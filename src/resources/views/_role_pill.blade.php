{{--
    Resolved-role pill for CWM.

    Renders a stored role-mention value as a readable name (with the
    role's color dot when known) instead of a bare snowflake id. Used
    on the Settings page in the webhook list so operators can see at a
    glance which Discord role each webhook pings.

    Mirrors Structure Manager's _role_pill partial verbatim; keep the
    two implementations in sync per the suite role-picker pattern.

    Expects:
      $desc — result of DiscordRoleResolver::describeRoleMention(), or
              null for an empty / unset mention.
    Optional (inherited from the parent view):
      $roleProviderAvailable — bool, whether any role source is
              installed. Used to soften the unknown-id message when no
              source is configured (a raw id is then the expected
              manual format and isn't actually unresolved).
--}}
@php($rp_hasProvider = $roleProviderAvailable ?? false)
@if(empty($desc))
    <span class="text-muted" style="font-style:italic;font-size:0.85rem;">No mention</span>
@elseif(! empty($desc['known']))
    {{-- Resolved against an installed source — show name + colour. --}}
    <span class="cwm-role-pill" title="Discord role id {{ $desc['id'] }}">
        @if(! empty($desc['color']) && preg_match('/^#[0-9a-f]{6}$/i', $desc['color']))
            <span class="cwm-role-color-dot" style="background:{{ $desc['color'] }};"></span>
        @endif
        <span>{{ '@' . ($desc['name'] ?: ('Role ' . $desc['id'])) }}</span>
    </span>
@elseif(($desc['kind'] ?? '') === 'user')
    <span class="cwm-role-pill cwm-role-user" title="{{ $desc['raw'] }}">
        <i class="fas fa-user"></i>
        <span>User mention{{ ! empty($desc['id']) ? ' (' . $desc['id'] . ')' : '' }}</span>
    </span>
@elseif(($desc['kind'] ?? '') === 'role')
    @if($rp_hasProvider)
        <span class="cwm-role-pill cwm-role-unknown" title="{{ $desc['raw'] }}">
            <i class="fas fa-question-circle"></i>
            <span>Role {{ $desc['id'] }} (not in any installed list)</span>
        </span>
    @else
        <code style="font-size:0.75rem;">{{ $desc['raw'] }}</code>
    @endif
@else
    <span class="cwm-role-pill cwm-role-unknown" title="{{ $desc['raw'] }}">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Unrecognized (will not ping)</span>
    </span>
@endif
