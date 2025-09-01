<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Seat\CorpWalletManager\Http\Controllers',
    'middleware' => ['web', 'auth', 'locale'],
    'prefix' => 'corp-wallet-manager',
], function () {
    
    // Main views - using new permission format
    Route::get('/director', 'WalletController@director')
        ->name('corpwalletmanager.director')
        ->middleware('can:corpwalletmanager.director_view');
    
    Route::get('/member', 'WalletController@member')
        ->name('corpwalletmanager.member')
        ->middleware('can:corpwalletmanager.member_view');
    
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
});
