<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Seat\CorpWalletManager\Http\Controllers',
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

    // Internal Transfer endpoints
    Route::get('/internal-transfers', 'InternalTransferApiController@getStatistics')
        ->name('corpwalletmanager.api.internal.stats');
    
    Route::get('/internal-transfers/analyze', 'InternalTransferApiController@analyze')
        ->name('corpwalletmanager.api.internal.analyze');
    
    Route::get('/internal-transfers/matrix', 'InternalTransferApiController@getTransferMatrix')
        ->name('corpwalletmanager.api.internal.matrix');
    
    Route::post('/settings/internal-transfers', 'InternalTransferApiController@saveSettings')
        ->name('corpwalletmanager.api.internal.settings')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/api/internal-transfers', [
        'as' => 'corpwalletmanager.api.internal.stats',
        'uses' => 'WalletController@getInternalTransferStats'
    
    Route::post('/api/internal-transfers/settings', [
        'as' => 'corpwalletmanager.api.internal.settings',
        'uses' => 'WalletController@saveInternalTransferSettings'
    
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
    
    Route::get('/settings/job-status', 'SettingsController@jobStatus')
        ->name('corpwalletmanager.settings.job-status')
        ->middleware('can:corpwalletmanager.settings');

    Route::get('/settings/access-logs', 'SettingsController@getAccessLogs')
        ->name('corpwalletmanager.settings.access-logs')
        ->middleware('can:corpwalletmanager.settings');
    
    // Route for getting selected corporation settings
    Route::get('/api/selected-corporation', 'SettingsController@getSelectedCorporation')
        ->name('corpwalletmanager.selected-corporation')
        ->middleware('can:corpwalletmanager.view');

});
