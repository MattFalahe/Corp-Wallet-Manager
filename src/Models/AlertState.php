<?php

namespace CorpWalletManager\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * Per-corporation alert latch.
 *
 * One row per corporation that has been low at least once, tracking whether
 * its wallet balance is currently below the low-balance threshold. The
 * hourly detector uses this so a low-balance alert fires once on the
 * crossing rather than every run while the balance stays low.
 */
class AlertState extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_alert_state';

    /**
     * The table is keyed by corporation_id, not a surrogate auto-increment.
     *
     * @var string
     */
    protected $primaryKey = 'corporation_id';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    protected $keyType = 'int';

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id',
        'balance_is_low',
        'balance_low_notified_at',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id'          => 'integer',
        'balance_is_low'          => 'boolean',
        'balance_low_notified_at' => 'datetime',
    ];
}
