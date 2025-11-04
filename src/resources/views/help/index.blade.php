@extends('web::layouts.grids.12')

@section('title', trans('corpwalletmanager::help.help_documentation'))
@section('page_header', trans('corpwalletmanager::help.help_documentation'))

@push('head')
<style>
    .help-wrapper {
        display: flex;
        gap: 20px;
    }
    
    .help-sidebar {
        flex: 0 0 280px;
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }
    
    .help-content {
        flex: 1;
        min-width: 0;
    }
    
    .help-nav .nav-link {
        color: #e2e8f0;
        border-radius: 5px;
        margin-bottom: 5px;
        padding: 10px 15px;
        transition: all 0.3s;
        font-size: 0.95rem;
    }
    
    .help-nav .nav-link:hover {
        background: rgba(76, 175, 239, 0.2);
    }
    
    .help-nav .nav-link.active {
        background: linear-gradient(135deg, #4cafef 0%, #3b82f6 100%);
    }
    
    .help-nav .nav-link i {
        width: 24px;
        text-align: center;
        margin-right: 10px;
    }
    
    .help-section {
        display: none;
        animation: fadeIn 0.3s;
    }
    
    .help-section.active {
        display: block;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .help-card {
        background: #2d3748;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 20px;
        border: 1px solid rgba(76, 175, 239, 0.2);
    }
    
    .help-card h3 {
        color: #4cafef;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .help-card h4 {
        color: #9ca3af;
        margin-top: 20px;
        margin-bottom: 10px;
        font-size: 1.1rem;
    }

    .help-card h5 {
        color: #9ca3af;
        margin-top: 15px;
        margin-bottom: 8px;
        font-size: 1rem;
    }
    
    .help-card p {
        color: #d1d5db;
        line-height: 1.6;
    }
    
    .help-card ul, .help-card ol {
        color: #d1d5db;
        line-height: 1.8;
        margin-left: 20px;
    }
    
    .help-card code {
        background: rgba(0, 0, 0, 0.3);
        padding: 2px 6px;
        border-radius: 3px;
        color: #fbbf24;
        font-size: 0.9em;
    }
    
    .help-card pre {
        background: rgba(0, 0, 0, 0.3);
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
        color: #d1d5db;
    }
    
    .step-by-step {
        counter-reset: step-counter;
        list-style: none;
        padding-left: 0;
    }
    
    .step-by-step li {
        counter-increment: step-counter;
        margin-bottom: 20px;
        padding-left: 50px;
        position: relative;
    }
    
    .step-by-step li::before {
        content: counter(step-counter);
        position: absolute;
        left: 0;
        top: 0;
        background: linear-gradient(135deg, #4cafef 0%, #3b82f6 100%);
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1rem;
    }
    
    .info-box {
        background: rgba(23, 162, 184, 0.15);
        border-left: 4px solid #17a2b8;
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
        color: #d1d5db;
    }
    
    .warning-box {
        background: rgba(255, 193, 7, 0.15);
        border-left: 4px solid #ffc107;
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
        color: #d1d5db;
    }
    
    .success-box {
        background: rgba(28, 200, 138, 0.15);
        border-left: 4px solid #1cc88a;
        padding: 15px;
        margin: 15px 0;
        border-radius: 5px;
        color: #d1d5db;
    }
    
    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }
    
    .feature-item {
        background: rgba(76, 175, 239, 0.1);
        padding: 15px;
        border-radius: 8px;
        border: 1px solid rgba(76, 175, 239, 0.3);
    }
    
    .feature-item i {
        font-size: 2rem;
        color: #4cafef;
        margin-bottom: 10px;
    }
    
    .feature-item h5 {
        color: #e2e8f0;
        margin-bottom: 8px;
    }
    
    .feature-item p {
        color: #9ca3af;
        font-size: 0.9rem;
        margin: 0;
    }
    
    .search-box {
        position: relative;
        margin-bottom: 20px;
    }
    
    .search-box input {
        width: 100%;
        padding: 12px 45px 12px 15px;
        background: #2d3748;
        border: 1px solid rgba(76, 175, 239, 0.3);
        border-radius: 8px;
        color: #e2e8f0;
    }
    
    .search-box i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }
    
    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin: 20px 0;
    }
    
    .quick-link {
        background: linear-gradient(135deg, #4cafef 0%, #3b82f6 100%);
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        color: white;
        text-decoration: none;
        transition: transform 0.2s;
    }
    
    .quick-link:hover {
        transform: translateY(-2px);
        color: white;
        text-decoration: none;
    }
    
    .quick-link i {
        font-size: 1.5rem;
        margin-bottom: 5px;
    }
    
    .faq-item {
        margin-bottom: 15px;
        border-bottom: 1px solid rgba(76, 175, 239, 0.2);
        padding-bottom: 15px;
    }
    
    .faq-question {
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #e2e8f0;
        padding: 10px;
        border-radius: 5px;
        transition: background 0.3s;
    }
    
    .faq-question:hover {
        background: rgba(76, 175, 239, 0.1);
    }
    
    .faq-answer {
        display: none;
        padding: 15px 10px 10px;
        color: #d1d5db;
    }
    
    .faq-item.open .faq-answer {
        display: block;
    }
    
    .faq-item.open .faq-question i {
        transform: rotate(180deg);
    }
    
    .command-list {
        margin: 20px 0;
    }
    
    .command-list code {
        display: block;
        margin: 10px 0 5px;
        padding: 10px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 5px;
        color: #fbbf24;
    }

    .plugin-info {
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid rgba(76, 175, 239, 0.3);
    }

    .plugin-info .info-row {
        color: #9ca3af;
        margin: 5px 0;
    }

    .plugin-info .author {
        color: #4cafef;
        margin: 10px 0;
    }

    .plugin-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .plugin-link {
        background: rgba(76, 175, 239, 0.1);
        padding: 10px;
        border-radius: 5px;
        border: 1px solid rgba(76, 175, 239, 0.3);
        color: #4cafef;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s;
    }

    .plugin-link:hover {
        background: rgba(76, 175, 239, 0.2);
        color: #6dd5fa;
        text-decoration: none;
        transform: translateX(5px);
    }

    .model-comparison {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin: 20px 0;
    }

    .model-card {
        background: rgba(76, 175, 239, 0.1);
        padding: 20px;
        border-radius: 10px;
        border: 2px solid rgba(76, 175, 239, 0.3);
    }

    .model-card h5 {
        color: #4cafef;
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
        color: #d1d5db;
    }

    .model-card ul li i {
        color: #1cc88a;
        margin-right: 8px;
    }

    .tab-explanation {
        background: rgba(76, 175, 239, 0.05);
        padding: 15px;
        border-left: 3px solid #4cafef;
        margin: 15px 0;
        border-radius: 5px;
    }

    .tab-explanation h5 {
        color: #4cafef;
        margin-bottom: 10px;
    }
</style>
@endpush

@section('content')
<div class="help-wrapper">
    {{-- Sidebar Navigation --}}
    <div class="help-sidebar">
        <div class="search-box">
            <input type="text" id="helpSearch" placeholder="{{ trans('corpwalletmanager::help.search_placeholder') }}">
            <i class="fas fa-search"></i>
        </div>
        
        <div class="help-nav">
            <a href="#" class="nav-link active" data-section="overview">
                <i class="fas fa-home"></i>
                {{ trans('corpwalletmanager::help.overview') }}
            </a>
            <a href="#" class="nav-link" data-section="getting-started">
                <i class="fas fa-rocket"></i>
                {{ trans('corpwalletmanager::help.getting_started') }}
            </a>
            <a href="#" class="nav-link" data-section="features">
                <i class="fas fa-star"></i>
                {{ trans('corpwalletmanager::help.features') }}
            </a>
            <a href="#" class="nav-link" data-section="director-tabs">
                <i class="fas fa-columns"></i>
                {{ trans('corpwalletmanager::help.director_tabs') }}
            </a>
            <a href="#" class="nav-link" data-section="dashboard">
                <i class="fas fa-chart-line"></i>
                {{ trans('corpwalletmanager::help.dashboard') }}
            </a>
            <a href="#" class="nav-link" data-section="predictions">
                <i class="fas fa-crystal-ball"></i>
                {{ trans('corpwalletmanager::help.predictions') }}
            </a>
            <a href="#" class="nav-link" data-section="reports">
                <i class="fas fa-file-alt"></i>
                {{ trans('corpwalletmanager::help.reports') }}
            </a>
            <a href="#" class="nav-link" data-section="analytics">
                <i class="fas fa-chart-bar"></i>
                {{ trans('corpwalletmanager::help.analytics') }}
            </a>
            <a href="#" class="nav-link" data-section="commands">
                <i class="fas fa-terminal"></i>
                {{ trans('corpwalletmanager::help.commands') }}
            </a>
            <a href="#" class="nav-link" data-section="settings">
                <i class="fas fa-cog"></i>
                {{ trans('corpwalletmanager::help.settings') }}
            </a>
            <a href="#" class="nav-link" data-section="member-view">
                <i class="fas fa-user"></i>
                {{ trans('corpwalletmanager::help.member_view') }}
            </a>
            <a href="#" class="nav-link" data-section="faq">
                <i class="fas fa-question-circle"></i>
                {{ trans('corpwalletmanager::help.faq') }}
            </a>
            <a href="#" class="nav-link" data-section="troubleshooting">
                <i class="fas fa-wrench"></i>
                {{ trans('corpwalletmanager::help.troubleshooting') }}
            </a>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="help-content">
        
        {{-- Overview Section --}}
        <div id="overview" class="help-section active">
            {{-- Plugin Information --}}
            <div class="plugin-info">
                <h3 style="color: #4cafef; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> {{ trans('corpwalletmanager::help.plugin_info_title') }}
                </h3>
                <div class="info-row">
                    <strong>{{ trans('corpwalletmanager::help.version') }}:</strong> 
                    <img src="https://img.shields.io/github/v/release/MattFalahe/Corp-Wallet-Manager" alt="Version" style="vertical-align: middle;">
                    <img src="https://img.shields.io/badge/SeAT-5.0-green" alt="SeAT" style="vertical-align: middle;">
                </div>
                <div class="info-row">
                    <strong>{{ trans('corpwalletmanager::help.license') }}:</strong> GPL-2.0
                </div>
                
                <div class="author">
                    <i class="fas fa-user"></i> <strong>{{ trans('corpwalletmanager::help.author') }}:</strong> Matt Falahe
                    <br>
                    <i class="fas fa-envelope"></i> <a href="mailto:mattfalahe@gmail.com" style="color: #4cafef;">mattfalahe@gmail.com</a>
                </div>

                <div class="plugin-links">
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager" target="_blank" class="plugin-link">
                        <i class="fab fa-github"></i> {{ trans('corpwalletmanager::help.github_repo') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/blob/main/CHANGELOG.md" target="_blank" class="plugin-link">
                        <i class="fas fa-list"></i> {{ trans('corpwalletmanager::help.changelog') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/issues" target="_blank" class="plugin-link">
                        <i class="fas fa-bug"></i> {{ trans('corpwalletmanager::help.report_issues') }}
                    </a>
                    <a href="https://github.com/MattFalahe/Corp-Wallet-Manager/blob/main/README.md" target="_blank" class="plugin-link">
                        <i class="fas fa-book"></i> {{ trans('corpwalletmanager::help.readme') }}
                    </a>
                </div>

                <div class="success-box" style="margin-top: 15px;">
                    <i class="fas fa-heart"></i>
                    <strong>{{ trans('corpwalletmanager::help.support_project') }}:</strong>
                    {!! trans('corpwalletmanager::help.support_list') !!}
                </div>
            </div>

            <div class="help-card">
                <h3>
                    <i class="fas fa-wallet"></i>
                    {{ trans('corpwalletmanager::help.welcome_title') }}
                </h3>
                <p>{{ trans('corpwalletmanager::help.welcome_desc') }}</p>

                <h4>{{ trans('corpwalletmanager::help.what_is_title') }}</h4>
                <p>{{ trans('corpwalletmanager::help.what_is_desc') }}</p>

                <div class="feature-grid">
                    <div class="feature-item">
                        <i class="fas fa-chart-line"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_tracking_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_tracking_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-brain"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_predictions_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_predictions_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-chart-bar"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_analytics_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_analytics_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-file-chart-line"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_reports_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_reports_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users-cog"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_divisions_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_divisions_desc') }}</p>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <h5>{{ trans('corpwalletmanager::help.feature_permissions_title') }}</h5>
                        <p>{{ trans('corpwalletmanager::help.feature_permissions_desc') }}</p>
                    </div>
                </div>

                <div class="quick-links">
                    <a href="{{ route('corpwalletmanager.director') }}" class="quick-link">
                        <i class="fas fa-tachometer-alt"></i><br>
                        {{ trans('corpwalletmanager::help.view_dashboard') }}
                    </a>
                    <a href="{{ route('corpwalletmanager.settings') }}" class="quick-link">
                        <i class="fas fa-cog"></i><br>
                        {{ trans('corpwalletmanager::help.configure_settings') }}
                    </a>
                    <a href="{{ route('corpwalletmanager.reports.history') }}" class="quick-link">
                        <i class="fas fa-file-alt"></i><br>
                        {{ trans('corpwalletmanager::help.view_reports') }}
                    </a>
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

                <div class="info-box">
                    <i class="fas fa-sync"></i>
                    <strong>{{ trans('corpwalletmanager::help.data_refresh') }}:</strong>
                    {{ trans('corpwalletmanager::help.data_refresh_desc') }}
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
                    <i class="fas fa-crystal-ball"></i>
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
                </div>

                <h4>{{ trans('corpwalletmanager::help.manual_commands') }}</h4>
                <p>{{ trans('corpwalletmanager::help.manual_commands_intro') }}</p>
                
                <div class="command-list">
                    <h5>{{ trans('corpwalletmanager::help.cmd_backfill') }}</h5>
                    <code>php artisan corpwalletmanager:backfill</code>
                    <p style="margin-left: 15px; color: #9ca3af;">
                        <strong>{{ trans('corpwalletmanager::help.purpose') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_purpose') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.when_to_use') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_when') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.what_it_does') }}:</strong> {{ trans('corpwalletmanager::help.cmd_backfill_desc') }}<br>
                        <strong>{{ trans('corpwalletmanager::help.options') }}:</strong>
                        {!! trans('corpwalletmanager::help.cmd_backfill_options') !!}
                        <strong>{{ trans('corpwalletmanager::help.example') }}:</strong> <code>php artisan corpwalletmanager:backfill --months=3 --corporation=98000001</code>
                    </p>

                    <h5>{{ trans('corpwalletmanager::help.cmd_backfill_divisions') }}</h5>
                    <code>php artisan corpwalletmanager:backfill-divisions</code>
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
                
                @for ($i = 1; $i <= 10; $i++)
                <div class="faq-item">
                    <div class="faq-question">
                        <strong>{{ trans("corpwalletmanager::help.faq_q{$i}") }}</strong>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>{{ trans("corpwalletmanager::help.faq_a{$i}") }}</p>
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

                <div class="info-box">
                    <i class="fas fa-life-ring"></i>
                    <strong>{{ trans('corpwalletmanager::help.need_help') }}:</strong>
                    {{ trans('corpwalletmanager::help.support_message') }}
                </div>
            </div>
        </div>

    </div>
</div>

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
