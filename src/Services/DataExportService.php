<?php

namespace CorpWalletManager\Services;

use CorpWalletManager\Models\DataExport;
use CorpWalletManager\Support\JournalFilters;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Bulk CSV export service, mirroring Mining Manager's analogous flow.
 *
 * Wraps the multi-section CSV generation for the operator-initiated Data
 * Export feature. Section selection is constrained to the SECTIONS map so
 * the controller / UI / job all share the same surface; format is "zip"
 * (one CSV per section, packaged) or "csv" (one multi-section CSV with
 * labelled section headers separated by blank rows so Excel handles it
 * cleanly). Files land under storage/app/cwm-exports/{corp_id}/...
 *
 * Defensive discipline matches the rest of CWM. Raw journal export uses
 * `JournalFilters::excludeInternalTransfers` so corp-internal rebalancing
 * doesn't appear as donations or withdrawals. Character-keyed
 * aggregations skip NPC range ids (< 90M) and corp-self rows. Names are
 * resolved through EntityNameResolver where the column is an entity id.
 * Every CSV is emitted with a BOM so Excel handles UTF-8 reliably, and
 * every field gets a defensive quote via fputcsv.
 */
class DataExportService
{
    /**
     * Allowed section keys + human labels. The order here is the order
     * they appear in the multi-section CSV and in the ZIP file index.
     */
    public const SECTIONS = [
        'wallet_journal'      => 'Wallet Journal Entries',
        'contributions'       => 'Contribution Records',
        'reports'             => 'Report Metadata',
        'alerts'              => 'Alert History',
        'anomaly_state'       => 'Anomaly State Snapshot',
    ];

    public const FORMAT_ZIP = 'zip';
    public const FORMAT_CSV = 'csv';

    /** Hard ceiling so a runaway export doesn't fill the disk. */
    private const MAX_JOURNAL_ROWS = 500000;

