<?php

namespace CorpWalletManager\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates per-character ratting income from SeAT's
 * corporation_wallet_journals table.
 *
 * Consumed by the Manager Core PluginBridge capabilities registered in
 * CorpWalletManagerServiceProvider when MC is installed, and available
 * for direct injection when callers prefer not to route through MC.
 *
 * CONTRACT NOTE: every method here is PER CHARACTER and must stay that
 * way. HR Manager calls these once per character and does its own alt
 * rollup, so making any method sum a player's alts internally would
 * multiply income by the alt count at the consumer (3 alts -> 3x) and
 * break the frozen cross-plugin contract. For a player-level total, add
 * a NEW method + a NEW capability (e.g. getPlayerIncome via
 * refresh_tokens.user_id) and update HR in lockstep. Do not change these.
 */
class RattingIncomeService
{
    private const RAT_REF_TYPES = [
        'bounty_prizes',
        'bounty_prize',
        'agent_mission_reward',
        'agent_mission_time_bonus_reward',
    ];

    private const CACHE_TTL_SECONDS = 60;

    public function getCharacterIncome(int $characterId, int $corporationId, int $months = 6): ?object
    {
        return Cache::remember(
            $this->cacheKey('income', $characterId, $corporationId, $months),
            self::CACHE_TTL_SECONDS,
            fn () => $this->baseQuery($characterId, $corporationId, $months)
                ->selectRaw('SUM(amount) as total_income, COUNT(*) as transaction_count, MIN(date) as first_activity, MAX(date) as last_activity')
                ->first()
        );
    }

    public function getCharacterMonthly(int $characterId, int $corporationId, int $months = 6)
    {
        return Cache::remember(
            $this->cacheKey('monthly', $characterId, $corporationId, $months),
            self::CACHE_TTL_SECONDS,
            fn () => $this->baseQuery($characterId, $corporationId, $months)
                ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total_income, COUNT(*) as transaction_count")
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->get()
        );
    }

    public function getCharacterBreakdown(int $characterId, int $corporationId, int $months = 6)
    {
        return Cache::remember(
            $this->cacheKey('breakdown', $characterId, $corporationId, $months),
            self::CACHE_TTL_SECONDS,
            fn () => $this->baseQuery($characterId, $corporationId, $months)
                ->selectRaw('ref_type, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('ref_type')
                ->get()
        );
    }

    private function baseQuery(int $characterId, int $corporationId, int $months)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('second_party_id', $characterId)
            ->whereIn('ref_type', self::RAT_REF_TYPES)
            ->where('date', '>=', now()->subMonths($months));
    }

    private function cacheKey(string $kind, int $characterId, int $corporationId, int $months): string
    {
        return sprintf('cwm:ratting:%s:%d:%d:%d', $kind, $corporationId, $characterId, $months);
    }
}
