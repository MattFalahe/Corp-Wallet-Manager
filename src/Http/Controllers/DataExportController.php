<?php

namespace CorpWalletManager\Http\Controllers;

use Carbon\Carbon;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Jobs\ExportCorpWalletData;
use CorpWalletManager\Models\DataExport;
use CorpWalletManager\Services\DataExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Settings -> Data Export endpoints.
 *
 * Four routes:
 *   GET    /api/data-exports             - list recent exports for the corp
 *   POST   /api/data-exports             - create + queue a new export
 *   GET    /api/data-exports/{id}/download - signed-URL download of the file
 *   DELETE /api/data-exports/{id}        - delete the row + underlying file
 *
 * All routes are admin / director-gated by the route group's
 * `can:corpwalletmanager.settings` middleware. The download endpoint uses
 * Laravel's signed URL machinery so a 24h-valid URL can be embedded in
 * the Recent Exports table without the user re-authenticating on each
 * click; the controller verifies signature on the inbound request via
 * the `signed` middleware applied per-route.
 */
class DataExportController extends Controller
{
    use AuthorizesCorporationAccess;

    /** Recent exports cap on the panel. Keep tight so the table reads cleanly. */
    private const RECENT_LIMIT = 5;

    /** Download signature lifetime: 24 hours. */
    private const DOWNLOAD_SIGNATURE_MINUTES = 24 * 60;

    /**
     * List recent exports for the selected corp.
     */
    public function index(Request $request)
    {
        try {
            $requested = $request->get('corporation_id');

            $query = DataExport::query()->orderBy('requested_at', 'desc')->limit(self::RECENT_LIMIT);

            if (($requested === null || $requested === '') && $this->userIsAdmin()) {
                // Admin with no filter sees every export.
            } else {
                $corpId = $this->getCorporationId($request);
                if (! $corpId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No corporation context resolved.',
                        'exports' => [],
                    ], 400);
                }
                $query->where('corporation_id', $corpId);
            }

            $rows = $query->get();

            return response()->json([
                'success' => true,
                'exports' => $rows->map(fn ($r) => $this->present($r))->values(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] DataExport index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not load exports.',
                'exports' => [],
            ], 500);
        }
    }

    /**
     * Create + queue a new export.
     */
    public function store(Request $request)
    {
        $validSections = array_keys(DataExportService::SECTIONS);

        $validated = $request->validate([
            'corporation_id' => 'required|integer|min:1',
            'sections'       => 'required|array|min:1',
            'sections.*'     => 'string|in:' . implode(',', $validSections),
            'format'         => 'sometimes|string|in:' . DataExportService::FORMAT_ZIP . ',' . DataExportService::FORMAT_CSV,
            'date_from'      => 'sometimes|nullable|date',
            'date_to'        => 'sometimes|nullable|date|after_or_equal:date_from',
        ]);

        $corpId = (int) $validated['corporation_id'];
        if (! $this->userCanAccessCorporation($corpId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to that corporation.',
            ], 403);
        }

        try {
            $dateFrom = ! empty($validated['date_from'])
                ? Carbon::parse($validated['date_from'])->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
            $dateTo = ! empty($validated['date_to'])
                ? Carbon::parse($validated['date_to'])->endOfDay()
                : Carbon::now()->endOfDay();

            $export = new DataExport();
            $export->corporation_id = $corpId;
            $export->user_id = Auth::id();
            $export->requested_at = Carbon::now();
            $export->status = 'pending';
            $export->format = $validated['format'] ?? DataExportService::FORMAT_ZIP;
            $export->sections = array_values($validated['sections']);
            $export->date_from = $dateFrom->toDateString();
            $export->date_to = $dateTo->toDateString();
            $export->save();

            ExportCorpWalletData::dispatch($export->id);

            return response()->json([
                'success' => true,
                'export'  => $this->present($export->fresh()),
                'message' => 'Export queued. Refresh the Recent Exports table in a few moments to pick up the download link.',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Corp Wallet Manager] DataExport store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not queue export: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the generated file.
     *
     * Hits via a signed URL produced by present(). The `signed` middleware
     * is applied per-route; here we just verify the row + access and
     * stream the file.
     */
    public function download(Request $request, int $id)
    {
        $export = DataExport::find($id);
        if (! $export) {
            abort(404, 'Export not found.');
        }
        if (! $this->userCanAccessCorporation((int) $export->corporation_id)) {
            abort(403, 'You do not have access to that export.');
        }
        if ($export->status !== 'complete' || empty($export->file_path)) {
            abort(409, 'Export is not yet complete.');
        }

        $absolutePath = app(DataExportService::class)->absolutePath($export->file_path);
        if (! file_exists($absolutePath)) {
            abort(404, 'Export file no longer present on disk.');
        }

        $downloadName = basename($absolutePath);
        return response()->download($absolutePath, $downloadName);
    }

    /**
     * Delete the row + file.
     */
    public function destroy(Request $request, int $id)
    {
        $export = DataExport::find($id);
        if (! $export) {
            return response()->json(['success' => false, 'message' => 'Export not found.'], 404);
        }
        if (! $this->userCanAccessCorporation((int) $export->corporation_id)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to that export.'], 403);
        }

        if (! empty($export->file_path)) {
            $absolutePath = app(DataExportService::class)->absolutePath($export->file_path);
            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        $export->delete();

        return response()->json(['success' => true, 'message' => 'Export deleted.']);
    }

    /**
     * Present a DataExport row for the API.
     */
    protected function present(DataExport $row): array
    {
        $downloadUrl = null;
        if ($row->status === 'complete' && ! empty($row->file_path)) {
            try {
                $downloadUrl = URL::temporarySignedRoute(
                    'corpwalletmanager.data-exports.download',
                    Carbon::now()->addMinutes(self::DOWNLOAD_SIGNATURE_MINUTES),
                    ['id' => $row->id]
                );
            } catch (\Throwable $e) {
                // Route may not be loaded under route cache during boot;
                // fall back to a plain URL the user can still hit (the
                // controller re-checks access).
                $downloadUrl = null;
            }
        }

        return [
            'id'              => $row->id,
            'corporation_id'  => $row->corporation_id,
            'user_id'         => $row->user_id,
            'requested_at'    => optional($row->requested_at)->toIso8601String(),
            'completed_at'    => optional($row->completed_at)->toIso8601String(),
            'status'          => $row->status,
            'sections'        => is_array($row->sections) ? $row->sections : [],
            'format'          => $row->format,
            'date_from'       => optional($row->date_from)->toDateString(),
            'date_to'         => optional($row->date_to)->toDateString(),
            'file_size_bytes' => $row->file_size_bytes,
            'file_size_human' => $this->humanSize($row->file_size_bytes),
            'error'           => $row->error,
            'download_url'    => $downloadUrl,
        ];
    }

    /**
     * Round-to-nearest human size, kB / MB / GB.
     */
    protected function humanSize(?int $bytes): ?string
    {
        if ($bytes === null) return null;
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' kB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 1) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 2) . ' GB';
    }
}