    /** UTF-8 BOM so Excel decodes the CSV correctly without prompting. */
    private const UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Generate the export file and mark the DataExport row complete.
     *
     * Called from the ExportCorpWalletData job. The row is already
     * persisted; we update it in place rather than returning a new one
     * so the queue handler doesn't need to re-fetch.
     */
    public function generate(DataExport $export): void
    {
        try {
            $export->status = 'processing';
            $export->save();

            $sections = is_array($export->sections) ? $export->sections : [];
            $sections = array_values(array_intersect($sections, array_keys(self::SECTIONS)));

            if (empty($sections)) {
                throw new \InvalidArgumentException('No valid sections selected for export.');
            }

            $corpId = (int) $export->corporation_id;
            $dateFrom = $export->date_from ? Carbon::parse($export->date_from)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
            $dateTo = $export->date_to ? Carbon::parse($export->date_to)->endOfDay() : Carbon::now()->endOfDay();

            // Build the section content first, in memory (a section is a
            // [header_row, ...data_rows] pair). Empty sections render a
            // single "No data found for the selected period" row rather
            // than being silently dropped, so operators can see what
            // they asked for.
            $sectionData = [];
            foreach ($sections as $sectionKey) {
                $sectionData[$sectionKey] = $this->buildSection($sectionKey, $corpId, $dateFrom, $dateTo);
            }

            // Decide on the output shape and write the file.
            $relativeDir = 'cwm-exports/' . $corpId;
            $timestamp = Carbon::now()->format('Ymd_His');
            $format = $export->format ?: self::FORMAT_ZIP;

            if ($format === self::FORMAT_CSV) {
                $relativePath = $relativeDir . '/cwm_export_' . $timestamp . '.csv';
                $absolutePath = $this->absolutePath($relativePath);
                $this->ensureDir(dirname($absolutePath));
                $this->writeMultiSectionCsv($absolutePath, $sectionData);
            } else {
                $relativePath = $relativeDir . '/cwm_export_' . $timestamp . '.zip';
                $absolutePath = $this->absolutePath($relativePath);
                $this->ensureDir(dirname($absolutePath));
                $this->writeZip($absolutePath, $sectionData);
            }

            $export->file_path = $relativePath;
            $export->file_size_bytes = file_exists($absolutePath) ? filesize($absolutePath) : null;
            $export->status = 'complete';
            $export->completed_at = Carbon::now();
            $export->save();

            Log::info('DataExportService: export complete', [
                'id'         => $export->id,
                'corp_id'    => $corpId,
                'path'       => $relativePath,
                'size_bytes' => $export->file_size_bytes,
                'sections'   => $sections,
            ]);
        } catch (\Throwable $e) {
            Log::error('DataExportService: export failed', [
                'id'    => $export->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $export->status = 'failed';
            $export->error = mb_substr($e->getMessage(), 0, 2000);
            $export->completed_at = Carbon::now();
            $export->save();
        }
    }

    /**
     * Build one section's [header, ...rows] payload.
     *
     * Each branch is responsible for the right defensive guards: the
     * journal section runs `JournalFilters::excludeInternalTransfers`,
     * the contributions section skips NPC range ids and corp-self rows,
     * the reports section dereferences metadata only when present, etc.
     */
    protected function buildSection(string $sectionKey, int $corpId, Carbon $dateFrom, Carbon $dateTo): array
    {
        switch ($sectionKey) {
            case 'wallet_journal':
                return $this->buildWalletJournal($corpId, $dateFrom, $dateTo);
            case 'contributions':
                return $this->buildContributions($corpId, $dateFrom, $dateTo);
            case 'reports':
                return $this->buildReports($corpId, $dateFrom, $dateTo);
            case 'alerts':
                return $this->buildAlerts($corpId, $dateFrom, $dateTo);
            case 'anomaly_state':
                return $this->buildAnomalyState($corpId);
            default:
                return [['Section'], ['Unknown section: ' . $sectionKey]];
        }
    }

    /**
     * Wallet journal section.
     *
     * Joined to corporation_infos for the corp ticker, with the first and
     * second party names resolved through EntityNameResolver so external
     * recipients show as "Mercurialis Inc. [98692850]" rather than bare
     * snowflakes. Internal transfers are filtered out per the standard
     * CWM discipline.
     */
    protected function buildWalletJournal(int $corpId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $header = [
            'journal_id',
            'date',
            'ref_type',
            'amount',
            'balance',
            'first_party_id',
            'first_party_name',
            'second_party_id',
            'second_party_name',
            'context_id',
            'context_id_type',
            'reason',
            'description',
            'division',
        ];

        if (! Schema::hasTable('corporation_wallet_journals')) {
            return [$header, ['No corporation_wallet_journals table present.']];
        }

        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
            ->orderBy('date', 'asc')
            ->limit(self::MAX_JOURNAL_ROWS);

        JournalFilters::excludeInternalTransfers($query, $corpId);

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return [$header, ['No data found for the selected period.']];
        }

        // Resolve party ids in one batch (entity-name resolver caches
        // and round-trips ESI once per unique unknown id).
        $partyIds = [];
        foreach ($rows as $r) {
            if (! empty($r->first_party_id))  $partyIds[] = (int) $r->first_party_id;
            if (! empty($r->second_party_id)) $partyIds[] = (int) $r->second_party_id;
        }
        $partyIds = array_values(array_unique($partyIds));

        $nameMap = [];
        if (! empty($partyIds) && class_exists(EntityNameResolver::class)) {
            try {
                $nameMap = app(EntityNameResolver::class)->resolve($partyIds);
            } catch (\Throwable $e) {
                // Fallback: leave names blank rather than aborting the export.
                Log::warning('DataExportService: EntityNameResolver failed in wallet_journal section: ' . $e->getMessage());
            }
        }

        $data = [];
        foreach ($rows as $r) {
            $firstName = $nameMap[$r->first_party_id] ?? '';
            $secondName = $nameMap[$r->second_party_id] ?? '';

            $data[] = [
                (string) ($r->id ?? ''),
                (string) ($r->date ?? ''),
                (string) ($r->ref_type ?? ''),
                (string) ($r->amount ?? ''),
                (string) ($r->balance ?? ''),
                (string) ($r->first_party_id ?? ''),
                $firstName,
                (string) ($r->second_party_id ?? ''),
                $secondName,
                (string) ($r->context_id ?? ''),
                (string) ($r->context_id_type ?? ''),
                (string) ($r->reason ?? ''),
                (string) ($r->description ?? ''),
                (string) ($r->division ?? ''),
            ];
        }

        return array_merge([$header], $data);
    }

    /**
     * Per-character contribution records section.
     *
     * Reads the same cache that powers Top Contributors. Filtered to the
     * year-month range from the date picker. NPC-range character_ids are
     * defensively skipped at read time (the cleanup migration scrubbed
     * pre-v3 rows but a stray could survive).
     */
    protected function buildContributions(int $corpId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $header = [
            'year',
            'month',
            'character_id',
            'character_name',
            'ratting_amount',
            'ratting_count',
            'mission_amount',
            'mission_count',
            'industry_amount',
            'industry_count',
            'tax_payment_amount',
            'tax_payment_count',
            'donation_voluntary_amount',
            'donation_voluntary_count',
            'withdrawal_amount',
            'withdrawal_count',
            'total_amount',
        ];

        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return [$header, ['No corpwalletmanager_character_contributions table present.']];
        }

        $startYear = (int) $dateFrom->year;
        $startMonth = (int) $dateFrom->month;
        $endYear = (int) $dateTo->year;
        $endMonth = (int) $dateTo->month;

        $rows = DB::table('corpwalletmanager_character_contributions')
            ->where('corporation_id', $corpId)
            ->where('character_id', '>=', 90000000) // skip NPC range defensively
            ->where('character_id', '!=', $corpId)  // skip corp-self residue
            ->where(function ($q) use ($startYear, $startMonth, $endYear, $endMonth) {
                $q->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [
                    $startYear * 100 + $startMonth,
                    $endYear * 100 + $endMonth,
                ]);
            })
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->orderBy('character_id', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return [$header, ['No data found for the selected period.']];
        }

