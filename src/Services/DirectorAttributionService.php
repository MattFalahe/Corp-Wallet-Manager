<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Support\JournalFilters;
use CorpWalletManager\Services\EntityNameResolver;

/**
 * Best-effort attribution of corporation_account_withdrawal journal rows
 * to the director who most plausibly authorised them.
 *
 * The accountability gap this fills
 * ---------------------------------
 * When a wallet director moves ISK out of a corp wallet via EVE's in-game
 * UI (cash takeout, third-party payments, SRP payouts, etc.) CCP does NOT
 * stamp the acting director onto the resulting wallet journal row. The
 * row carries the corporation as first_party_id, the recipient as
 * second_party_id, and a description string — but no "actor" field. This
 * is one of the largest gaps in corp accountability surfaced by ESI:
 * directors can move significant ISK without a trail tying them to the
 * action. HR Manager wants to flag directors who are quiet on social
 * activity but keep moving large sums.
 *
 * Three signals are available, in confidence order:
 *
 *   1. context_id_type = 'character_id' — CCP populates this on a small
 *      sub-set of corporation_account_withdrawal entries (notably some
 *      SRP-style payouts) with the relevant character id. When present,
 *      this is the strongest signal we have (still a recipient, not
 *      strictly the acting director — but a usable starting point).
 *
 *   2. corporation_member_trackings.logon_date — every character SeAT has
 *      a member-tracking row for is recorded with their latest logon /
 *      logoff timestamps. We treat "logged in within 15 minutes before
 *      the journal date, and not yet logged off (or logoff_date older
 *      than logon_date, meaning still online)" as candidate territory.
 *      The candidate whose logon was MOST RECENT before the row is the
 *      one attributed; ties or empty buckets land in 'unattributable'.
 *      This is a temporal heuristic, not a confident attribution: any
 *      online member is a candidate, not just directors. Operators
 *      should treat the output as a starting point for investigation,
 *      not a verdict.
 *
 *   3. (Skipped) tax_receiver_id — for bounty_prize_corporation_tax and
 *      agent_mission_reward_corporation_tax CCP fills this with the
 *      corp's own id, not a director. No signal.
 *
 * Wrapped in try/catch + structured logging; on any failure returns a
 * zero-shape map so a dashboard render can never crash on this.
 */
class DirectorAttributionService
{
    /**
     * Window (minutes) before a journal row's date within which a tracked
     * character's logon_date is considered a candidate. Short enough that
     * an idle login from hours earlier doesn't get falsely attributed,
     * long enough to absorb tracking-table sync lag and the time it
     * takes a director to alt-tab into the wallet UI and authorise a
     * payment after logging in.
     */
    private const LOGON_WINDOW_MINUTES = 15;

    /** Cap on the unattributable rows sample so the payload stays small. */
    private const UNATTRIBUTABLE_SAMPLE_CAP = 20;

