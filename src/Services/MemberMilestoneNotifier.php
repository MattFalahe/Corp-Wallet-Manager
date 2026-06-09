<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\CharacterContribution;
use CorpWalletManager\Models\MemberMilestoneState;

/**
 * Publishes member.* edge-transition events to Manager Core's
 * EventBus when notable thresholds cross. HR Manager (and any other
 * consumer) subscribes via MC and reacts on the transition, instead
 * of polling the contribution cache repeatedly.
 *
 * Three events:
 *
 *   member.contribution.stalled
 *     Fires once when a member crosses from "active in any of the
 *     last 3 months" to "zero contribution for >= 2 consecutive
 *     months ending now". Cleared back to null when the member
 *     contributes again, so the next stall (after a recovery)
 *     emits properly.
 *
 *   member.contribution.milestone
 *     Fires when lifetime total_contribution crosses a configured
 *     ladder rung (1B, 5B, 10B, 25B, 50B, 100B ISK). Tracks the
 *     highest rung already published so each crossing emits exactly
 *     once.
 *
 *   member.tax.compliance_dropped
 *     Fires when MM tax compliance over the last 3 months falls
 *     below 50% AND was above 50% on the prior run (edge transition,
 *     not continuous). When MM is not installed this event never
 *     fires.
 *
 * Every publish is guarded by class_exists(\ManagerCore\Topics::class)
 * so CWM running standalone is a complete no-op: state is still
 * tracked locally (cheap; useful when MC gets installed later) but no
 * outbound notification happens.
 *
 * Called once per ComputeCharacterContributions run after the watermark
 * advances. The notifier scans every (corp, character) in the cache for
 * the trailing 12 months, evaluates transitions, and updates state.
 * Designed to be cheap per-run because most members hold steady; the
 * inner loop is a single state lookup + a couple of cache reads per
 * member.
 */
class MemberMilestoneNotifier
{
    /** ISK thresholds for the lifetime milestone event (ascending). */
    private const MILESTONE_LADDER = [
        1_000_000_000,    // 1B
        5_000_000_000,    // 5B
        10_000_000_000,   // 10B
        25_000_000_000,   // 25B
        50_000_000_000,   // 50B
        100_000_000_000,  // 100B
    ];

    /** Compliance floor (%) for the tax-compliance-dropped event. */
    private const COMPLIANCE_FLOOR_PCT = 50.0;

    /** How many consecutive zero-contribution months trigger a stall. */
    private const STALL_THRESHOLD_MONTHS = 2;

    private ContributionService $contributions;

    public function __construct(ContributionService $contributions)
    {
        $this->contributions = $contributions;
    }