        // Resolve character names in one batch.
        $charIds = array_values(array_unique(array_map(fn ($r) => (int) $r->character_id, $rows->all())));
        $nameMap = [];
        if (! empty($charIds) && class_exists(EntityNameResolver::class)) {
            try {
                $nameMap = app(EntityNameResolver::class)->resolve($charIds);
            } catch (\Throwable $e) {
                Log::warning('DataExportService: EntityNameResolver failed in contributions section: ' . $e->getMessage());
            }
        }

        $data = [];
        foreach ($rows as $r) {
            $ratting    = (float) ($r->ratting_amount ?? 0);
            $mission    = (float) ($r->mission_amount ?? 0);
            $industry   = (float) ($r->industry_amount ?? 0);
            $tax        = (float) ($r->tax_payment_amount ?? 0);
            $donation   = (float) ($r->donation_voluntary_amount ?? 0);
            $withdrawal = (float) ($r->withdrawal_amount ?? 0);

            // Total excludes withdrawal (withdrawals are HR's net-position
            // signal, not contribution).
            $total = $ratting + $mission + $industry + $tax + $donation;

            $data[] = [
                (string) ($r->year ?? ''),
                (string) ($r->month ?? ''),
                (string) ($r->character_id ?? ''),
                $nameMap[$r->character_id] ?? '',
                (string) $ratting,
                (string) ($r->ratting_count ?? 0),
                (string) $mission,
                (string) ($r->mission_count ?? 0),
                (string) $industry,
                (string) ($r->industry_count ?? 0),
                (string) $tax,
                (string) ($r->tax_payment_count ?? 0),
                (string) $donation,
                (string) ($r->donation_voluntary_count ?? 0),
                (string) $withdrawal,
                (string) ($r->withdrawal_count ?? 0),
                (string) $total,
            ];
        }