    /**
     * Best-effort director attribution for outgoing corp wallet activity.
     *
     * Pulls all corporation_account_withdrawal rows over the last $months
     * months at or above $minAmount (per-row, not aggregate), excludes
     * inter-division transfers, then tries to attribute each row to a
     * character via (1) context_id and (2) member-tracking logon
     * proximity in that order. Aggregates per attributed character with
     * a signal-source split so consumers can see how confident each row
     * was, and surfaces a sample of unattributable rows for manual
     * investigation.
     *
     * @return array{
     *   corporation_id:int,
     *   months:int,
     *   min_amount_threshold:float,
     *   directors:array<int,array{
     *     character_id:int,
     *     character_name:string,
     *     count:int,
     *     total_amount:float,
     *     signal_split:array{context_id:int,member_tracking:int},
     *     largest_single:float,
     *     first_attributed_date:string,
     *     last_attributed_date:string,
     *   }>,
     *   unattributable:array{
     *     count:int,
     *     total_amount:float,
     *     sample_rows:array<int,array{id:int,date:string,amount:float,second_party_id:int|null,description:string}>,
     *   },
     *   notes:string,
     * }
     */
    public function getDirectorAttribution(int $corporationId, int $months = 3, int $minAmount = 50_000_000): array
    {
        $months = max(1, $months);
        $minAmount = max(0, $minAmount);

        // Empty-shape return scaffold; reused on early exit and on failure
        // so callers can rely on a predictable structure even when
        // something goes wrong upstream.
        $emptyShape = [
            'corporation_id'       => $corporationId,
            'months'               => $months,
            'min_amount_threshold' => (float) $minAmount,
            'directors'            => [],
            'unattributable'       => [
                'count'        => 0,
                'total_amount' => 0.0,
                'sample_rows'  => [],
            ],
            'notes'                => $this->notesText(),
        ];

        try {
            $from = now()->subMonths($months);

            // Pull the candidate withdrawal rows. amount is signed
            // negative for outflows, so a threshold of 50M ISK means
            // amount <= -50_000_000. Inter-division transfers (which
            // carry first_party_id == second_party_id == corp_id) are
            // dropped here so they never enter the attribution pass.
            $rowsQuery = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->where('ref_type', 'corporation_account_withdrawal')
                ->where('amount', '<=', -1 * (float) $minAmount)
                ->where('date', '>=', $from);

            $rowsQuery = JournalFilters::excludeInternalTransfers($rowsQuery, $corporationId);

            $rows = $rowsQuery
                ->orderBy('date')
                ->get([
                    'id', 'date', 'amount', 'first_party_id', 'second_party_id',
                    'description', 'context_id', 'context_id_type',
                ]);

            if ($rows->isEmpty()) {
                return $emptyShape;
            }

            // Pre-load the member-tracking dataset for this corp once.
            // Most corps have under a couple hundred tracked members, so
            // pulling all of them into memory and scanning per-row in PHP
            // is far cheaper than N per-row SQL queries. The table is
            // optional — older SeAT installs may not have populated it,
            // in which case we silently skip the second signal.
            $trackedMembers = [];
            if (Schema::hasTable('corporation_member_trackings')) {
                $trackedMembers = DB::table('corporation_member_trackings')
                    ->where('corporation_id', $corporationId)
                    ->whereNotNull('logon_date')
                    ->get(['character_id', 'logon_date', 'logoff_date'])
                    ->map(function ($r) {
                        return [
                            'character_id' => (int) $r->character_id,
                            'logon'        => Carbon::parse($r->logon_date),
                            'logoff'       => $r->logoff_date ? Carbon::parse($r->logoff_date) : null,
                        ];
                    })
                    ->all();
            }

            // Per-character aggregation accumulator. Keyed by character_id.
            $attribution = [];

            // Unattributable rows: count + total, plus a small sample for
            // operator investigation. We materialise the whole sample
            // list and trim to the cap at the end so the FIRST 20 rows
            // chronologically are kept (callers usually want the oldest
            // first to investigate "when did this start?").
            $unattribAmount = 0.0;
            $unattribCount  = 0;
            $unattribSample = [];

            foreach ($rows as $row) {
                $absAmount = abs((float) $row->amount);
                $rowDate   = Carbon::parse($row->date);

                // Signal 1: context_id_type === 'character_id'. The
                // player-id sanity floor (90M) protects against CCP
                // sending NPC ids in this field for edge ref_types.
                $attributedId = null;
                $signalSource = null;

                if (($row->context_id_type ?? null) === 'character_id'
                    && $row->context_id !== null
                    && (int) $row->context_id >= 90_000_000) {
                    $attributedId = (int) $row->context_id;
                    $signalSource = 'context_id';
                }

                // Signal 2: member-tracking temporal proximity. Only
                // consulted when signal 1 missed AND the tracking table
                // is available. Candidate definition: logon_date falls
                // inside [rowDate - 15 min, rowDate], AND the character
                // is still online at rowDate (logoff_date is null OR
                // logoff_date < logon_date, which is how SeAT records
                // "currently logged in" — the previous session's logoff
                // is older than this session's logon).
                if ($attributedId === null && ! empty($trackedMembers)) {
                    $windowStart = $rowDate->copy()->subMinutes(self::LOGON_WINDOW_MINUTES);
                    $bestCandidate = null;
                    $bestLogon = null;
                    $tieFlag = false;

                    foreach ($trackedMembers as $member) {
                        /** @var Carbon $logon */
                        $logon = $member['logon'];
                        if ($logon->lt($windowStart) || $logon->gt($rowDate)) {
                            continue;
                        }

                        // Still-online test. logoff_date being null is
                        // an explicit "no logoff seen since this logon".
                        // logoff_date older than logon_date means the
                        // session that ended at logoff is BEFORE the
                        // current one started — i.e. still online.
                        /** @var Carbon|null $logoff */
                        $logoff = $member['logoff'];
                        if ($logoff !== null && $logoff->gte($logon) && $logoff->lt($rowDate)) {
                            continue;
                        }

                        if ($bestLogon === null || $logon->gt($bestLogon)) {
                            $bestCandidate = $member['character_id'];
                            $bestLogon = $logon;
                            $tieFlag = false;
                        } elseif ($logon->equalTo($bestLogon)) {
                            // Same-second logons are indistinguishable
                            // by this heuristic; flag as a tie and let
                            // the row drop to unattributable rather
                            // than guess.
                            $tieFlag = true;
                        }
                    }

                    if ($bestCandidate !== null && ! $tieFlag) {
                        $attributedId = $bestCandidate;
                        $signalSource = 'member_tracking';
                    }
                }

                if ($attributedId === null) {
                    $unattribAmount += $absAmount;
                    $unattribCount  += 1;
                    if (count($unattribSample) < self::UNATTRIBUTABLE_SAMPLE_CAP) {
                        $unattribSample[] = [
                            'id'              => (int) $row->id,
                            'date'            => (string) $row->date,
                            'amount'          => (float) $row->amount,
                            'second_party_id' => $row->second_party_id !== null ? (int) $row->second_party_id : null,
                            'description'    => (string) ($row->description ?? ''),
                        ];
                    }
                    continue;
                }

                if (! isset($attribution[$attributedId])) {
                    $attribution[$attributedId] = [
                        'character_id'   => $attributedId,
                        'character_name' => 'Unknown',
                        'count'          => 0,
                        'total_amount'   => 0.0,
                        'signal_split'   => ['context_id' => 0, 'member_tracking' => 0],
                        'largest_single' => 0.0,
                        'first_attributed_date' => (string) $row->date,
                        'last_attributed_date'  => (string) $row->date,
                    ];
                }

                $attribution[$attributedId]['count'] += 1;
                $attribution[$attributedId]['total_amount'] += $absAmount;
                $attribution[$attributedId]['signal_split'][$signalSource] += 1;
                if ($absAmount > $attribution[$attributedId]['largest_single']) {
                    $attribution[$attributedId]['largest_single'] = $absAmount;
                }
                // Rows are pulled in ascending date order so first_*_date
                // is already correct on first sight; only last_*_date
                // needs the running update.
                $attribution[$attributedId]['last_attributed_date'] = (string) $row->date;
            }

            // Resolve names for the attributed character ids in a single
            // batch. useEsi = false: this method can run inside a
            // synchronous HR Manager dashboard render and we don't want
            // an ESI roundtrip blocking the response. Locally-cached
            // sources (character_infos / corporation_infos /
            // alliance_infos / universe_names) still fire; ids those
            // miss stay as 'Unknown' until something else triggers an
            // ESI sync.
            if (! empty($attribution)) {
                $ids = array_keys($attribution);
                $resolved = app(EntityNameResolver::class)->resolve($ids, false);
                foreach ($attribution as $id => &$entry) {
                    $info = $resolved[$id] ?? null;
                    if ($info !== null && $info['name'] !== 'Unknown') {
                        $entry['character_name'] = (string) $info['name'];
                    }
                }
                unset($entry);
            }

            // Sort directors by total_amount desc; the loudest movers
            // float to the top of the HR review queue.
            $directors = array_values($attribution);
            usort($directors, fn ($a, $b) => $b['total_amount'] <=> $a['total_amount']);

            return [
                'corporation_id'       => $corporationId,
                'months'               => $months,
                'min_amount_threshold' => (float) $minAmount,
                'directors'            => $directors,
                'unattributable'       => [
                    'count'        => $unattribCount,
                    'total_amount' => $unattribAmount,
                    'sample_rows'  => $unattribSample,
                ],
                'notes'                => $this->notesText(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] DirectorAttributionService: attribution failed', [
                'corporation_id' => $corporationId,
                'months'         => $months,
                'min_amount'     => $minAmount,
                'error'          => $e->getMessage(),
            ]);
            return $emptyShape;
        }
    }

    /**
     * Caveats text returned in every payload so dashboards rendering
     * this data show the limitations next to the numbers. Single source
     * of truth so the empty-shape and the happy-path strings can never
     * drift apart.
     */
    private function notesText(): string
    {
        return 'Best-effort attribution. CCP does not structure the acting director on '
            . 'corporation_account_withdrawal journal rows, so this output combines two '
            . 'best-effort signals: context_id (when CCP populates it, strongest) and a '
            . '15-minute logon-proximity heuristic against corporation_member_trackings '
            . '(weaker, surfaces any online member, not just directors). Treat as a '
            . 'starting point for investigation rather than a verdict; ties and rows '
            . 'with no online member at the time land in the unattributable bucket.';
    }
}
