<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-solar-system breakdown of Planetary Interaction customs tax (POCO
 * import + export duty) for the Director "Planetary Tax" view.
 *
 * The corp earns planetary_import_tax / planetary_export_tax whenever a
 * pilot moves PI goods through one of its customs offices. CCP stamps each
 * of those journal rows with context_id_type = 'planet_id' and context_id
 * = the planet the office orbits, so every tax row resolves to a solar
 * system through the SDE: planet itemID -> mapDenormalize.solarSystemID ->
 * that system's own mapDenormalize row for the name + security. This
 * answers "which systems actually generate the PI tax income" instead of
 * just the corp-wide total the ref_type breakdown already shows.
 *
 * Rows whose planet can't be resolved (no SDE row, or a null context_id)
 * fall into an "Unknown System" bucket so the per-system totals always
 * reconcile with the raw planetary-tax total.
 *
 * The per-system detail lives in the table (getCurrentPeriod / getForRange);
 * the trend chart (getTrend) deliberately plots only import vs export
 * totals so it stays readable no matter how many systems a corp taxes.
 *
 * Note on internal-transfer filtering: PI customs tax is always paid by a
 * pilot to the corp (first_party = character, second_party = corp), never
 * a first==second==corp inter-division movement, so the internal-transfer
 * filter the other CWM aggregates apply would be a no-op here and is
 * omitted on purpose.
 */
class PlanetaryTaxService
{
    private const REF_TYPES = ['planetary_import_tax', 'planetary_export_tax'];

    /** Catch-all bucket id for tax rows whose planet won't resolve. */
    private const UNKNOWN_SYSTEM_ID = 0;

    /** Day windows the trend chart offers. */
    public const TREND_WINDOWS = [30, 90, 180, 365];

    /**
     * Single-month per-system breakdown for the Director tab. Mirrors
     * ExpenseAttributionService::getCurrentPeriod: a per-system list with
     * import / export / total, tx count, % of total, and trend vs the
     * immediately-preceding calendar month, plus a per-planet drill-down.
     *
     * Return shape:
     *   corporation_id, period (YYYY-MM),
     *   total_import, total_export, total, prior_total,
     *   by_system: [
     *     {system_id, system, security, import_tax, export_tax, total,
     *      tx_count, pct_of_total, trend_vs_prior_pct,
     *      planets: [{planet_id, planet, import_tax, export_tax, total, tx_count}]}
     *   ]  // sorted by total desc
     */
    public function getCurrentPeriod(int $corpId, string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = Carbon::now()->format('Y-m');
        }

        [$from, $to] = $this->periodRange($period);
        $current = $this->aggregateRange($corpId, $from, $to);

        [$pFrom, $pTo] = $this->periodRange($this->priorPeriod($period));
        $prior = $this->aggregateRange($corpId, $pFrom, $pTo);

        $priorTotalBySystem = [];
        foreach ($prior['by_system'] as $s) {
            $priorTotalBySystem[$s['system_id']] = $s['total'];
        }

        $grandTotal = $current['total'];
        foreach ($current['by_system'] as &$s) {
            $s['pct_of_total'] = $grandTotal > 0 ? ($s['total'] / $grandTotal) * 100.0 : 0.0;

            $priorTotal = $priorTotalBySystem[$s['system_id']] ?? 0.0;
            $s['trend_vs_prior_pct'] = $priorTotal > 0.0
                ? max(-1000.0, min(1000.0, (($s['total'] - $priorTotal) / $priorTotal) * 100.0))
                : null;
        }
        unset($s);

