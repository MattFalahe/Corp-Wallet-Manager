<?php

namespace CorpWalletManager\Jobs;

use CorpWalletManager\Models\DataExport;
use CorpWalletManager\Services\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Worker job behind the Settings -> Data Export form.
 *
 * The controller writes a `pending` DataExport row, dispatches this job
 * with the row's id, and returns immediately so the operator doesn't
 * stare at a spinner while the export runs. The job picks the row up,
 * defers all the work to DataExportService::generate(), and the service
 * is responsible for flipping the row to processing -> complete | failed
 * with file_path + file_size_bytes + completed_at populated.
 *
 * The DataExportService also handles its own failure logging and writes
 * the error message back to the row, so the queue retry layer doesn't
 * also need to try; tries=1 + a long timeout matches BackfillCharacterContributions.
 */
class ExportCorpWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Wide timeout: very large date ranges on busy corps can take a while. */
    public $timeout = 1800;

    /** No retry: the service writes status=failed + error on its own. */
    public $tries = 1;

    private int $exportId;

    public function __construct(int $exportId)
    {
        $this->exportId = $exportId;
    }

    public function tags(): array
    {
        return ['corpwalletmanager', 'data-export', 'export-' . $this->exportId];
    }

    public function handle(DataExportService $service): void
    {
        $export = DataExport::find($this->exportId);
        if (! $export) {
            Log::warning('ExportCorpWalletData: DataExport row not found', ['id' => $this->exportId]);
            return;
        }

        Log::info('ExportCorpWalletData: starting', [
            'id'      => $export->id,
            'corp_id' => $export->corporation_id,
        ]);

        $service->generate($export);
    }
}
