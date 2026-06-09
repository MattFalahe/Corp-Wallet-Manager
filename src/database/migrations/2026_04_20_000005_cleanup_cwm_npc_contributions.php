<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-only cleanup of bad attributions written to the contribution
 * cache by the pre-fix classifier.
 *
 * Two classes of garbage to remove:
 *
 *   1. NPC ids (character_id < 90M). bounty_prizes and
 *      agent_mission_reward put the NPC pirate's faction / agent id
 *      into first_party_id on CORP wallets, not the ratting / running
 *      character; the pre-fix classifier attributed bounties to those
 *      NPCs (so a contributor like "Character 1000125" appears on the
 *      leaderboard — that id is in Caldari Navy / NPC range). The
 *      classifier now prefers context_id when context_id_type is
 *      'character_id'.
 *
 *   2. The corp's own id (character_id == corporation_id). Internal
 *      division transfers carry first_party == second_party ==
 *      corporation_id; the pre-fix corporation_account_withdrawal
 *      branch attributed those to the corp itself in the withdrawal
 *      bucket. JournalFilters::isInternalTransfer now skips them.
 *
 * Both classes are deleted from the cache so the leaderboard, HR
 * summaries, and reports stop surfacing them. The watermark is left
 * alone: future scans only ever produce valid attributions, so there
 * is no need to replay journals.
 */
class CleanupCwmNpcContributions extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return;
        }

        DB::table('corpwalletmanager_character_contributions')
            ->where('character_id', '<', 90000000)
            ->delete();

        DB::table('corpwalletmanager_character_contributions')
            ->whereColumn('character_id', 'corporation_id')
            ->delete();
    }

    public function down()
    {
        // Forward-only — there is no recovery, the deleted rows were
        // misattributions and the source journal data is intact.
    }
}
