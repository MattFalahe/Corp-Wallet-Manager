<?php

namespace CorpWalletManager\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Models\Settings;

/**
 * Resolves the corporation_id the current user is permitted to view,
 * and rejects requests that target corps the user has no character in.
 *
 * Before this trait existed, controllers trusted the `corporation_id`
 * request parameter unconditionally — any director could read another
 * corp's wallet data by passing its id. This trait is the authoritative
 * gate; routes keep their `can:` middleware for view-level permission
 * checks, and the trait adds corp-level authorization on top.
 */
trait AuthorizesCorporationAccess
{
    private const SESSION_KEY = 'corpwalletmanager.selected_corporation_id';

    private ?array $userCorporationIdsCache = null;

    /**
     * Resolve the corporation_id for the current request. Aborts 403 if the
     * user explicitly requested a corp they are not authorized for, or if
     * they are a non-admin with zero authorized corps (which would otherwise
     * cause callers' `if ($corpId) { ->where(...) }` pattern to run an
     * unscoped query and leak every corp's data).
     *
     * Returns null only for admins with no corp selected, preserving the
     * pre-existing "admin sees everything" behavior of the controllers.
     */
    protected function getCorporationId(Request $request): ?int
    {
        $requested = $request->get('corporation_id');

        if ($requested !== null && $requested !== '') {
            if (!is_numeric($requested) || (int) $requested <= 0) {
                abort(400, 'Invalid corporation_id.');
            }
            $corpId = (int) $requested;
            if (!$this->userCanAccessCorporation($corpId)) {
                abort(403, 'You are not authorized to view this corporation.');
            }
            return $corpId;
        }

        // Per-user session selection (set via /api/set-corporation). Takes
        // precedence over the global setting so two users on different corps
        // don't race through the shared corpwalletmanager_settings row.
        $sessionCorp = session(self::SESSION_KEY);
        if ($sessionCorp !== null && is_numeric($sessionCorp)) {
            $corpId = (int) $sessionCorp;
            if ($this->userCanAccessCorporation($corpId)) {
                return $corpId;
            }
            // Stale session (e.g. user lost corp access) — drop it.
            session()->forget(self::SESSION_KEY);
        }

        // Global admin-configured default, honored only if this user can see it.
        $setting = Settings::getSetting('selected_corporation_id');
        if ($setting !== null && $setting !== '' && is_numeric($setting)) {
            $corpId = (int) $setting;
            if ($this->userCanAccessCorporation($corpId)) {
                return $corpId;
            }
        }

        $userCorps = $this->userCorporationIds();
        if (!empty($userCorps)) {
            return $userCorps[0];
        }

        if (!$this->userIsAdmin()) {
            abort(403, 'No authorized corporation for your account.');
        }

        return null;
    }

    /**
     * Store the caller's chosen corporation in their session. Returns false if
     * they are not authorized to view that corp, true on success. Callers are
     * responsible for returning an HTTP response appropriate to the outcome.
     */
    protected function setSessionCorporation(int $corpId): bool
    {
        if (!$this->userCanAccessCorporation($corpId)) {
            return false;
        }
        session([self::SESSION_KEY => $corpId]);
        return true;
    }

    /**
     * Corporation IDs the authenticated user has a character in. Canonical
     * resolver for the plugin ecosystem (see project memory on
     * "Corp ID resolution for a user").
     */
    protected function userCorporationIds(): array
    {
        if ($this->userCorporationIdsCache !== null) {
            return $this->userCorporationIdsCache;
        }

        $user = auth()->user();
        if (!$user) {
            return $this->userCorporationIdsCache = [];
        }

        $corps = DB::table('refresh_tokens')
            ->where('user_id', $user->id)
            ->whereNull('refresh_tokens.deleted_at')
            ->join('character_affiliations', 'refresh_tokens.character_id', '=', 'character_affiliations.character_id')
            ->pluck('character_affiliations.corporation_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $this->userCorporationIdsCache = $corps;
    }

    protected function userCanAccessCorporation(int $corpId): bool
    {
        if ($this->userIsAdmin()) {
            return true;
        }
        return in_array($corpId, $this->userCorporationIds(), true);
    }

    protected function userIsAdmin(): bool
    {
        $user = auth()->user();
        return $user !== null && method_exists($user, 'isAdmin') && $user->isAdmin();
    }
}