    /**
     * Evaluate state transitions for every (corp, character) the
     * contribution cache knows about in the trailing 12 months, fire
     * edge-transition events, and update state. Returns a summary of
     * what was published.
     *
     * @return array{stalled:int, milestone:int, compliance_dropped:int, errors:int}
     */
    public function runSweep(): array
    {
        $summary = ['stalled' => 0, 'milestone' => 0, 'compliance_dropped' => 0, 'errors' => 0];

        try {
            $twelveMonthsAgo = Carbon::now()->subMonths(12)->format('Y-m');

            // Pull the distinct (corp, character) set seen in the cache
            // recently. Filtering NPCs / corp self-attribution with the
            // same defensive guards the leaderboard uses.
            $cohort = CharacterContribution::query()
                ->where('period', '>=', $twelveMonthsAgo)
                ->where('character_id', '>=', 90_000_000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->select('corporation_id', 'character_id')
                ->distinct()
                ->get();

            foreach ($cohort as $row) {
                try {
                    $this->evaluateMember(
                        (int) $row->corporation_id,
                        (int) $row->character_id,
                        $summary
                    );
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    Log::warning('[Corp Wallet Manager] MemberMilestoneNotifier: per-member evaluation failed', [
                        'corporation_id' => $row->corporation_id,
                        'character_id'   => $row->character_id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $summary['errors']++;
            Log::error('[Corp Wallet Manager] MemberMilestoneNotifier sweep failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $summary;
    }

    /**
     * Evaluate the three events for one (corp, character) and update
     * the row in corpwalletmanager_member_milestone_state. Increments
     * the appropriate counter in $summary for each event published.
     */
    private function evaluateMember(int $corporationId, int $characterId, array &$summary): void
    {
        $state = MemberMilestoneState::firstOrNew([
            'corporation_id' => $corporationId,
            'character_id'   => $characterId,
        ]);
        $dirty = false;

        // ---- Event 1: member.contribution.stalled ----
        $gaps = $this->contributions->getActivityGaps($characterId, $corporationId, 12);
        $hasRecentStall = $this->hasTrailingStall($gaps['gaps'] ?? []);
        $currentPeriod = Carbon::now()->format('Y-m');

        if ($hasRecentStall && $state->last_stalled_period !== $currentPeriod) {
            // Edge transition: stall now, no stall last period (or never
            // recorded). Publish + remember.
            $this->publish('member.contribution.stalled', [
                'corporation_id'       => $corporationId,
                'character_id'         => $characterId,
                'months_analyzed'      => $gaps['months_analyzed'] ?? 12,
                'longest_gap_months'   => $gaps['longest_gap_months'] ?? 0,
                'last_active_period'   => $gaps['last_active_period'],
                'detected_at'          => Carbon::now()->toIso8601String(),
            ]);
            $state->last_stalled_period = $currentPeriod;
            $dirty = true;
            $summary['stalled']++;
        } elseif (! $hasRecentStall && $state->last_stalled_period !== null) {
            // Recovery — clear so the next stall fires.
            $state->last_stalled_period = null;
            $dirty = true;
        }

        // ---- Event 2: member.contribution.milestone ----
        $lifetime = $this->contributions->getLifetimeSummary($characterId, $corporationId);
        $lifetimeTotal = (float) ($lifetime['lifetime_total_contributed'] ?? 0);
        $highestRecorded = (float) ($state->highest_milestone_isk ?? 0);

        foreach (self::MILESTONE_LADDER as $rung) {
            if ($lifetimeTotal >= $rung && $highestRecorded < $rung) {
                $this->publish('member.contribution.milestone', [
                    'corporation_id'   => $corporationId,
                    'character_id'     => $characterId,
                    'milestone_isk'    => (float) $rung,
                    'lifetime_total'   => $lifetimeTotal,
                    'months_active'    => $lifetime['months_active'] ?? null,
                    'detected_at'      => Carbon::now()->toIso8601String(),
                ]);
                $state->highest_milestone_isk = (float) $rung;
                $highestRecorded = (float) $rung;
                $dirty = true;
                $summary['milestone']++;
            }
        }

        // ---- Event 3: member.tax.compliance_dropped (MM only) ----
        $compliance = $this->contributions->getCharacterTaxCompliance($characterId, $corporationId, 3);
        if ($compliance !== null) {
            $compliancePct = (float) ($compliance['overall_compliance_pct'] ?? 100);
            $totalOwed = (float) ($compliance['total_owed'] ?? 0);

            // Only meaningful when there was actually tax owed in the
            // window; a "100%" reading on zero-owed isn't interesting.
            if ($totalOwed > 0 && $compliancePct < self::COMPLIANCE_FLOOR_PCT
                && $state->last_compliance_drop_period !== $currentPeriod) {
                $this->publish('member.tax.compliance_dropped', [
                    'corporation_id'        => $corporationId,
                    'character_id'          => $characterId,
                    'compliance_pct'        => $compliancePct,
                    'consecutive_overdue'   => $compliance['consecutive_overdue'] ?? 0,
                    'total_owed'            => $totalOwed,
                    'total_paid'            => (float) ($compliance['total_paid'] ?? 0),
                    'floor_pct'             => self::COMPLIANCE_FLOOR_PCT,
                    'detected_at'           => Carbon::now()->toIso8601String(),
                ]);
                $state->last_compliance_drop_period = $currentPeriod;
                $dirty = true;
                $summary['compliance_dropped']++;
            } elseif ($compliancePct >= self::COMPLIANCE_FLOOR_PCT
                && $state->last_compliance_drop_period !== null) {
                // Recovery above floor — clear so the next drop fires.
                $state->last_compliance_drop_period = null;
                $dirty = true;
            }
        }

        if ($dirty) {
            $state->save();
        }
    }

    /**
     * Stall = the most recent STALL_THRESHOLD_MONTHS entries in the
     * gap series (which arrives newest-first) are all inactive. Lets
     * us emit on the second consecutive zero month rather than
     * waiting for a longer gap to accumulate.
     *
     * @param array<int, array{period: string, active: bool}> $gaps
     */
    private function hasTrailingStall(array $gaps): bool
    {
        if (count($gaps) < self::STALL_THRESHOLD_MONTHS) {
            return false;
        }
        for ($i = 0; $i < self::STALL_THRESHOLD_MONTHS; $i++) {
            if (! empty($gaps[$i]['active'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Publish to Manager Core's EventBus. class_exists-guarded so CWM
     * standalone is a complete no-op (state still updates locally,
     * cheap, and the events fire automatically the moment MC is
     * installed without rerunning anything).
     */
    private function publish(string $topic, array $payload): void
    {
        if (! class_exists(\ManagerCore\Topics::class)) {
            return;
        }
        try {
            \ManagerCore\Topics::publish($topic, $payload);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] MemberMilestoneNotifier publish failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