        return array_merge([$header], $data);
    }

    /**
     * Reports metadata section.
     *
     * Metadata only, NOT the PDF body. Report files / Discord deliveries
     * are exported separately via the Reports History UI; this section
     * is for auditors who want a list of every report that was generated
     * for the corp in a window.
     */
    protected function buildReports(int $corpId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $header = [
            'id',
            'corporation_id',
            'report_type',
            'period_start',
            'period_end',
            'generated_at',
            'generated_by',
            'sent_to_discord',
            'has_pdf',
            'has_csv',
        ];

        if (! Schema::hasTable('corpwalletmanager_reports')) {
            return [$header, ['No corpwalletmanager_reports table present.']];
        }

        $rows = DB::table('corpwalletmanager_reports')
            ->where('corporation_id', $corpId)
            ->whereBetween('generated_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
            ->orderBy('generated_at', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return [$header, ['No data found for the selected period.']];
        }

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (string) ($r->id ?? ''),
                (string) ($r->corporation_id ?? ''),
                (string) ($r->report_type ?? ''),
                (string) ($r->period_start ?? ''),
                (string) ($r->period_end ?? ''),
                (string) ($r->generated_at ?? ''),
                (string) ($r->generated_by ?? ''),
                (string) ($r->sent_to_discord ?? '0'),
                (string) (! empty($r->pdf_path) ? '1' : '0'),
                (string) (! empty($r->csv_path) ? '1' : '0'),
            ];
        }

        return array_merge([$header], $data);
    }

    /**
     * Alert history section.
     *
     * Best-effort: derived from the alert_state + anomaly_state tables
     * since CWM doesn't keep a per-row alert log (the latch tables are
     * the audit trail). Each crossing is one row.
     */
    protected function buildAlerts(int $corpId, Carbon $dateFrom, Carbon $dateTo): array
    {
        $header = [
            'alert_kind',
            'corporation_id',
            'character_id',
            'notified_at',
            'detail',
        ];

        $data = [];

        // Low-balance crossings from alert_state.
        if (Schema::hasTable('corpwalletmanager_alert_state')) {
            $rows = DB::table('corpwalletmanager_alert_state')
                ->where('corporation_id', $corpId)
                ->whereNotNull('balance_low_notified_at')
                ->whereBetween('balance_low_notified_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
                ->get();
            foreach ($rows as $r) {
                $data[] = [
                    'low_balance',
                    (string) $corpId,
                    '',
                    (string) ($r->balance_low_notified_at ?? ''),
                    'Latched: ' . ($r->balance_is_low ? 'yes' : 'no'),
                ];
            }
        }

        // Contribution drop crossings from anomaly_state.
        if (Schema::hasTable('corpwalletmanager_anomaly_state')) {
            $rows = DB::table('corpwalletmanager_anomaly_state')
                ->where('corporation_id', $corpId)
                ->whereNotNull('contribution_drop_notified_at')
                ->whereBetween('contribution_drop_notified_at', [$dateFrom->toDateTimeString(), $dateTo->toDateTimeString()])
                ->get();
            foreach ($rows as $r) {
                $data[] = [
                    'contribution_drop',
                    (string) $corpId,
                    (string) ($r->character_id ?? ''),
                    (string) ($r->contribution_drop_notified_at ?? ''),
                    'Prior avg ' . ($r->contribution_drop_prior_avg ?? '0') . ' -> recent avg ' . ($r->contribution_drop_recent_avg ?? '0'),
                ];
            }
        }

        if (empty($data)) {
            return [$header, ['No alert history found for the selected period.']];
        }

        // Sort the merged result by notified_at so the section reads as a timeline.
        usort($data, fn ($a, $b) => strcmp($a[3] ?? '', $b[3] ?? ''));

        return array_merge([$header], $data);
    }

    /**
     * Anomaly state snapshot.
     *
     * Current state of every member-corp pair in the anomaly_state table
     * (latched-or-not, prior + recent averages). No date filter - this is
     * a now-snapshot, not a window slice.
     */
    protected function buildAnomalyState(int $corpId): array
    {
        $header = [
            'corporation_id',
            'character_id',
            'character_name',
            'contribution_drop_latched',
            'contribution_drop_prior_avg',
            'contribution_drop_recent_avg',
            'contribution_drop_notified_at',
        ];

        if (! Schema::hasTable('corpwalletmanager_anomaly_state')) {
            return [$header, ['No corpwalletmanager_anomaly_state table present.']];
        }

        $rows = DB::table('corpwalletmanager_anomaly_state')
            ->where('corporation_id', $corpId)
            ->orderBy('character_id', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return [$header, ['No anomaly state recorded for this corp.']];
        }

        $charIds = array_values(array_unique(array_map(fn ($r) => (int) $r->character_id, $rows->all())));
        $nameMap = [];
        if (! empty($charIds) && class_exists(EntityNameResolver::class)) {
            try {
                $nameMap = app(EntityNameResolver::class)->resolve($charIds);
            } catch (\Throwable $e) {
                Log::warning('DataExportService: EntityNameResolver failed in anomaly_state section: ' . $e->getMessage());
            }
        }

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                (string) ($r->corporation_id ?? ''),
                (string) ($r->character_id ?? ''),
                $nameMap[$r->character_id] ?? '',
                $r->contribution_drop_latched ? 'yes' : 'no',
                (string) ($r->contribution_drop_prior_avg ?? '0'),
                (string) ($r->contribution_drop_recent_avg ?? '0'),
                (string) ($r->contribution_drop_notified_at ?? ''),
            ];
        }

        return array_merge([$header], $data);
    }

    /**
     * Write a multi-section single CSV.
     *
     * Each section is preceded by a `# Section Label` row and followed
     * by a blank row, so Excel handles the boundaries without merging
     * adjacent sections.
     */
    protected function writeMultiSectionCsv(string $absolutePath, array $sectionData): void
    {
        $handle = fopen($absolutePath, 'w');
        if (! $handle) {
            throw new \RuntimeException('Failed to open ' . $absolutePath . ' for writing.');
        }

        try {
            // BOM first so Excel reads as UTF-8.
            fwrite($handle, self::UTF8_BOM);

            foreach ($sectionData as $sectionKey => $rows) {
                $label = self::SECTIONS[$sectionKey] ?? $sectionKey;
                fputcsv($handle, ['# ' . $label]);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fputcsv($handle, []); // blank separator
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write a ZIP archive with one CSV per section.
     */
    protected function writeZip(string $absolutePath, array $sectionData): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('ZipArchive PHP extension not available; switch the export format to CSV.');
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to open ' . $absolutePath . ' for writing.');
        }

        try {
            foreach ($sectionData as $sectionKey => $rows) {
                $label = self::SECTIONS[$sectionKey] ?? $sectionKey;
                $entry = $sectionKey . '.csv';

                // Generate CSV content in memory via a temp stream.
                $tmp = fopen('php://temp', 'r+');
                fwrite($tmp, self::UTF8_BOM);
                fputcsv($tmp, ['# ' . $label]);
                foreach ($rows as $row) {
                    fputcsv($tmp, $row);
                }
                rewind($tmp);
                $contents = stream_get_contents($tmp);
                fclose($tmp);

                $zip->addFromString($entry, $contents);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Resolve a storage-relative path to an absolute filesystem path.
     */
    public function absolutePath(string $relativePath): string
    {
        return Storage::disk('local')->path($relativePath);
    }

    /**
     * mkdir -p the storage directory for the export file.
     */
    protected function ensureDir(string $absoluteDir): void
    {
        if (! is_dir($absoluteDir)) {
            @mkdir($absoluteDir, 0775, true);
        }
    }
}
