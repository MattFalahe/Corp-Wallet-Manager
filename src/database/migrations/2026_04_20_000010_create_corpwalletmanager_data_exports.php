<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data Export feature, modelled on Mining Manager's bulk CSV export pattern.
 *
 * One row per operator-initiated export. The ExportCorpWalletData job
 * generates a ZIP of CSVs (or a single multi-section CSV) under
 * storage/app/cwm-exports/{corp_id}/{timestamp}.zip and updates the row
 * with the file_path + file_size_bytes + completed_at when done. Failures
 * land in the error column so the Recent Exports table can surface them
 * inline rather than burying them in the queue log.
 *
 * Sections are stored as a JSON array of section keys so the chosen
 * subset is auditable after the fact (a re-run with the same form values
 * lands the same sections without re-deriving). Status enum is a simple
 * pending / processing / complete / failed string so it can be filtered
 * in the UI without joining auxiliary tables.
 */
class CreateCorpwalletmanagerDataExports extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('corpwalletmanager_data_exports')) {
            Schema::create('corpwalletmanager_data_exports', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Corporation scope. Nullable for the rare cross-corp
                // admin export, but every UI path sets it.
                $table->bigInteger('corporation_id')->nullable();

                // SeAT user who initiated the export. Stored for audit so
                // operators can see who pulled what data when.
                $table->bigInteger('user_id')->nullable();

                $table->timestamp('requested_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();

                // Lifecycle: pending -> processing -> complete | failed.
                // Stored as string so a future status doesn't need a
                // migration to add.
                $table->string('status', 24)->default('pending');

                // Storage-relative path under storage/app/. Used to build
                // the signed download URL. Null until the job completes.
                $table->string('file_path', 512)->nullable();

                // Persisted so the Recent Exports table can render the
                // size without stat-ing the file each render.
                $table->bigInteger('file_size_bytes')->nullable();

                // JSON array of section keys the user picked (see
                // DataExportService::SECTIONS). Audit + re-render hint.
                $table->json('sections')->nullable();

                $table->string('format', 16)->default('zip');

                $table->date('date_from')->nullable();
                $table->date('date_to')->nullable();

                // Failure detail; null on the happy path. Truncated to a
                // reasonable read length in the UI.
                $table->text('error')->nullable();

                $table->timestamps();

                $table->index('corporation_id');
                $table->index(['corporation_id', 'requested_at']);
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('corpwalletmanager_data_exports');
    }
}
