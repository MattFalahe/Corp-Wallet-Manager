<?php

namespace CorpWalletManager\Support;

/**
 * Filters for corporation_wallet_journals.
 *
 * Inter-division transfers (ISK moved between divisions of the same corp)
 * carry first_party_id == second_party_id == corporation_id. The receiving
 * division logs +X and the sending division logs -X, so the pair nets to
 * zero in balance but double-counts in any income/expense calculation
 * that looks at amount sign without considering the pair. These helpers
 * exclude such rows from income/expense queries and classification.
 *
 * Rows with a NULL party are kept (those are not internal transfers by
 * definition; the missing party usually means a system / NPC action that
 * CCP did not structure).
 */
class JournalFilters
{
    /**
     * Append a WHERE clause excluding inter-division transfers.
     *
     * When $corporationId is given the query is presumed to already be
     * scoped to that corp; the helper compares both party columns to the
     * literal corp id (cheaper). When $corporationId is null the helper
     * compares party columns to the row's own corporation_id column,
     * which is correct for queries spanning multiple corps (e.g. the
     * cross-corp large-transaction alert scan).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    public static function excludeInternalTransfers($query, ?int $corporationId = null)
    {
        if ($corporationId !== null) {
            return $query->where(function ($q) use ($corporationId) {
                $q->whereNull('first_party_id')
                  ->orWhereNull('second_party_id')
                  ->orWhere('first_party_id', '!=', $corporationId)
                  ->orWhere('second_party_id', '!=', $corporationId);
            });
        }

        return $query->where(function ($q) {
            $q->whereNull('first_party_id')
              ->orWhereNull('second_party_id')
              ->orWhereColumn('first_party_id', '!=', 'corporation_id')
              ->orWhereColumn('second_party_id', '!=', 'corporation_id');
        });
    }

    /**
     * Check a single journal row for inter-division transfer.
     *
     * Accepts any stdClass / model with corporation_id, first_party_id,
     * second_party_id properties.
     */
    public static function isInternalTransfer($row): bool
    {
        $corp   = $row->corporation_id ?? null;
        $first  = $row->first_party_id ?? null;
        $second = $row->second_party_id ?? null;

        if ($corp === null || $first === null || $second === null) {
            return false;
        }

        return (int) $first === (int) $corp && (int) $second === (int) $corp;
    }
}
