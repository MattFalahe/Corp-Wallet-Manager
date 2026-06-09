<?php

namespace CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;
use CorpWalletManager\Console\Commands\UpdateHourlyWalletDataCommand;
use CorpWalletManager\Console\Commands\DailyAggregationCommand;
use CorpWalletManager\Console\Commands\ComputeDailyPredictionCommand;
use CorpWalletManager\Console\Commands\ComputeDivisionDailyPredictionCommand;
use CorpWalletManager\Console\Commands\GenerateReportCommand;
use CorpWalletManager\Console\Commands\BackfillWalletDataCommand;
use CorpWalletManager\Console\Commands\BackfillDivisionWalletDataCommand;
use CorpWalletManager\Console\Commands\IntegrityCheckCommand;
use CorpWalletManager\Console\Commands\BacktestPredictionsCommand;
use CorpWalletManager\Console\Commands\DetectWalletAlertsCommand;
use CorpWalletManager\Console\Commands\ComputeCharacterContributionsCommand;
use CorpWalletManager\Console\Commands\BackfillCharacterContributionsCommand;
use CorpWalletManager\Console\Commands\DispatchScheduledReportsCommand;
use CorpWalletManager\Console\Commands\ComputePersonalWalletAggregatesCommand;
use CorpWalletManager\Console\Commands\InitializeCommand;
use CorpWalletManager\Database\Seeders\ScheduleSeeder;
use CorpWalletManager\Services\BacktestService;
use CorpWalletManager\Services\ContributionService;
use CorpWalletManager\Services\ModelSelector;
use CorpWalletManager\Services\PersonalWalletAggregator;
use CorpWalletManager\Services\RattingIncomeService;
use CorpWalletManager\Services\SeasonalFactorLearner;
use CorpWalletManager\Services\WebhookService;
use Illuminate\Support\Facades\Log;

/**
 * Fallback version constant used by the Help page Version Status block
 * when Composer's runtime metadata isn't available (e.g. a zip-deployed
 * install). The composer.json / Packagist version remains the source of
 * truth for the package; this constant exists only so the badge never
 * renders empty.
 */
