<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'CorpWalletManager\Http\Controllers',
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => 'corp-wallet-manager',
], function () {
    
    // Main views
    Route::get('/director', 'WalletController@director')
        ->name('corpwalletmanager.director')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/member', 'WalletController@member')
        ->name('corpwalletmanager.member')
        ->middleware('can:corpwalletmanager.member_view');

    Route::get('/api/corporation-info', 'WalletController@getCorporationInfo')
        ->name('corpwalletmanager.corporation-info')
        ->middleware('can:corpwalletmanager.view');

    Route::post('/api/set-corporation', 'WalletController@setCorporation')
        ->name('corpwalletmanager.set-corporation')
        ->middleware('can:corpwalletmanager.view');
 
    // API endpoints for data
    Route::get('/api/latest', 'WalletController@latest')
        ->name('corpwalletmanager.latest')
        ->middleware('can:corpwalletmanager.view');
    
    Route::get('/api/monthly-comparison', 'WalletController@monthlyComparison')
        ->name('corpwalletmanager.monthly')
        ->middleware('can:corpwalletmanager.view');
        
    Route::get('/api/predictions', 'WalletController@predictions')
        ->name('corpwalletmanager.predictions')
        ->middleware('can:corpwalletmanager.view');
        
    Route::get('/api/division-breakdown', 'WalletController@divisionBreakdown')
        ->name('corpwalletmanager.divisions')
        ->middleware('can:corpwalletmanager.director_view');
        
    Route::get('/api/summary', 'WalletController@summary')
        ->name('corpwalletmanager.summary')
        ->middleware('can:corpwalletmanager.view');

    Route::get('/api/wallet-actual', 'WalletController@walletActual')
        ->name('corpwalletmanager.wallet-actual')
        ->middleware('can:corpwalletmanager.director_view');

    // APIs for Director View data
    Route::get('/api/today', 'WalletController@today')
        ->name('corpwalletmanager.today')
        ->middleware('can:corpwalletmanager.view');

    Route::get('/api/division-current', 'WalletController@divisionCurrent')
        ->name('corpwalletmanager.division-current')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/balance-history', 'WalletController@balanceHistory')
        ->name('corpwalletmanager.balance-history')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/income-expense', 'WalletController@incomeExpense')
        ->name('corpwalletmanager.income-expense')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/transaction-breakdown', 'WalletController@transactionBreakdown')
        ->name('corpwalletmanager.transaction-breakdown')
        ->middleware('can:corpwalletmanager.director_view');

    // Member View API Routes
    Route::get('/api/member/health', 'WalletController@memberHealth')
        ->name('corpwalletmanager.member.health')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/goals', 'WalletController@memberGoals')
        ->name('corpwalletmanager.member.goals')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/milestones', 'WalletController@memberMilestones')
        ->name('corpwalletmanager.member.milestones')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/activity', 'WalletController@memberActivity')
        ->name('corpwalletmanager.member.activity')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/performance-metrics', 'WalletController@memberPerformanceMetrics')
        ->name('corpwalletmanager.member.performance-metrics')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/weekly-pattern', 'WalletController@memberWeeklyPattern')
        ->name('corpwalletmanager.member.weekly-pattern')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::get('/api/member/monthly-summary', 'WalletController@memberMonthlySummary')
        ->name('corpwalletmanager.member.monthly-summary')
        ->middleware('can:corpwalletmanager.member_view');
    
    Route::post('/api/member/log-access', 'WalletController@logMemberAccess')
        ->name('corpwalletmanager.member.log-access')
        ->middleware('can:corpwalletmanager.member_view');

    // Personal contribution + leaderboard surface (v3.0.0). All four
    // gated by member_view; the leaderboard's privacy mode + size are
    // resolved server-side from Settings so a tampered URL cannot ask
    // for a different mode than the operator configured.
    Route::get('/api/personal-contribution', 'WalletController@personalContribution')
        ->name('corpwalletmanager.personal-contribution')
        ->middleware('can:corpwalletmanager.member_view');

    Route::get('/api/member-leaderboard', 'WalletController@memberLeaderboard')
        ->name('corpwalletmanager.member-leaderboard')
        ->middleware('can:corpwalletmanager.member_view');

    Route::get('/api/personal-mm-compliance', 'WalletController@personalMmCompliance')
        ->name('corpwalletmanager.personal-mm-compliance')
        ->middleware('can:corpwalletmanager.member_view');

    Route::get('/api/personal-milestones', 'WalletController@personalMilestones')
        ->name('corpwalletmanager.personal-milestones')
        ->middleware('can:corpwalletmanager.member_view');

    // My Personal Wallet tab (v3.0.0 follow-up). Aggregates the viewer's
    // SeAT `character_wallet_journals` across every owned character with
    // no corp filter - personal wallet is independent of which corp a
    // character is in - so a member sees income / expense / net /
    // sparkline / top sources / top transactions across all their alts.
    Route::get('/api/personal-wallet-stats', 'WalletController@personalWalletStats')
        ->name('corpwalletmanager.personal-wallet-stats')
        ->middleware('can:corpwalletmanager.member_view');
    
    // ===== ANALYTICS ROUTES =====
    
    // Analytics Tab Routes
    Route::get('/api/analytics/health-score', 'AnalyticsController@healthScore')
        ->name('corpwalletmanager.analytics.health-score')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/burn-rate', 'AnalyticsController@burnRate')
        ->name('corpwalletmanager.analytics.burn-rate')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/financial-ratios', 'AnalyticsController@financialRatios')
        ->name('corpwalletmanager.analytics.financial-ratios')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/analytics/daily-cashflow', 'AnalyticsController@dailyCashFlow')
        ->name('corpwalletmanager.analytics.daily-cashflow')
        ->middleware('can:corpwalletmanager.director_view');
    
    // Trends Tab Routes
    Route::get('/api/analytics/activity-heatmap', 'AnalyticsController@activityHeatmap')
        ->name('corpwalletmanager.analytics.activity-heatmap')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/best-worst-days', 'AnalyticsController@bestWorstDays')
        ->name('corpwalletmanager.analytics.best-worst-days')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/weekly-patterns', 'AnalyticsController@weeklyPatterns')
        ->name('corpwalletmanager.analytics.weekly-patterns')
        ->middleware('can:corpwalletmanager.director_view');
    
    // Performance Tab Routes
    Route::get('/api/analytics/division-performance', 'AnalyticsController@divisionPerformance')
        ->name('corpwalletmanager.analytics.division-performance')
        ->middleware('can:corpwalletmanager.director_view');

    // Cash Flow Tab Routes
    Route::get('/api/analytics/last-month-balance', 'AnalyticsController@lastMonthBalance')
        ->name('corpwalletmanager.analytics.last-month-balance')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/division-daily-cashflow', 'AnalyticsController@divisionDailyCashFlow')
        ->name('corpwalletmanager.analytics.division-daily-cashflow')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/api/analytics/divisions-list', 'AnalyticsController@divisionsList')
        ->name('corpwalletmanager.analytics.divisions-list')
        ->middleware('can:corpwalletmanager.director_view');
    
    // Reports Tab Routes
    Route::get('/api/analytics/executive-summary', 'AnalyticsController@executiveSummary')
        ->name('corpwalletmanager.analytics.executive-summary')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/analytics/top-contributors', 'AnalyticsController@topContributors')
        ->name('corpwalletmanager.analytics.top-contributors')
        ->middleware('can:corpwalletmanager.director_view');

    // Composite payload for the two supporting charts on the Top
    // Contributors tab (Contribution Concentration pie + Members vs
    // External Contributors stacked bar). One round trip, both shapes.
    Route::get('/api/analytics/contributor-mix', 'AnalyticsController@contributorMix')
        ->name('corpwalletmanager.analytics.contributor-mix')
        ->middleware('can:corpwalletmanager.director_view');

    // Profit Attribution Tab Route
    Route::get('/api/analytics/profit-attribution', 'AnalyticsController@profitAttribution')
        ->name('corpwalletmanager.analytics.profit-attribution')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/analytics/profit-attribution-trend', 'AnalyticsController@profitAttributionTrend')
        ->name('corpwalletmanager.analytics.profit-attribution-trend')
        ->middleware('can:corpwalletmanager.director_view');

    // Expense Attribution Tab Routes
    Route::get('/api/analytics/expense-attribution', 'AnalyticsController@expenseAttribution')
        ->name('corpwalletmanager.analytics.expense-attribution')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/api/analytics/expense-attribution-trend', 'AnalyticsController@expenseAttributionTrend')
        ->name('corpwalletmanager.analytics.expense-attribution-trend')
        ->middleware('can:corpwalletmanager.director_view');

    // Alliance Tax Tab Route
    Route::get('/api/analytics/alliance-tax-reconciliation', 'AnalyticsController@allianceTaxReconciliation')
        ->name('corpwalletmanager.analytics.alliance-tax-reconciliation')
        ->middleware('can:corpwalletmanager.director_view');

    Route::post('/reports/generate', 'ReportsController@generate')
        ->name('corpwalletmanager.reports.generate')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/reports/history', 'ReportsController@history')
        ->name('corpwalletmanager.reports.history')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/reports/templates', 'ReportsController@templates')
        ->name('corpwalletmanager.reports.templates')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/reports/{report}/export/pdf', 'ReportsController@exportPdf')
        ->name('corpwalletmanager.reports.export-pdf')
        ->middleware('can:corpwalletmanager.director_view');

    Route::get('/reports/{report}/export/csv', 'ReportsController@exportCsv')
        ->name('corpwalletmanager.reports.export-csv')
        ->middleware('can:corpwalletmanager.director_view');

    // Settings routes
    Route::get('/settings', 'SettingsController@index')
        ->name('corpwalletmanager.settings')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings', 'SettingsController@update')
        ->name('corpwalletmanager.settings.update')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings/reset', 'SettingsController@reset')
        ->name('corpwalletmanager.settings.reset')
        ->middleware('can:corpwalletmanager.settings');
    
    // Maintenance routes
    Route::post('/settings/backfill', 'SettingsController@triggerBackfill')
        ->name('corpwalletmanager.settings.backfill')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings/prediction', 'SettingsController@triggerPrediction')
        ->name('corpwalletmanager.settings.prediction')
        ->middleware('can:corpwalletmanager.settings');
    
    Route::post('/settings/division-backfill', 'SettingsController@triggerDivisionBackfill')
        ->name('corpwalletmanager.settings.division-backfill')
        ->middleware('can:corpwalletmanager.settings');
    
    Route::post('/settings/division-prediction', 'SettingsController@triggerDivisionPrediction')
        ->name('corpwalletmanager.settings.division-prediction')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings/contribution-backfill', 'SettingsController@triggerContributionBackfill')
        ->name('corpwalletmanager.settings.contribution-backfill')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/settings/recent-outgoing-recipients', 'SettingsController@recentOutgoingRecipients')
        ->name('corpwalletmanager.settings.recent-outgoing-recipients')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/settings/job-status', 'SettingsController@jobStatus')
        ->name('corpwalletmanager.settings.job-status')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/settings/access-logs', 'SettingsController@getAccessLogs')
        ->name('corpwalletmanager.settings.access-logs')
        ->middleware('can:corpwalletmanager.settings');
    
    // Scheduled Reports CRUD (Settings -> Scheduled Reports panel).
    // Backs the per-corp + per-cadence schedule table; the dispatcher cron
    // `corpwalletmanager:dispatch-scheduled-reports` reads from the same
    // table every 5 minutes.
    Route::get('/api/report-schedules', 'ReportSchedulesController@index')
        ->name('corpwalletmanager.report-schedules.index')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/api/report-schedules', 'ReportSchedulesController@store')
        ->name('corpwalletmanager.report-schedules.store')
        ->middleware('can:corpwalletmanager.settings');

    Route::put('/api/report-schedules/{id}', 'ReportSchedulesController@update')
        ->name('corpwalletmanager.report-schedules.update')
        ->middleware('can:corpwalletmanager.settings');

    Route::delete('/api/report-schedules/{id}', 'ReportSchedulesController@destroy')
        ->name('corpwalletmanager.report-schedules.destroy')
        ->middleware('can:corpwalletmanager.settings');

    // Discord webhook management
    Route::post('/settings/webhooks/save', 'WebhookController@save')
        ->name('corpwalletmanager.webhooks.save')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings/webhooks/{webhook}/delete', 'WebhookController@destroy')
        ->name('corpwalletmanager.webhooks.delete')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/settings/webhooks/{webhook}/test', 'WebhookController@test')
        ->name('corpwalletmanager.webhooks.test')
        ->middleware('can:corpwalletmanager.settings');

    // Route for getting selected corporation settings
    Route::get('/api/selected-corporation', 'SettingsController@getSelectedCorporation')
        ->name('corpwalletmanager.selected-corporation')
        ->middleware('can:corpwalletmanager.view');

    // Help & Documentation route
    Route::get('/help', 'SettingsController@help')
        ->name('corpwalletmanager.help')
        ->middleware('can:corpwalletmanager.view');

    // Admin-only diagnostic page. Intentionally NOT in the sidebar - admins
    // navigate manually to /corp-wallet-manager/diagnostic per the
    // diagnostic-standard convention.
    Route::get('/diagnostic', 'DiagnosticController@index')
        ->name('corpwalletmanager.diagnostic')
        ->middleware('can:corpwalletmanager.settings');

    // Notification Testing tab - fire a test notification at the operator's
    // request. Returns per-webhook delivery outcomes for in-page display.
    Route::post('/diagnostic/fire-test-notification', 'DiagnosticController@fireTestNotification')
        ->name('corpwalletmanager.diagnostic.fire-test')
        ->middleware('can:corpwalletmanager.settings');

    // Data Export CRUD (Settings -> Data Export panel).
    // Mirrors the Mining Manager bulk CSV pattern - operator-initiated
    // export of journal / contribution / report metadata / alert
    // history / anomaly state for a corp + date range. The download
    // route uses a signed URL (24h validity) per index() output.
    Route::get('/api/data-exports', 'DataExportController@index')
        ->name('corpwalletmanager.data-exports.index')
        ->middleware('can:corpwalletmanager.settings');

    Route::post('/api/data-exports', 'DataExportController@store')
        ->name('corpwalletmanager.data-exports.store')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/api/data-exports/{id}/download', 'DataExportController@download')
        ->name('corpwalletmanager.data-exports.download')
        ->middleware(['can:corpwalletmanager.settings', 'signed']);

    Route::delete('/api/data-exports/{id}', 'DataExportController@destroy')
        ->name('corpwalletmanager.data-exports.destroy')
        ->middleware('can:corpwalletmanager.settings');

});
