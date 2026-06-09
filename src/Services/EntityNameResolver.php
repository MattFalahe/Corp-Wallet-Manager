<?php

namespace CorpWalletManager\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve a batch of EVE entity ids (characters, corporations,
 * alliances, NPC corps, factions, ...) to human-readable names.
 *
 * Layered resolution, cheapest source first:
 *
 *   1. character_infos       — authed pilots + their cached affiliations
 *   2. corporation_infos     — player corps and NPC corps SeAT has seen
 *   3. alliance_infos        — alliances SeAT has seen (when the table
 *                              exists; older SeAT installs may not have
 *                              it as a top-level table)
 *   4. universe_names        — SeAT core's catch-all name cache, populated
 *                              over time by various ESI sync jobs
 *   5. ESI /universe/names/  — fallback for ids no local source knows.
 *                              Calls the public endpoint (no auth needed)
 *                              via SeAT's existing Universe\Names job,
 *                              which writes back into universe_names so
 *                              subsequent lookups for the same id are
 *                              free.
 *
 * The ESI fallback runs synchronously when $useEsi is true (default).
 * That keeps a settings-page picker honest — a recipient that no local
 * table knows still resolves on first load instead of showing "Unknown"
 * until the operator hits refresh.
 *
 * Returns an [id => ['name' => string, 'type' => string, 'source' =>
 * string]] map keyed by integer id. Ids that fail every resolution
 * source come back with name="Unknown", type="unknown", source="".
 * Callers can render those raw or with a placeholder; never throws.
 */
class EntityNameResolver
{
    public function resolve(array $ids, bool $useEsi = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));
        if (empty($ids)) {
            return [];
        }

        $result = [];

        // Source 1: character_infos
        $characterRows = DB::table('character_infos')
            ->whereIn('character_id', $ids)
            ->pluck('name', 'character_id');
        foreach ($characterRows as $id => $name) {
            $result[(int) $id] = ['name' => (string) $name, 'type' => 'character', 'source' => 'character_infos'];
        }

        // Source 2: corporation_infos (only ids not yet resolved)
        $remaining = $this->remainingIds($ids, $result);
        if (! empty($remaining)) {
            $corpRows = DB::table('corporation_infos')
                ->whereIn('corporation_id', $remaining)
                ->pluck('name', 'corporation_id');
            foreach ($corpRows as $id => $name) {
                $result[(int) $id] = ['name' => (string) $name, 'type' => 'corporation', 'source' => 'corporation_infos'];
            }
        }

        // Source 3: alliance_infos (defensive — older SeAT installs may
        // not have it as a standalone table).
        $remaining = $this->remainingIds($ids, $result);
        if (! empty($remaining) && Schema::hasTable('alliance_infos')) {
            $allianceRows = DB::table('alliance_infos')
                ->whereIn('alliance_id', $remaining)
                ->pluck('name', 'alliance_id');
            foreach ($allianceRows as $id => $name) {
                $result[(int) $id] = ['name' => (string) $name, 'type' => 'alliance', 'source' => 'alliance_infos'];
            }
        }

        // Source 4: universe_names (SeAT core's catch-all cache).
        $remaining = $this->remainingIds($ids, $result);
        if (! empty($remaining) && Schema::hasTable('universe_names')) {
            $universeRows = DB::table('universe_names')
                ->whereIn('entity_id', $remaining)
                ->get(['entity_id', 'name', 'category']);
            foreach ($universeRows as $row) {
                $result[(int) $row->entity_id] = [
                    'name'   => (string) $row->name,
                    'type'   => (string) $row->category,
                    'source' => 'universe_names',
                ];
            }
        }

        // Source 5: ESI /universe/names/ via SeAT's Universe\Names job.
        $remaining = $this->remainingIds($ids, $result);
        if (! empty($remaining) && $useEsi && class_exists(\Seat\Eveapi\Jobs\Universe\Names::class)) {
            try {
                \Seat\Eveapi\Jobs\Universe\Names::dispatchSync(collect($remaining));

                // Re-query universe_names to pick up everything the job
                // just wrote back. The job batches by 1000 ids and writes
                // each resolution as it arrives.
                if (Schema::hasTable('universe_names')) {
                    $freshRows = DB::table('universe_names')
                        ->whereIn('entity_id', $remaining)
                        ->get(['entity_id', 'name', 'category']);
                    foreach ($freshRows as $row) {
                        $result[(int) $row->entity_id] = [
                            'name'   => (string) $row->name,
                            'type'   => (string) $row->category,
                            'source' => 'esi',
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[Corp Wallet Manager] EntityNameResolver: ESI fallback failed', [
                    'count' => count($remaining),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fill in unknowns for ids that survived every source so callers
        // get a predictable shape.
        foreach ($ids as $id) {
            if (! isset($result[$id])) {
                $result[$id] = ['name' => 'Unknown', 'type' => 'unknown', 'source' => ''];
            }
        }

        return $result;
    }

    /**
     * Format an entity id as "Name [12345]" when a name is known, or
     * "Unknown [12345]" when it isn't. Used everywhere the suite wants
     * to surface the id alongside the resolved name for operator
     * traceability.
     */
    public static function formatNameWithId(?string $name, int $id): string
    {
        $clean = trim((string) $name);
        if ($clean === '' || strcasecmp($clean, 'Unknown') === 0) {
            return 'Unknown [' . $id . ']';
        }
        return $clean . ' [' . $id . ']';
    }

    /**
     * @param  array<int>  $ids
     * @param  array<int, array>  $resolved
     * @return array<int>
     */
    private function remainingIds(array $ids, array $resolved): array
    {
        return array_values(array_filter($ids, fn ($id) => ! isset($resolved[$id])));
    }
}