const CWM_VERSION = '3.0.0';

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        // Check if routes are cached before loading
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }

        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/', 'corpwalletmanager');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', 'corpwalletmanager');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');

        // Add publications
        $this->add_publications();

        // Register Plugin Bridge capabilities for cross-plugin communication
        $this->registerPluginBridgeCapabilities();
    }

    /**
     * Register capabilities with the Manager Core Plugin Bridge.
     * Exposes per-character ratting income data for HR Manager and other consumers.
     * The capabilities are thin delegators to RattingIncomeService so the same
     * logic is reachable via direct app(RattingIncomeService::class) injection
     * when a caller doesn't want to go through MC.
     */
    private function registerPluginBridgeCapabilities()
    {
        if (!class_exists('ManagerCore\Services\PluginBridge')) {
            return;
        }

        try {
            $bridge = app(\ManagerCore\Services\PluginBridge::class);
            $service = app(RattingIncomeService::class);

            // CONTRACT NOTE: the ratting.getCharacter* capabilities below are
            // strictly PER CHARACTER (one character_id in, that character's
            // numbers out). HR Manager consumes them and does its own
            // alt rollup, so it calls these once per character and sums.
            //
            // DO NOT change these to roll up a player's alts internally. If a
            // call started returning the player's main+alts total, every
            // consumer that iterates a player's characters would multiply the
            // same income by the alt count (a player with 3 alts -> 3x the
            // real figure), and it would silently break the frozen
            // cross-plugin contract HR was built against.
            //
            // If a player-level rollup is ever wanted, add a NEW capability
            // (e.g. 'ratting.getPlayerIncome') that resolves the character set
            // via refresh_tokens.user_id and sums once, and update HR in the
            // same coordinated change to consume it and drop its per-character
            // summing. Leave the getCharacter* contract untouched.
            $bridge->registerCapability('corp-wallet-manager', 'ratting.getCharacterIncome',
                fn ($characterId, $corporationId, $months = 6) =>
                    $service->getCharacterIncome((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'ratting.getCharacterMonthly',
                fn ($characterId, $corporationId, $months = 6) =>
                    $service->getCharacterMonthly((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'ratting.getCharacterBreakdown',
                fn ($characterId, $corporationId, $months = 6) =>
                    $service->getCharacterBreakdown((int) $characterId, (int) $corporationId, (int) $months)
            );

            // Per-character contribution capabilities consumed by HR Manager
            // for member assessment. Classification (including the MM tax-code
            // vs voluntary donation split) happens inside CWM via
            // ContributionService; HR doesn't need to join against MM.
            $contributionService = app(ContributionService::class);

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterSummary',
                fn ($characterId, $corporationId, $months = 6) =>
                    $contributionService->getCharacterSummary((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterByCategory',
                fn ($characterId, $corporationId, $months = 6) =>
                    $contributionService->getCharacterByCategory((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterEntries',
                fn ($characterId, $corporationId, $months = 6, $minAmount = 0) =>
                    $contributionService->getCharacterEntries((int) $characterId, (int) $corporationId, (int) $months, (float) $minAmount)
            );

            $bridge->registerCapability('corp-wallet-manager', 'wallet.getCorpOutflows',
                fn ($corporationId, $months = 3) =>
                    $contributionService->getCorpOutflows((int) $corporationId, (int) $months)
            );

            // Corp-wide per-member financial roll-up — EVERY member with
            // wallet activity (registered or not), in one call. Powers HR
            // Manager's Corp Health "Wallet Insights" cards corp-wide instead
            // of the registered-only assessment cache. months=0 = all-time.
            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCorpMemberSummary',
                fn ($corporationId, $months = 0) =>
                    $contributionService->getCorpMemberSummary((int) $corporationId, (int) $months)
            );

            // ---- HR Manager analytics (Tier 1) ----
            // Six per-character read methods that aggregate the existing
            // contribution cache into HR-friendly shapes. Pure math, no MM
            // dependency. HR Manager composes these with its own activity /
            // login / structure signals to drive Corp Health classification,
            // purge workflow ladders, and inactive-director alerts.
            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterTrend',
                fn ($characterId, $corporationId, $months = 6) =>
                    $contributionService->getCharacterTrend((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getActivityGaps',
                fn ($characterId, $corporationId, $months = 12) =>
                    $contributionService->getActivityGaps((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getNetPosition',
                fn ($characterId, $corporationId, $months = 6) =>
                    $contributionService->getNetPosition((int) $characterId, (int) $corporationId, (int) $months)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getLifetimeSummary',
                fn ($characterId, $corporationId) =>
                    $contributionService->getLifetimeSummary((int) $characterId, (int) $corporationId)
            );

            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterPercentile',
                fn ($characterId, $corporationId, $period) =>
                    $contributionService->getCharacterPercentile((int) $characterId, (int) $corporationId, (string) $period)
            );

            // ---- HR Manager analytics (Tier 2: MM tax compliance) ----
            // Per-character MM tax owed/paid/compliance over months.
            // class_exists-guarded inside the service; returns null when MM
            // is absent so HR can detect "tax signal unavailable" cleanly
            // and skip that input in its classification.
            $bridge->registerCapability('corp-wallet-manager', 'contribution.getCharacterTaxCompliance',
                fn ($characterId, $corporationId, $months = 6) =>
                    $contributionService->getCharacterTaxCompliance((int) $characterId, (int) $corporationId, (int) $months)
            );

            // Best-effort director attribution for corporation_account_withdrawal
            // rows. CCP does not stamp the acting director on the journal row,
            // so this capability combines context_id (when present) with a
            // logon-proximity heuristic against corporation_member_trackings.
            // HR Manager consumes it to flag directors moving large ISK while
            // otherwise quiet on social activity.
            $directorAttribution = app(\CorpWalletManager\Services\DirectorAttributionService::class);

            $bridge->registerCapability('corp-wallet-manager', 'wallet.getDirectorAttribution',
                fn ($corporationId, $months = 3, $minAmount = 50_000_000) =>
                    $directorAttribution->getDirectorAttribution((int) $corporationId, (int) $months, (float) $minAmount)
            );
        } catch (\Exception $e) {
            Log::warning('[Corp Wallet Manager] Could not register bridge capabilities: ' . $e->getMessage());
        }
    }

    /**
     * Add content which must be published.
     */
    private function add_publications()
    {
        $this->publishes([
            __DIR__ . '/resources/js' => public_path('corpwalletmanager/js'),
        ], ['public', 'seat']);

        // Publish CSS / asset bundle to vendor/corp-wallet-manager/* (canonical
        // design-system path; matches Mining Manager / Structure Manager).
        $this->publishes([
            __DIR__ . '/resources/assets' => public_path('vendor/corp-wallet-manager'),
        ], ['public', 'seat']);
    }

    public function register()
    {
        // Register sidebar configuration
        $this->mergeConfigFrom(__DIR__ . '/Config/corpwalletmanager.sidebar.php', 'package.sidebar');

        // Register permissions
        $this->registerPermissions(__DIR__ . '/Config/Permissions/corpwalletmanager.permissions.php', 'corpwalletmanager');

        // Register config
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');

        // Register database seeders (canonical home for schedule definitions;
        // all future schedule add/modify/remove must go through ScheduleSeeder).
        $this->registerDatabaseSeeders([ScheduleSeeder::class]);

        // Shared services
        $this->app->singleton(RattingIncomeService::class);
        $this->app->singleton(SeasonalFactorLearner::class);
        $this->app->singleton(BacktestService::class);
        $this->app->singleton(ModelSelector::class);
        $this->app->singleton(WebhookService::class);
        $this->app->singleton(ContributionService::class);
        $this->app->singleton(PersonalWalletAggregator::class);

        // Register commands
        $this->commands([
            UpdateHourlyWalletDataCommand::class,
            DailyAggregationCommand::class,
            ComputeDailyPredictionCommand::class,
            ComputeDivisionDailyPredictionCommand::class,
            GenerateReportCommand::class,
            BackfillWalletDataCommand::class,
            BackfillDivisionWalletDataCommand::class,
            IntegrityCheckCommand::class,
            BacktestPredictionsCommand::class,
            DetectWalletAlertsCommand::class,
            ComputeCharacterContributionsCommand::class,
            BackfillCharacterContributionsCommand::class,
            DispatchScheduledReportsCommand::class,
            ComputePersonalWalletAggregatesCommand::class,
            InitializeCommand::class,
        ]);
    }

    public function getName(): string
    {
        return 'CorpWallet Manager';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/MattFalahe/Corp-Wallet-Manager';
    }

    public function getPackagistPackageName(): string
    {
        return 'corp-wallet-manager';
    }

    public function getPackagistVendorName(): string
    {
        return 'mattfalahe';
    }
}
