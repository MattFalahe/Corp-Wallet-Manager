<?php

namespace CorpWalletManager\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * Precomputed per-character / per-month corp wallet activity, one row per
 * (corporation_id, character_id, period). Maintained incrementally by
 * ComputeCharacterContributions and rebuilt by BackfillCharacterContributions.
 *
 * Backs the Top Contributors leaderboard and the contribution.* PluginBridge
 * capabilities consumed by HR Manager.
 */
class CharacterContribution extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_character_contributions';

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id',
        'character_id',
        'period',
        'ratting_amount',
        'ratting_count',
        'mission_amount',
        'mission_count',
        'tax_payment_amount',
        'tax_payment_count',
        'donation_voluntary_amount',
        'donation_voluntary_count',
        'industry_amount',
        'industry_count',
        'withdrawal_amount',
        'withdrawal_count',
        'total_contribution_amount',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id'            => 'integer',
        'character_id'              => 'integer',
        'ratting_amount'            => 'float',
        'ratting_count'             => 'integer',
        'mission_amount'            => 'float',
        'mission_count'             => 'integer',
        'tax_payment_amount'        => 'float',
        'tax_payment_count'         => 'integer',
        'donation_voluntary_amount' => 'float',
        'donation_voluntary_count'  => 'integer',
        'industry_amount'           => 'float',
        'industry_count'            => 'integer',
        'withdrawal_amount'         => 'float',
        'withdrawal_count'          => 'integer',
        'total_contribution_amount' => 'float',
    ];
}
