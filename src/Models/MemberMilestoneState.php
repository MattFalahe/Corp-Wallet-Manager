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

    /**
     * The table's real primary key is the composite
     * (corporation_id, character_id), but Eloquent only understands a
     * single-column key: handing it an array made every UPDATE fail with
     * "Cannot access offset of type array on array", because it used the
     * array itself as an attribute offset. We name one column here to
     * keep Eloquent's internals happy and constrain saves on BOTH columns
     * in setKeysForSaveQuery() below, which is what actually keeps an
     * update pinned to a single row.
     *
     * @var string
     */
    protected $primaryKey = 'corporation_id';

    /** @var string */
    protected $keyType = 'int';

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

    /**
     * Pin an update to the one row identified by the full composite key.
     * Without this, Eloquent would scope the UPDATE to corporation_id
     * alone and rewrite every member's state row for that corp.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('corporation_id', $this->getOriginal('corporation_id', $this->getAttribute('corporation_id')))
            ->where('character_id', $this->getOriginal('character_id', $this->getAttribute('character_id')));
    }
}
