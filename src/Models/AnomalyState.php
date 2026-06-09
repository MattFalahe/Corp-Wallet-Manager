<?php

namespace CorpWalletManager\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * Per-(corp, character) anomaly latch.
 *
 * Currently holds the contribution_drop latch so the hourly detector
 * fires once on the crossing rather than every run while a member's
 * contributions remain depressed. A small audit trail (prior + recent
 * 3-month averages, timestamp of last notification) is kept inline so
 * operators can review what triggered the alert without reading logs.
 *
 * Future anomaly detectors that need per-character latching can extend
 * this table with extra columns rather than introducing parallel state
 * tables.
 */
class AnomalyState extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_anomaly_state';

    /**
     * Composite primary key on (corporation_id, character_id). Eloquent
     * does not handle composite PKs natively; firstOrNew + manual save
     * is the supported pattern used elsewhere in the suite.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id',
        'character_id',
        'contribution_drop_latched',
        'contribution_drop_prior_avg',
        'contribution_drop_recent_avg',
        'contribution_drop_notified_at',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id'                => 'integer',
        'character_id'                  => 'integer',
        'contribution_drop_latched'     => 'boolean',
        'contribution_drop_prior_avg'   => 'float',
        'contribution_drop_recent_avg'  => 'float',
        'contribution_drop_notified_at' => 'datetime',
    ];
}
