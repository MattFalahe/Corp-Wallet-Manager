<?php

namespace CorpWalletManager\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * One row per (corporation_id, character_id) holding the last-published
 * milestone / stall / compliance-drop state used by the
 * MemberMilestoneNotifier service. See migration 000007 for column
 * semantics.
 */
class MemberMilestoneState extends ExtensibleModel
{
    /** @var string */
    protected $table = 'corpwalletmanager_member_milestone_state';

    /** @var array<int, string> */
    protected $primaryKey = ['corporation_id', 'character_id'];

    /** @var bool */
    public $incrementing = false;

    /** @var array */
    protected $fillable = [
        'corporation_id',
        'character_id',
        'last_stalled_period',
        'highest_milestone_isk',
        'last_compliance_drop_period',
    ];

    /** @var array */
    protected $casts = [
        'corporation_id'        => 'integer',
        'character_id'          => 'integer',
        'highest_milestone_isk' => 'float',
    ];
}
