@extends('web::layouts.grids.12')

@section('title', trans('corpwalletmanager::help.help_documentation'))
@section('page_header', trans('corpwalletmanager::help.help_documentation'))

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/corp-wallet-manager/css/corp-wallet-manager.css') }}?v=3">
<style>
    /* Page-specific overrides for the help index. Most chrome lives in canonical CSS. */

    /* Plugin info hero block (gradient background card) */
    .plugin-info {
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(102, 126, 234, 0.3);
    }

    .plugin-info .info-row {
        color: #9ca3af;
        margin: 5px 0;
    }

    .plugin-info .author {
        color: #667eea;
        margin: 10px 0;
    }

    /* Plugin link badges (alt to .quick-link) */
    .plugin-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .plugin-link {
        background: rgba(102, 126, 234, 0.1);
        padding: 10px;
        border-radius: 5px;
        border: 1px solid rgba(102, 126, 234, 0.3);
        color: #667eea;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .plugin-link:hover {
        background: rgba(102, 126, 234, 0.2);
        color: #8da4ff;
        text-decoration: none;
        transform: translateX(5px);
    }

    /* Model comparison cards (basic vs ARIMA) */
    .model-comparison {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .model-card {
        background: rgba(102, 126, 234, 0.1);
        padding: 20px;
        border-radius: 10px;
        border: 2px solid rgba(102, 126, 234, 0.3);
    }

    .model-card h5 {
        color: #667eea !important;
        margin-bottom: 15px;
        font-size: 1.2rem;
    }

    .model-card ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .model-card ul li {
        padding: 5px 0;
        color: #d1d5db !important;
    }

    .model-card ul li i {
        color: #1cc88a;
        margin-right: 8px;
    }

    /* Tab-explanation callout (used in director-tabs section) */
    .tab-explanation {
        background: rgba(102, 126, 234, 0.05);
        padding: 15px;
        border-left: 3px solid #667eea;
        margin: 15px 0;
        border-radius: 5px;
    }

    .tab-explanation h5 {
        color: #667eea !important;
        margin-bottom: 10px;
    }

    /* Command-list (CLI command examples) */
    .command-list {
        margin: 20px 0;
    }

    .command-list code {
        display: block;
        margin: 10px 0 5px;
        padding: 10px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 5px;
        color: #fbbf24 !important;
    }

    /* Contrast fixes for tinted callout boxes.
       The canonical .help-card h4 / h5 / .text-muted defaults to #9ca3af which
       is hard to read on the 15%-tint backgrounds of .info-box / .warning-box
       / .success-box / .purple-box. Override with chained-class selectors so
       they win the cascade against Bootstrap's .text-muted. Pattern mirrors
       Structure Manager + Mining Manager (per feedback_help_docs_visual_design). */
    .help-card .info-box h4,
    .help-card .info-box h5,
    .help-card .warning-box h4,
    .help-card .warning-box h5,
    .help-card .success-box h4,
    .help-card .success-box h5,
    .help-card .purple-box h4,
    .help-card .purple-box h5 {
        color: #e2e8f0 !important;
    }
    .help-card .info-box .text-muted,
    .help-card .warning-box .text-muted,
    .help-card .success-box .text-muted,
    .help-card .purple-box .text-muted {
        color: #cbd5e1 !important;
    }

    /* Per the same fix pattern: bare `.feature-item i` targets ALL icons inside
       a feature card, including nested badge icons. Use a direct-child combinator
       so only the top-level icon gets the 2rem indigo treatment. */
    .help-card .feature-item > i {
        font-size: 2rem;
    }
    .help-card .feature-item .badge i,
    .help-card .feature-item .v3-badge i {
        font-size: inherit;
        color: inherit;
    }

    /* Green-tinted callout used by the "What's New in vX.Y.Z" panel inside the
       Overview tab. Mirrors the pattern shipped in Structure Manager + Mining
       Manager so version highlights read the same way across the suite. Sits
       between the Welcome and "What is..." cards on the Overview tab. */
    .whats-new-box {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.15) 0%, rgba(32, 201, 151, 0.1) 100%);
        border-left: 4px solid #28a745;
        border-radius: 8px;
        padding: 15px 20px;
        margin: 20px 0;
        color: #d1d5db !important;
    }
    .whats-new-box h3,
    .whats-new-box h4,
    .whats-new-box h5 {
        color: #51cf66 !important;
        margin-top: 0;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .whats-new-box h3 i,
    .whats-new-box h4 i,
    .whats-new-box h5 i {
        color: #51cf66 !important;
    }
    .whats-new-box ul {
        color: #d1d5db !important;
    }
    .whats-new-box code {
        background: rgba(0, 0, 0, 0.25);
        color: #93f7b8;
    }
</style>
@endpush

@section('content')
<div class="corp-wallet-wrapper">
<div class="help-wrapper">
    {{-- Sidebar Navigation --}}
    <div class="help-sidebar">
        <div class="card card-dark">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-compass"></i>
                    Navigation
                </h3>
            </div>
            <div class="card-body p-0">
                <ul class="nav nav-pills flex-column help-nav">
                    {{-- v3.0.0: the Overview entry consolidates Plugin Info,
                         Version Status, Welcome, What's New (.whats-new-box
                         green callout), What Is CWM, and Key Features into
                         one long scroll page. Mirrors the MM/MC pattern:
                         What's New lives INSIDE Overview, not as its own
                         sidebar entry. --}}
                    <li class="nav-item">
                        <a href="#" class="nav-link active" data-section="overview">
                            <i class="fas fa-info-circle"></i>
                            {{ trans('corpwalletmanager::help.nav_overview') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="getting-started">
                            <i class="fas fa-rocket"></i>
                            {{ trans('corpwalletmanager::help.getting_started') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="features">
                            <i class="fas fa-tasks"></i>
                            {{ trans('corpwalletmanager::help.features') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="director-tabs">
                            <i class="fas fa-columns"></i>
                            {{ trans('corpwalletmanager::help.director_tabs') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="dashboard">
                            <i class="fas fa-chart-line"></i>
                            {{ trans('corpwalletmanager::help.dashboard') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="contributions">
                            <i class="fas fa-users"></i>
                            {{ trans('corpwalletmanager::help.contributions') }}
                            <span class="v3-badge-nav">v3.0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="predictions">
                            <i class="fas fa-bullseye"></i>
                            {{ trans('corpwalletmanager::help.predictions') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="reports">
                            <i class="fas fa-file-alt"></i>
                            {{ trans('corpwalletmanager::help.reports') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="analytics">
                            <i class="fas fa-chart-bar"></i>
                            {{ trans('corpwalletmanager::help.analytics') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="webhooks">
                            <i class="fab fa-discord"></i>
                            {{ trans('corpwalletmanager::help.webhooks') }}
                            <span class="v3-badge-nav">v3.0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="diagnostic">
                            <i class="fas fa-stethoscope"></i>
                            {{ trans('corpwalletmanager::help.diagnostic') }}
                            <span class="v3-badge-nav">v3.0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="integrations">
                            <i class="fas fa-plug"></i>
                            {{ trans('corpwalletmanager::help.integrations') }}
                            <span class="v3-badge-nav">v3.0</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="commands">
                            <i class="fas fa-terminal"></i>
                            {{ trans('corpwalletmanager::help.commands') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="settings">
                            <i class="fas fa-cog"></i>
                            {{ trans('corpwalletmanager::help.settings') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="member-view">
                            <i class="fas fa-user"></i>
                            {{ trans('corpwalletmanager::help.member_view') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="faq">
                            <i class="fas fa-question-circle"></i>
                            {{ trans('corpwalletmanager::help.faq') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link" data-section="troubleshooting">
                            <i class="fas fa-wrench"></i>
                            {{ trans('corpwalletmanager::help.troubleshooting') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="help-content">

        {{-- Search Box --}}
        <div class="search-box">
            <input type="text"
                   id="helpSearch"
                   placeholder="{{ trans('corpwalletmanager::help.search_placeholder') }}"
                   class="form-control">
            <i class="fas fa-search"></i>
        </div>

        {{-- Overview: single help-section that scrolls through Plugin Info
             (with four GitHub chips embedded), Version Status, Welcome,
             What's New (green callout), What is CWM, and Key Features as
             a sequence of help-card panels plus one .whats-new-box. Mirrors
             the Mining Manager / Manager Core pattern verbatim, including
             What's New living inside Overview rather than as its own
             sidebar entry. --}}
        <div id="overview" class="help-section active">
            {{-- Plugin Information panel --}}
            <div class="help-card">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    {{ trans('corpwalletmanager::help.plugin_info_title') }}
                </h3>
                <p>
                    <strong>{{ trans('corpwalletmanager::help.version') }}:</strong>
                    <span class="badge badge-secondary" style="font-size: 0.9rem; vertical-align: middle;">
                        v{{ $installedVersion ?? '3.0.0' }}
                    </span>
                    <span class="badge" style="background:#667eea; color:#fff; font-size: 0.85rem; vertical-align: middle;">
                        {{ $releaseTag ?? 'v3.0.0' }} &middot; {{ $releaseCodename ?? 'The Ecosystem Era' }}
                    </span>
                    <span class="badge" style="background:#3a4049; color:#cbd5e1; font-size: 0.85rem; vertical-align: middle;">SeAT 5.0</span>
                </p>
                <p>
                    <strong>{{ trans('corpwalletmanager::help.license') }}:</strong> GPL-2.0-or-later
                </p>
                <p>
                    <i class="fas fa-user"></i> <strong>{{ trans('corpwalletmanager::help.author') }}:</strong> Matt Falahe<br>
                    <i class="fas fa-envelope"></i> <a href="mailto:mattfalahe@gmail.com" style="color: #667eea;">mattfalahe@gmail.com</a>
                </p>

                {{-- Four canonical repo chips. Same shape + icons MM and MC use
                     so the Overview tab feels identical across the suite. --}}
                <div class="quick-links" style="margin-top: 15px;">
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                        <i class="fas fa-code-branch" style="font-size: 1rem; margin-bottom: 4px;"></i>
                        {{ trans('corpwalletmanager::help.github_repo') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/blob/main/CHANGELOG.md" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                        <i class="fas fa-list" style="font-size: 1rem; margin-bottom: 4px;"></i>
                        {{ trans('corpwalletmanager::help.changelog') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/issues" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                        <i class="fas fa-bug" style="font-size: 1rem; margin-bottom: 4px;"></i>
                        {{ trans('corpwalletmanager::help.report_issues') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/blob/main/README.md" class="quick-link" target="_blank" rel="noopener" style="padding: 10px;">
                        <i class="fas fa-book" style="font-size: 1rem; margin-bottom: 4px;"></i>
                        {{ trans('corpwalletmanager::help.readme') }}
                    </a>
                </div>

                <div class="success-box" style="margin-top: 20px;">
                    <i class="fas fa-heart"></i>
                    <div>
                        <strong>{{ trans('corpwalletmanager::help.support_project') }}:</strong>
                        {!! trans('corpwalletmanager::help.support_list') !!}
                    </div>
                </div>
            </div>

            {{-- Version Status panel — mirrors Manager Core's layout.
                 Shape comes from EcosystemVersionChecker::getStatusForPlugin
                 when MC is installed; otherwise the controller builds a
                 minimal local shape with the same field names. The status
                 badge, Installed / Latest release row, and the source hint
                 line are intentionally the same across the suite so the
                 reader's eye moves the same way on every plugin. --}}
            @php
                $vs = $versionStatus ?? ['current' => '?', 'current_source' => 'config', 'is_dev_branch' => false, 'latest' => null, 'status' => 'unknown', 'message' => '', 'release_url' => null];
                $statusBadgeClass = [
                    'current'    => 'badge-success',
                    'outdated'   => 'badge-warning',
                    'ahead'      => 'badge-info',
                    'dev_branch' => 'badge-info',
                    'unreleased' => 'badge-secondary',
                    'unknown'    => 'badge-secondary',
                    'offline'    => 'badge-secondary',
                ][$vs['status']] ?? 'badge-secondary';
                $statusLabel = [
                    'current'    => '✓ Up to date',
                    'outdated'   => '⚠ Update available',
                    'ahead'      => '🚀 Pre-release',
                    'dev_branch' => '🌱 Development branch',
                    'unreleased' => '🚀 Coming soon',
                    'unknown'    => '- Unable to check',
                    'offline'    => '- Not installed',
                ][$vs['status']] ?? '- Unknown';
                $installedDisplay = ($vs['is_dev_branch'] || empty($vs['current'])) ? ($vs['current'] ?? '?') : ('v' . $vs['current']);
                $sourceHint = ($vs['current_source'] ?? 'config') === 'composer'
                    ? "resolved via Composer's installed.json"
                    : 'resolved via fallback (Composer metadata unavailable)';
            @endphp
            <div class="help-card">
                <h3><i class="fas fa-tag"></i> {{ trans('corpwalletmanager::help.version_status_title') }}</h3>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin: 0.5rem 0;">
                    <div>
                        <strong>Installed:</strong>
                        <span class="badge badge-secondary" style="font-size: 0.9rem;" title="{{ $sourceHint }}">
                            {{ $installedDisplay }}
                        </span>
                    </div>
                    <div>
                        <strong>Latest release:</strong>
                        @if(! empty($vs['latest']))
                            <span class="badge badge-secondary" style="font-size: 0.9rem;">v{{ $vs['latest'] }}</span>
                        @else
                            <span class="badge badge-secondary" style="font-size: 0.9rem;">unknown</span>
                        @endif
                    </div>
                    <div>
                        <span class="badge {{ $statusBadgeClass }}" style="font-size: 0.9rem;">{{ $statusLabel }}</span>
                    </div>
                    @if(! empty($vs['release_url']))
                        <div>
                            <a href="{{ $vs['release_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-cwm-primary">
                                <i class="fas fa-external-link-alt"></i> View release notes
                            </a>
                        </div>
                    @endif
                </div>
                @if(! empty($vs['message']))
                    <small class="text-muted">{{ $vs['message'] }}</small>
                @endif
                <small class="text-muted" style="display: block; margin-top: 0.4rem; font-size: 0.75rem;">
                    <i class="fas fa-info-circle"></i>
                    Installed version {{ $sourceHint }}. Latest checked via Packagist's public API (6h cache, safe on outages). Use the <strong>Refresh Versions</strong> button on the Plugin Bridge page to flush the cache on demand.
                </small>
            </div>

            {{-- Welcome panel: friendly prose intro distinct from the deeper
                 "What is CWM?" panel below. Tone: greeting the newcomer,
                 framing the value, naming the suite siblings. --}}
            <div class="help-card">
                <h3>
                    <i class="fas fa-hand-sparkles"></i>
                    {{ trans('corpwalletmanager::help.welcome_title') }}
                </h3>
                {!! trans('corpwalletmanager::help.welcome_body') !!}
            </div>

            {{-- What's New in v3.0.0 ============================================
                 Green-tinted callout placed right after the Welcome card so the
                 release highlights land as the first thing operators read after
                 the introduction. Mirrors the MM/MC pattern: What's New lives
                 INSIDE Overview, not as a separate sidebar entry. =============== --}}
            <div class="whats-new-box">
                <h3>
                    <i class="fas fa-sparkles"></i>
                    {{ trans('corpwalletmanager::help.whats_new_title') }}
                </h3>
                <p>{!! trans('corpwalletmanager::help.whats_new_intro') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_ecosystem') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_ecosystem_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_analytics') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_analytics_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_scheduled') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_scheduled_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_reports') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_reports_body') !!}</p>

                <h5 style="margin-top:15px;">{{ trans('corpwalletmanager::help.whats_new_report_table_title') }}</h5>
                {!! trans('corpwalletmanager::help.whats_new_report_table') !!}

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_annual') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_annual_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_anomaly') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_anomaly_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_hr') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_hr_body') !!}</p>

                <h4>{!! trans('corpwalletmanager::help.whats_new_section_fixes') !!}</h4>
                <p>{!! trans('corpwalletmanager::help.whats_new_section_fixes_body') !!}</p>

                <p style="margin-top:12px; margin-bottom:0; font-size:0.88rem; color:#9aa3b3;">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ trans('corpwalletmanager::help.upgrade_notes_title') }}:</strong>
                    {!! trans('corpwalletmanager::help.upgrade_notes_body') !!}
                </p>
            </div>

            {{-- What is Corp Wallet Manager? Deeper, structured explanation
                 distinct from the prose Welcome above. Covers the data
                 model, cross-plugin integration, permissions, and the
                 diagnostic surface. --}}
            <div class="help-card">
                <h3>
                    <i class="fas fa-wallet"></i>
                    {{ trans('corpwalletmanager::help.what_is_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.what_is_desc') }}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_data_model_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.what_is_data_model_body') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_data_freshness_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.what_is_data_freshness_body') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_cross_plugin_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.what_is_cross_plugin_body') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_permissions_title') }}</h4>
                {!! trans('corpwalletmanager::help.what_is_permissions_body') !!}

                <h4>{{ trans('corpwalletmanager::help.what_is_member_surface_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.what_is_member_surface_body') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_diagnostic_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.what_is_diagnostic_body') !!}</p>

                <div class="info-box">
                    <i class="fas fa-lightbulb"></i>
                    <strong>{{ trans('corpwalletmanager::help.what_is_key_benefit_title') }}:</strong>
                    {{ trans('corpwalletmanager::help.what_is_key_benefit_body') }}
                </div>
            </div>

            {{-- Key Features panel: a trim summary, NOT the deep features
                 section below. Pulled from the v3.0.0 highlights but
                 condensed to one paragraph per item. --}}
            <div class="help-card">
                <h3>
                    <i class="fas fa-star"></i>
                    {{ trans('corpwalletmanager::help.key_features_title') }}
                </h3>

                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_top_contributors_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_top_contributors_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-pie"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_profit_attribution_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_profit_attribution_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-receipt"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_expense_attribution_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_expense_attribution_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-balance-scale"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_alliance_tax_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_alliance_tax_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fab fa-discord"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_webhooks_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_webhooks_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-bell"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_alerts_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_alerts_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-calendar-alt"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_scheduled_reports_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_scheduled_reports_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-export"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_data_export_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_data_export_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-stethoscope"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_diagnostic_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_diagnostic_body') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-user-shield"></i>
                        <h5>{{ trans('corpwalletmanager::help.kf_member_surface_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.kf_member_surface_body') }}</p>
                    </div>
                </div>
            </div>

        </div>

        {{-- Getting Started Section --}}
        <div id="getting-started" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-rocket"></i>
                    {{ trans('corpwalletmanager::help.getting_started_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.getting_started_desc') }}</p>

                <h4>{{ trans('corpwalletmanager::help.quick_start_title') }}</h4>
                <ol class="step-by-step">
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.step1_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.step1_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.step2_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.step2_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.step3_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.step3_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.step4_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.step4_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.step5_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.step5_desc') }}
                    </li>
                </ol>

                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <strong>{{ trans('corpwalletmanager::help.success_tip') }}:</strong>
                    {{ trans('corpwalletmanager::help.success_desc') }}
                </div>
            </div>
        </div>

        {{-- Features Section --}}
        <div id="features" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-star"></i>
                    {{ trans('corpwalletmanager::help.features_overview') }}
                </h3>

                <h4>{{ trans('corpwalletmanager::help.balance_tracking_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.balance_tracking_desc') }}</p>
                {!! trans('corpwalletmanager::help.balance_features') !!}

                <h4>{{ trans('corpwalletmanager::help.predictions_system_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.predictions_system_desc') }}</p>
                
                <div class="model-comparison">
                    <div class="model-card">
                        <h5><i class="fas fa-chart-line"></i> {{ trans('corpwalletmanager::help.basic_model_title') }}</h5>
                        <p style="color: #9ca3af; margin-bottom: 15px;">{{ trans('corpwalletmanager::help.basic_model_subtitle') }}</p>
                        {!! trans('corpwalletmanager::help.basic_model_features') !!}
                    </div>
                    <div class="model-card">
                        <h5><i class="fas fa-brain"></i> {{ trans('corpwalletmanager::help.arima_model_title') }}</h5>
                        <p style="color: #9ca3af; margin-bottom: 15px;">{{ trans('corpwalletmanager::help.arima_model_subtitle') }}</p>
                        {!! trans('corpwalletmanager::help.arima_model_features') !!}
                    </div>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ trans('corpwalletmanager::help.model_migration') }}:</strong>
                    {{ trans('corpwalletmanager::help.model_migration_desc') }}
                </div>

                <h4>{{ trans('corpwalletmanager::help.division_management_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.division_management_desc') }}</p>

                <h4>{{ trans('corpwalletmanager::help.advanced_analytics_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.advanced_analytics_desc') }}</p>
                {!! trans('corpwalletmanager::help.analytics_features') !!}

                <div class="info-box">
                    <i class="fas fa-lightbulb"></i>
                    <strong>{{ trans('corpwalletmanager::help.pro_tip') }}:</strong>
                    {{ trans('corpwalletmanager::help.predictions_tip') }}
                </div>
            </div>
        </div>

        {{-- Director Tabs Section --}}
        <div id="director-tabs" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-columns"></i>
                    {{ trans('corpwalletmanager::help.director_tabs_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.director_tabs_intro') }}</p>

                <div class="tab-explanation">
                    <h5><i class="fas fa-tachometer-alt"></i> {{ trans('corpwalletmanager::help.overview_tab_title') }}</h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.overview_tab_purpose') }}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.overview_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.overview_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-chart-bar"></i> {{ trans('corpwalletmanager::help.analytics_tab_title') }}</h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.analytics_tab_purpose') }}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.subsections') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.analytics_tab_subsections') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.analytics_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-file-alt"></i> {{ trans('corpwalletmanager::help.reports_tab_title') }}</h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.reports_tab_purpose') }}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.reports_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.currently_supported') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.reports_tab_supported') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.reports_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-chart-pie"></i> {{ trans('corpwalletmanager::help.predictions_tab_title') }}</h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.predictions_tab_purpose') }}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.predictions_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.predictions_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-users"></i> {{ trans('corpwalletmanager::help.contributors_tab_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {!! trans('corpwalletmanager::help.contributors_tab_purpose') !!}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.contributors_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.contributors_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-chart-pie"></i> {{ trans('corpwalletmanager::help.profit_attribution_tab_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {!! trans('corpwalletmanager::help.profit_attribution_tab_purpose') !!}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.profit_attribution_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.profit_attribution_tab_best') }}</p>
                </div>

                <div class="tab-explanation">
                    <h5><i class="fas fa-balance-scale"></i> {{ trans('corpwalletmanager::help.alliance_tax_tab_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <p><strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {!! trans('corpwalletmanager::help.alliance_tax_tab_purpose') !!}</p>
                    <p><strong>{{ trans('corpwalletmanager::help.features') }}:</strong></p>
                    {!! trans('corpwalletmanager::help.alliance_tax_tab_features') !!}
                    <p><strong>{{ trans('corpwalletmanager::help.best_for') }}:</strong> {{ trans('corpwalletmanager::help.alliance_tax_tab_best') }}</p>
                </div>

                <div class="info-box">
                    <i class="fas fa-sync"></i>
                    <strong>{{ trans('corpwalletmanager::help.data_refresh') }}:</strong>
                    {{ trans('corpwalletmanager::help.data_refresh_desc') }}
                </div>
            </div>
        </div>

        {{-- Contributions & Tax Section (v3.0.0) --}}
        <div id="contributions" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-users"></i>
                    {{ trans('corpwalletmanager::help.contributions_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.contributions_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.contributions_buckets_title') }}</h4>
                {!! trans('corpwalletmanager::help.contributions_buckets_list') !!}

                <h4>{{ trans('corpwalletmanager::help.contributions_inter_division_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h4>
                <p>{!! trans('corpwalletmanager::help.contributions_inter_division_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.contributions_alliance_tax_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.contributions_alliance_tax_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.contributions_main_grouping_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.contributions_main_grouping_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.contributions_backfill_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.contributions_backfill_desc') !!}</p>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Standalone-safe:</strong>
                    Without Mining Manager, the Tax Payment and Voluntary Donation columns collapse into a single Donation bucket (still counted toward Total). Without Manager Core, the contribution cache + Top Contributors still work locally; milestone events are tracked locally and auto-fire the moment MC is installed.
                </div>
            </div>
        </div>

        {{-- Discord Webhooks Section (v3.0.0) --}}
        <div id="webhooks" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fab fa-discord"></i>
                    {{ trans('corpwalletmanager::help.webhooks_title') }}
                </h3>
                <p>{!! trans('corpwalletmanager::help.webhooks_intro') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.webhooks_fields_title') }}</h4>
                {!! trans('corpwalletmanager::help.webhooks_fields_list') !!}

                <h4>{{ trans('corpwalletmanager::help.webhooks_health_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.webhooks_health_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.webhooks_routing_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.webhooks_routing_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.webhooks_event_bus_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.webhooks_event_bus_desc') !!}</p>

                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <strong>Upgrade note:</strong>
                    The legacy single global <code>discord_webhook_url</code> setting is folded into a first-class webhook row on upgrade. The legacy settings row is left in place as dormant data, so no manual migration is required.
                </div>
            </div>
        </div>

        {{-- Diagnostic Section (v3.0.0) --}}
        <div id="diagnostic" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-stethoscope"></i>
                    {{ trans('corpwalletmanager::help.diagnostic_title') }}
                </h3>
                <p>{!! trans('corpwalletmanager::help.diagnostic_intro') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.diagnostic_tabs_title') }}</h4>
                {!! trans('corpwalletmanager::help.diagnostic_tabs_list') !!}

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Tab convention:</strong>
                    {{ trans('corpwalletmanager::help.diagnostic_tab_intro_note') }}
                </div>
            </div>
        </div>

        {{-- Integrations Section (v3.0.0) --}}
        <div id="integrations" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-plug"></i>
                    {{ trans('corpwalletmanager::help.integrations_title') }}
                </h3>
                <p>{!! trans('corpwalletmanager::help.integrations_intro') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.integrations_mc_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h4>
                <p>{!! trans('corpwalletmanager::help.integrations_mc_desc') !!}</p>

                <h5>{{ trans('corpwalletmanager::help.integrations_mc_capabilities_title') }}</h5>
                {!! trans('corpwalletmanager::help.integrations_mc_capabilities_list') !!}

                <h4>{{ trans('corpwalletmanager::help.integrations_mm_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.integrations_mm_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.integrations_hr_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h4>
                <p>{!! trans('corpwalletmanager::help.integrations_hr_desc') !!}</p>

                <h4>{{ trans('corpwalletmanager::help.integrations_standalone_title') }}</h4>
                <p>{!! trans('corpwalletmanager::help.integrations_standalone_desc') !!}</p>

                <div class="success-box">
                    <i class="fas fa-check-circle"></i>
                    <strong>No composer dependency:</strong>
                    None of the integrations require a composer dependency between plugins. All detection is <code>class_exists</code>-guarded at runtime, so plugins can be installed or removed in any order without breakage.
                </div>
            </div>
        </div>

        {{-- Dashboard Section --}}
        <div id="dashboard" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-chart-line"></i>
                    {{ trans('corpwalletmanager::help.dashboard_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.dashboard_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.director_dashboard') }}</h4>
                <p>{{ trans('corpwalletmanager::help.director_dashboard_desc') }}</p>
                <ul>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_corp_overview') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_corp_overview_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_predictions') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_predictions_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_trends') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_trends_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_divisions') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_divisions_desc') }}</li>
                </ul>

                <h4>{{ trans('corpwalletmanager::help.member_dashboard') }}</h4>
                <p>{{ trans('corpwalletmanager::help.member_dashboard_desc') }}</p>
                <ul>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_balance') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_balance_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_health') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_health_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_goals') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_goals_desc') }}</li>
                    <li><strong>{{ trans('corpwalletmanager::help.dashboard_activity') }}:</strong> {{ trans('corpwalletmanager::help.dashboard_activity_desc') }}</li>
                </ul>
            </div>
        </div>

        {{-- Predictions Section --}}
        <div id="predictions" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-bullseye"></i>
                    {{ trans('corpwalletmanager::help.predictions_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.predictions_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.model_selection_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.model_selection_desc') }}</p>
                
                <pre>{{ trans('corpwalletmanager::help.model_selection_code') }}</pre>

                <h4>{{ trans('corpwalletmanager::help.arima_details_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.arima_details_desc') }}</p>
                {!! trans('corpwalletmanager::help.arima_details_list') !!}

                <h5>{{ trans('corpwalletmanager::help.prediction_output') }}</h5>
                <pre>{{ trans('corpwalletmanager::help.arima_output_example') }}</pre>

                <h4>{{ trans('corpwalletmanager::help.basic_details_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.basic_details_desc') }}</p>
                {!! trans('corpwalletmanager::help.basic_details_list') !!}

                <h5>{{ trans('corpwalletmanager::help.basic_output') }}</h5>
                <pre>{{ trans('corpwalletmanager::help.basic_output_example') }}</pre>

                <h4>{{ trans('corpwalletmanager::help.checking_model_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.checking_model_desc') }}</p>
                <pre>{{ trans('corpwalletmanager::help.checking_model_sql') }}</pre>

                <h4>{{ trans('corpwalletmanager::help.prediction_accuracy') }}</h4>
                <p>{{ trans('corpwalletmanager::help.prediction_accuracy_desc') }}</p>
                {!! trans('corpwalletmanager::help.accuracy_factors') !!}

                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>{{ trans('corpwalletmanager::help.important') }}:</strong>
                    {{ trans('corpwalletmanager::help.prediction_warning') }}
                </div>

                <div class="info-box">
                    <i class="fas fa-chart-line"></i>
                    <strong>{{ trans('corpwalletmanager::help.improvement_over_time') }}:</strong>
                    {{ trans('corpwalletmanager::help.improvement_desc') }}
                </div>
            </div>
        </div>

        {{-- Reports Section --}}
        <div id="reports" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-file-alt"></i>
                    {{ trans('corpwalletmanager::help.reports_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.reports_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.accessing_reports') }}</h4>
                {!! trans('corpwalletmanager::help.accessing_reports_list') !!}

                <h4>{{ trans('corpwalletmanager::help.available_reports') }}</h4>
                {!! trans('corpwalletmanager::help.report_types') !!}

                <h4>{{ trans('corpwalletmanager::help.report_contents') }}</h4>
                <p>{{ trans('corpwalletmanager::help.report_contents_intro') }}</p>
                {!! trans('corpwalletmanager::help.report_contents_list') !!}

                <h4>{{ trans('corpwalletmanager::help.discord_integration') }}</h4>
                <p>{{ trans('corpwalletmanager::help.discord_integration_intro') }}</p>

                <h5>{{ trans('corpwalletmanager::help.discord_setup') }}</h5>
                <ol class="step-by-step">
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.discord_step1_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.discord_step1_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.discord_step2_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.discord_step2_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.discord_step3_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.discord_step3_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.discord_step4_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.discord_step4_desc') }}
                    </li>
                    <li>
                        <strong>{{ trans('corpwalletmanager::help.discord_step5_title') }}</strong><br>
                        {{ trans('corpwalletmanager::help.discord_step5_desc') }}
                    </li>
                </ol>

                <h4>{{ trans('corpwalletmanager::help.report_automation') }}</h4>
                <p>{{ trans('corpwalletmanager::help.report_automation_intro') }}</p>
                {!! trans('corpwalletmanager::help.automation_schedule') !!}

                <h4>{{ trans('corpwalletmanager::help.notification_triggers') }}</h4>
                <p>{{ trans('corpwalletmanager::help.notification_triggers_intro') }}</p>
                {!! trans('corpwalletmanager::help.notification_triggers_list') !!}

                <h4>{{ trans('corpwalletmanager::help.report_history') }}</h4>
                <p>{{ trans('corpwalletmanager::help.report_history_desc') }}</p>
                {!! trans('corpwalletmanager::help.report_history_features') !!}

                <div class="success-box">
                    <i class="fas fa-bell"></i>
                    <strong>{{ trans('corpwalletmanager::help.coming_soon') }}:</strong>
                    {!! trans('corpwalletmanager::help.coming_soon_features') !!}
                </div>

                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>{{ trans('corpwalletmanager::help.note') }}:</strong>
                    {{ trans('corpwalletmanager::help.reports_development_note') }}
                </div>
            </div>
        </div>

        {{-- Analytics Section --}}
        <div id="analytics" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-chart-bar"></i>
                    {{ trans('corpwalletmanager::help.analytics_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.analytics_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.available_charts') }}</h4>
                {!! trans('corpwalletmanager::help.chart_types') !!}

                <h4>{{ trans('corpwalletmanager::help.customizing_view') }}</h4>
                <p>{{ trans('corpwalletmanager::help.customizing_view_desc') }}</p>
            </div>
        </div>

        {{-- Commands Section --}}
        <div id="commands" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-terminal"></i>
                    {{ trans('corpwalletmanager::help.commands_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.commands_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.scheduled_commands') }}</h4>
                <p>{{ trans('corpwalletmanager::help.scheduled_commands_intro') }}</p>
                
                <div class="command-list">
                    <h5>{{ trans('corpwalletmanager::help.cmd_update_hourly') }}</h5>
                    <code>php artisan corpwalletmanager:update-hourly</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_hourly_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_hourly_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_hourly_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong> {{ trans('corpwalletmanager::help.none') }}
                    </p>
                    
                    <h5>{{ trans('corpwalletmanager::help.cmd_daily_aggregation') }}</h5>
                    <code>php artisan corpwalletmanager:daily-aggregation</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_aggregation_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_aggregation_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_aggregation_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_aggregation_options') !!}
                    </p>
                    
                    <h5>{{ trans('corpwalletmanager::help.cmd_compute_predictions') }}</h5>
                    <code>php artisan corpwalletmanager:compute-predictions</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_predictions_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_predictions_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_predictions_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_predictions_options') !!}
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_compute_division_predictions') }}</h5>
                    <code>php artisan corpwalletmanager:compute-division-predictions</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_division_predictions_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_division_predictions_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_division_predictions_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_division_predictions_options') !!}
                    </p>
                    
                    <h5>{{ trans('corpwalletmanager::help.cmd_generate_report') }}</h5>
                    <code>php artisan corpwalletmanager:generate-report</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_report_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_report_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_report_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_report_options') !!}
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_backtest') }}</h5>
                    <code>php artisan corpwalletmanager:backtest</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backtest_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backtest_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backtest_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong> {{ trans('corpwalletmanager::help.none') }}
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_detect_alerts') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <code>php artisan corpwalletmanager:detect-alerts</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_detect_alerts_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_detect_alerts_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {!! trans('corpwalletmanager::help.cmd_detect_alerts_desc') !!}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong> {{ trans('corpwalletmanager::help.none') }}
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_compute_contributions') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <code>php artisan corpwalletmanager:compute-contributions</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_compute_contributions_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.schedule') }}:</strong> {{ trans('corpwalletmanager::help.cmd_compute_contributions_schedule') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_compute_contributions_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong> {{ trans('corpwalletmanager::help.none') }}
                    </p>
                </div>

                <h4>{{ trans('corpwalletmanager::help.manual_commands') }}</h4>
                <p>{!! trans('corpwalletmanager::help.manual_commands_intro') !!}</p>

                <div class="success-box" style="margin-bottom: 1rem;">
                    <i class="fas fa-rocket"></i>
                    <div>
                        <strong>Recommended after install / upgrade:</strong>
                        <pre style="margin-top: 0.4rem; margin-bottom: 0;"><code>docker exec -it seat-docker-front-1 php artisan corpwalletmanager:initialize</code></pre>
                        <small class="text-muted" style="display: block; margin-top: 0.4rem;">
                            Runs every CWM cache-populating step in the right order with per-step progress bars. Idempotent and safe to re-run.
                        </small>
                    </div>
                </div>

                <div class="command-list">
                    <h5>{{ trans('corpwalletmanager::help.cmd_initialize') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <code>php artisan corpwalletmanager:initialize</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_initialize_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_initialize_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_initialize_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_initialize_options') !!}
                        <strong>{{ trans('corpwalletmanager::help.example') }}:</strong> <code>php artisan corpwalletmanager:initialize --months=6 --skip=alerts --force</code>
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_backfill') }}</h5>
                    <code>php artisan corpwalletmanager:backfill-wallet-data</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_backfill_options') !!}
                        <strong>{{ trans('corpwalletmanager::help.example') }}:</strong> <code>php artisan corpwalletmanager:backfill-wallet-data --months=3 --corporation=98000001</code>
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_backfill_divisions') }}</h5>
                    <code>php artisan corpwalletmanager:backfill-division-wallet-data</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_divisions_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_divisions_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_divisions_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_backfill_divisions_options') !!}
                    </p>
                    
                    <h5>{{ trans('corpwalletmanager::help.cmd_integrity_check') }}</h5>
                    <code>php artisan corpwalletmanager:integrity-check</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_integrity_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_integrity_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_checks') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_integrity_checks') !!}
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_integrity_options') !!}
                        <strong>{{ trans('corpwalletmanager::help.example') }}:</strong> <code>php artisan corpwalletmanager:integrity-check --detailed</code>
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_backfill_contributions') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                    <code>php artisan corpwalletmanager:backfill-contributions</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_contributions_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_contributions_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_contributions_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_backfill_contributions_options') !!}
                        <strong>{{ trans('corpwalletmanager::help.example') }}:</strong> <code>php artisan corpwalletmanager:backfill-contributions --months=12</code>
                    </p>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>{{ trans('corpwalletmanager::help.note') }}:</strong>
                    {{ trans('corpwalletmanager::help.commands_note') }}
                </div>

                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>{{ trans('corpwalletmanager::help.backfill_warning_title') }}:</strong>
                    {{ trans('corpwalletmanager::help.backfill_warning') }}
                </div>
            </div>
        </div>

        {{-- Settings Section --}}
        <div id="settings" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-cog"></i>
                    {{ trans('corpwalletmanager::help.settings_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.settings_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.general_settings') }}</h4>
                {!! trans('corpwalletmanager::help.general_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.prediction_settings') }}</h4>
                {!! trans('corpwalletmanager::help.prediction_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.division_settings') }}</h4>
                {!! trans('corpwalletmanager::help.division_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.report_settings') }}</h4>
                {!! trans('corpwalletmanager::help.report_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.alert_settings') }}</h4>
                {!! trans('corpwalletmanager::help.alert_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.advanced_settings') }}</h4>
                {!! trans('corpwalletmanager::help.advanced_settings_list') !!}

                <h4>{{ trans('corpwalletmanager::help.maintenance_actions') }}</h4>
                <p>{{ trans('corpwalletmanager::help.maintenance_actions_intro') }}</p>
                {!! trans('corpwalletmanager::help.maintenance_actions_list') !!}

                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>{{ trans('corpwalletmanager::help.warning') }}:</strong>
                    {{ trans('corpwalletmanager::help.settings_warning') }}
                </div>

                <div class="info-box">
                    <i class="fas fa-save"></i>
                    <strong>{{ trans('corpwalletmanager::help.saving_changes') }}:</strong>
                    {{ trans('corpwalletmanager::help.saving_changes_desc') }}
                </div>
            </div>
        </div>

        {{-- Member View Section --}}
        <div id="member-view" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-user"></i>
                    {{ trans('corpwalletmanager::help.member_view_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.member_view_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.member_view_tabs_heading') }}</h4>
                <p>{{ trans('corpwalletmanager::help.member_view_tabs_intro') }}</p>
                {!! trans('corpwalletmanager::help.member_view_tabs_list') !!}

                <div class="info-box">
                    <i class="fas fa-users"></i>
                    <strong>{{ trans('corpwalletmanager::help.member_view_aggregation_heading') }}:</strong>
                    {!! trans('corpwalletmanager::help.member_view_aggregation_desc') !!}
                </div>

                <h4>{{ trans('corpwalletmanager::help.available_information') }}</h4>
                
                <h5><i class="fas fa-wallet"></i> {{ trans('corpwalletmanager::help.member_balance_overview') }}</h5>
                {!! trans('corpwalletmanager::help.member_balance_features') !!}

                <h5><i class="fas fa-heart-pulse"></i> {{ trans('corpwalletmanager::help.member_health_score') }}</h5>
                <p>{{ trans('corpwalletmanager::help.member_health_desc') }}</p>
                {!! trans('corpwalletmanager::help.member_health_ratings') !!}

                <h5><i class="fas fa-bullseye"></i> {{ trans('corpwalletmanager::help.member_goals') }}</h5>
                <p>{{ trans('corpwalletmanager::help.member_goals_desc') }}</p>
                {!! trans('corpwalletmanager::help.member_goals_features') !!}

                <h5><i class="fas fa-trophy"></i> {{ trans('corpwalletmanager::help.member_milestones') }}</h5>
                <p>{{ trans('corpwalletmanager::help.member_milestones_desc') }}</p>
                {!! trans('corpwalletmanager::help.member_milestones_features') !!}

                <h5><i class="fas fa-chart-line"></i> {{ trans('corpwalletmanager::help.member_activity_patterns') }}</h5>
                {!! trans('corpwalletmanager::help.member_activity_features') !!}

                <h5><i class="fas fa-chart-area"></i> {{ trans('corpwalletmanager::help.member_performance') }}</h5>
                {!! trans('corpwalletmanager::help.member_performance_features') !!}

                <h4>{{ trans('corpwalletmanager::help.member_cannot_see') }}</h4>
                <p>{{ trans('corpwalletmanager::help.member_cannot_see_intro') }}</p>
                {!! trans('corpwalletmanager::help.member_restrictions') !!}

                <h4>{{ trans('corpwalletmanager::help.access_logging') }}</h4>
                <p>{{ trans('corpwalletmanager::help.access_logging_desc') }}</p>
                {!! trans('corpwalletmanager::help.access_logging_items') !!}
                <p><em>{{ trans('corpwalletmanager::help.access_logging_purpose') }}</em></p>

                <h4>{{ trans('corpwalletmanager::help.customization_options') }}</h4>
                <p>{{ trans('corpwalletmanager::help.customization_options_desc') }}</p>
                {!! trans('corpwalletmanager::help.customization_options_list') !!}

                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    <strong>{{ trans('corpwalletmanager::help.privacy_security') }}:</strong>
                    {{ trans('corpwalletmanager::help.privacy_desc') }}
                </div>

                <div class="success-box">
                    <i class="fas fa-users"></i>
                    <strong>{{ trans('corpwalletmanager::help.building_trust') }}:</strong>
                    {{ trans('corpwalletmanager::help.building_trust_desc') }}
                </div>
            </div>
        </div>

        {{-- FAQ Section --}}
        <div id="faq" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-question-circle"></i>
                    {{ trans('corpwalletmanager::help.frequently_asked') }}
                </h3>
                
                @for ($i = 1; $i <= 15; $i++)
                <div class="faq-item">
                    <div class="faq-question">
                        <strong>{{ trans("corpwalletmanager::help.faq_q{$i}") }}</strong>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{!! trans("corpwalletmanager::help.faq_a{$i}") !!}</p>
                    </div>
                </div>
                @endfor
            </div>
        </div>

        {{-- Troubleshooting Section --}}
        <div id="troubleshooting" class="help-section">
            <div class="help-card">
                <h3>
                    <i class="fas fa-wrench"></i>
                    {{ trans('corpwalletmanager::help.troubleshooting_guide') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.troubleshooting_intro') }}</p>

                <h4>{{ trans('corpwalletmanager::help.common_issues') }}</h4>
                
                <h5>{{ trans('corpwalletmanager::help.issue1_title') }}</h5>
                <p>{{ trans('corpwalletmanager::help.issue1_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue1_solutions') !!}

                <h5>{{ trans('corpwalletmanager::help.issue2_title') }}</h5>
                <p>{{ trans('corpwalletmanager::help.issue2_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue2_solutions') !!}

                <h5>{{ trans('corpwalletmanager::help.issue3_title') }}</h5>
                <p>{{ trans('corpwalletmanager::help.issue3_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue3_solutions') !!}

                <h5>{{ trans('corpwalletmanager::help.issue4_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                <p>{{ trans('corpwalletmanager::help.issue4_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue4_solutions') !!}

                <h5>{{ trans('corpwalletmanager::help.issue5_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                <p>{{ trans('corpwalletmanager::help.issue5_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue5_solutions') !!}

                <h5>{{ trans('corpwalletmanager::help.issue6_title') }} <span class="v3-badge">{{ trans('corpwalletmanager::help.v3_badge') }}</span></h5>
                <p>{{ trans('corpwalletmanager::help.issue6_desc') }}</p>
                {!! trans('corpwalletmanager::help.issue6_solutions') !!}

                <div class="info-box">
                    <i class="fas fa-life-ring"></i>
                    <strong>{{ trans('corpwalletmanager::help.need_help') }}:</strong>
                    {!! trans('corpwalletmanager::help.support_message') !!}
                </div>
            </div>
        </div>

    </div>
</div>
</div> {{-- /.corp-wallet-wrapper --}}

@push('javascript')
<script>
$(document).ready(function() {
    // Navigation
    $('.help-nav .nav-link').on('click', function(e) {
        e.preventDefault();
        
        const section = $(this).data('section');
        
        // Update nav
        $('.help-nav .nav-link').removeClass('active');
        $(this).addClass('active');
        
        // Update content
        $('.help-section').removeClass('active');
        $(`#${section}`).addClass('active');
        
        // Update URL hash
        window.location.hash = section;
        
        // Scroll to top of content
        $('.help-content').scrollTop(0);
    });
    
    // Load section from URL hash
    if (window.location.hash) {
        const hash = window.location.hash.substring(1);
        $(`.help-nav .nav-link[data-section="${hash}"]`).click();
    }
    
    // FAQ Accordion
    $('.faq-question').on('click', function() {
        $(this).closest('.faq-item').toggleClass('open');
    });
    
    // Search functionality
    let searchTimeout;
    $('#helpSearch').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().toLowerCase();
        
        if (query.length < 2) {
            $('.help-card').show();
            return;
        }
        
        searchTimeout = setTimeout(() => {
            $('.help-card').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.includes(query));
            });
        }, 300);
    });
});
</script>
@endpush
@endsection
