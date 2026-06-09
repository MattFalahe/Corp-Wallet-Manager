<?php

namespace CorpWalletManager\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * One operator-initiated data export.
 *
 * Lifecycle: a row is inserted in `pending` status by the controller when
 * the operator hits Generate; the queued ExportCorpWalletData job picks
 * it up, flips it to `processing`, writes the file under
 * storage/app/cwm-exports/{corp_id}/{timestamp}.zip, then either
 * `complete` or `failed`. The Recent Exports table on Settings reads
 * this table directly.
 *
 * Sections are stored as a JSON array of section keys (see
 * DataExportService::SECTIONS); cast to array so callers don't need to
 * decode by hand. Status is a plain string so future statuses don't
 * need a migration.
 */
class DataExport extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_data_exports';

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id',
        'user_id',
        'requested_at',
        'completed_at',
        'status',
        'file_path',
        'file_size_bytes',
        'sections',
        'format',
        'date_from',
        'date_to',
        'error',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id'  => 'integer',
        'user_id'         => 'integer',
        'requested_at'    => 'datetime',
        'completed_at'    => 'datetime',
        'file_size_bytes' => 'integer',
        'sections'        => 'array',
        'date_from'       => 'date',
        'date_to'         => 'date',
    ];
}