        return [
            'corporation_id' => $corpId,
            'period'         => $period,
            'total_import'   => $current['total_import'],
            'total_export'   => $current['total_export'],
            'total'          => $current['total'],
            'prior_total'    => $prior['total'],
            'by_system'      => $current['by_system'],
        ];
    }

    /**
     * Arbitrary-range per-system breakdown for the scheduled-report
     * surfaces. Same shape as getCurrentPeriod minus the prior-period
     * trend (a report does its own period comparison).
     */
    public function getForRange(int $corpId, $from, $to): array
    {
        $agg = $this->aggregateRange($corpId, $from, $to);

        $grandTotal = $agg['total'];
        foreach ($agg['by_system'] as &$s) {
            $s['pct_of_total'] = $grandTotal > 0 ? ($s['total'] / $grandTotal) * 100.0 : 0.0;
        }
        unset($s);

        return [
            'corporation_id' => $corpId,
            'from'           => is_string($from) ? $from : (string) $from,
            'to'             => is_string($to) ? $to : (string) $to,
            'total_import'   => $agg['total_import'],
            'total_export'   => $agg['total_export'],
            'total'          => $agg['total'],
            'by_system'      => $agg['by_system'],
        ];
    }

    /**
     * Import vs export tax totals over a trailing day window (30 / 90 /
     * 180 / 365) for the trend chart. Two series only, so the chart stays
     * readable regardless of how many systems the corp taxes. Buckets
     * auto-scale: daily up to a month, weekly up to 180 days, monthly for
     * a year.
     *
     * Return shape:
     *   corporation_id, days, bucket ('day'|'week'|'month'),
     *   labels: ['Aug 3', ...],  // x-axis, oldest first
     *   import: [float, ...],    // aligned to labels
     *   export: [float, ...]
     */
    public function getTrend(int $corpId, int $days = 90): array
    {
        if (! in_array($days, self::TREND_WINDOWS, true)) {
            $days = 90;
        }

        $mode = $days <= 31 ? 'day' : ($days <= 180 ? 'week' : 'month');

        $end   = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        [$bucketKeys, $labels] = $this->buildBuckets($start, $end, $mode);

        $bucketExpr = match ($mode) {
            'day'   => 'DATE(date)',
            'week'  => 'DATE(DATE_SUB(date, INTERVAL WEEKDAY(date) DAY))',
            'month' => "DATE_FORMAT(date, '%Y-%m')",
        };

        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereIn('ref_type', self::REF_TYPES)
            ->whereBetween('date', [$start, $end])
            ->selectRaw("$bucketExpr AS bucket, ref_type, SUM(amount) AS total")
            ->groupByRaw("$bucketExpr, ref_type")
            ->get();

        $import    = array_fill_keys($bucketKeys, 0.0);
        $export    = array_fill_keys($bucketKeys, 0.0);
        $bucketSet = array_fill_keys($bucketKeys, true);

        foreach ($rows as $r) {
            $bk = (string) $r->bucket;
            if (! isset($bucketSet[$bk])) {
                continue;
            }
            if ($r->ref_type === 'planetary_import_tax') {
                $import[$bk] += (float) $r->total;
            } else {
                $export[$bk] += (float) $r->total;
            }
        }

        return [
            'corporation_id' => $corpId,
            'days'           => $days,
            'bucket'         => $mode,
            'labels'         => $labels,
            'import'         => array_map(fn ($k) => (float) $import[$k], $bucketKeys),
            'export'         => array_map(fn ($k) => (float) $export[$k], $bucketKeys),
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Group the corp's PI-tax journal rows by (planet, ref_type) over the
     * range, resolve each distinct planet to its solar system via the SDE,
     * then roll up to per-system totals (with per-planet detail retained
     * for the drill-down). Unresolvable planets bucket under "Unknown
     * System" so totals always reconcile.
     */
    private function aggregateRange(int $corpId, $from, $to): array
    {
        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereIn('ref_type', self::REF_TYPES)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('context_id AS planet_id, ref_type, SUM(amount) AS total, COUNT(*) AS cnt')
            ->groupBy('context_id', 'ref_type')
            ->get();

        $planetIds = $rows
            ->pluck('planet_id')
            ->filter(fn ($v) => $v !== null)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->all();
        $planetMap = $this->resolvePlanets($planetIds);

        $systems     = [];
        $totalImport = 0.0;
        $totalExport = 0.0;

        foreach ($rows as $r) {
            $planetId = $r->planet_id !== null ? (int) $r->planet_id : 0;
            $info     = $planetMap[$planetId] ?? null;

            $systemId   = $info['system_id']   ?? self::UNKNOWN_SYSTEM_ID;
            $systemName = $info['system_name'] ?? 'Unknown System';
            $security   = $info['security']    ?? null;
            $planetName = $info['planet_name'] ?? ($planetId > 0 ? ('Planet ' . $planetId) : 'Unknown Planet');

            if (! isset($systems[$systemId])) {
                $systems[$systemId] = [
                    'system_id'  => $systemId,
                    'system'     => $systemName,
                    'security'   => $security,
                    'import_tax' => 0.0,
                    'export_tax' => 0.0,
                    'total'      => 0.0,
                    'tx_count'   => 0,
                    'planets'    => [],
                ];
            }
            if (! isset($systems[$systemId]['planets'][$planetId])) {
                $systems[$systemId]['planets'][$planetId] = [
                    'planet_id'  => $planetId,
                    'planet'     => $planetName,
                    'import_tax' => 0.0,
                    'export_tax' => 0.0,
                    'total'      => 0.0,
                    'tx_count'   => 0,
                ];
            }

            $amount = (float) $r->total;
            $cnt    = (int) $r->cnt;

            if ($r->ref_type === 'planetary_import_tax') {
                $systems[$systemId]['import_tax']                       += $amount;
                $systems[$systemId]['planets'][$planetId]['import_tax'] += $amount;
                $totalImport                                           += $amount;
            } else {
                $systems[$systemId]['export_tax']                       += $amount;
                $systems[$systemId]['planets'][$planetId]['export_tax'] += $amount;
                $totalExport                                           += $amount;
            }

            $systems[$systemId]['total']                          += $amount;
            $systems[$systemId]['tx_count']                       += $cnt;
            $systems[$systemId]['planets'][$planetId]['total']    += $amount;
            $systems[$systemId]['planets'][$planetId]['tx_count'] += $cnt;
        }

        $bySystem = [];
        foreach ($systems as $s) {
            $planets = array_values($s['planets']);
            usort($planets, fn ($a, $b) => $b['total'] <=> $a['total']);
            $s['planets'] = $planets;
            $bySystem[]   = $s;
        }
        usort($bySystem, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total_import' => $totalImport,
            'total_export' => $totalExport,
            'total'        => $totalImport + $totalExport,
            'by_system'    => $bySystem,
        ];
    }

    /**
     * Batch-resolve planet itemIDs to their solar system via the SDE.
     * One query, self-joining mapDenormalize (planet row -> its system's
     * row) for the system name + security. Returns a map keyed by planet
     * id; ids missing from the SDE simply don't appear (caller buckets
     * them as Unknown). No-ops to an empty map when the SDE isn't loaded.
     *
     * @param  array<int>  $planetIds
     * @return array<int, array{planet_name:string, system_id:int, system_name:string, security:?float}>
     */
    private function resolvePlanets(array $planetIds): array
    {
        if (empty($planetIds) || ! Schema::hasTable('mapDenormalize')) {
            return [];
        }

        $rows = DB::table('mapDenormalize as p')
            ->leftJoin('mapDenormalize as s', 's.itemID', '=', 'p.solarSystemID')
            ->whereIn('p.itemID', $planetIds)
            ->get([
                'p.itemID as planet_id',
                'p.itemName as planet_name',
                'p.solarSystemID as system_id',
                's.itemName as system_name',
                's.security as security',
            ]);

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->planet_id] = [
                'planet_name' => (string) ($r->planet_name ?? ''),
                'system_id'   => $r->system_id !== null ? (int) $r->system_id : self::UNKNOWN_SYSTEM_ID,
                'system_name' => $r->system_name !== null ? (string) $r->system_name : 'Unknown System',
                'security'    => $r->security !== null ? round((float) $r->security, 2) : null,
            ];
        }

        return $map;
    }

    /**
     * Dense bucket keys + human x-axis labels spanning [start, end] at the
     * given granularity. Keys match the SQL bucket expression output:
     * day/week -> 'Y-m-d' (week = its Monday), month -> 'Y-m'.
     *
     * @return array{0: array<int,string>, 1: array<int,string>}
     */
    private function buildBuckets(Carbon $start, Carbon $end, string $mode): array
    {
        $keys   = [];
        $labels = [];

        if ($mode === 'day') {
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $keys[]   = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M j');
                $cursor->addDay();
            }
        } elseif ($mode === 'week') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            while ($cursor->lte($end)) {
                $keys[]   = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M j');
                $cursor->addWeek();
            }
        } else { // month
            $cursor = $start->copy()->startOfMonth();
            $last   = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $keys[]   = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor->addMonth();
            }
        }

        return [$keys, $labels];
    }

    /**
     * "YYYY-MM" -> [first-day, last-day] timestamps for whereBetween('date').
     *
     * @return array{0:string,1:string}
     */
    private function periodRange(string $period): array
    {
        [$y, $m] = array_map('intval', explode('-', $period));
        $start = Carbon::createFromDate($y, $m, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /** "YYYY-MM" of the calendar month immediately before $period. */
    private function priorPeriod(string $period): string
    {
        [$y, $m] = array_map('intval', explode('-', $period));

        return Carbon::createFromDate($y, $m, 1)->subMonth()->format('Y-m');
    }
}
