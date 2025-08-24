<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Seat\CorpWalletManager\Http\Controllers',
    'middleware' => ['web', 'auth'],
    'prefix' => 'corp-wallet-manager',
], function () {
    
    // Main views
    Route::get('/director', 'WalletController@director')
        ->name('corpwalletmanager.director')
        ->middleware('can:corporation.wallet.view');
    
    Route::get('/member', 'WalletController@member')
        ->name('corpwalletmanager.member')
        ->middleware('can:corporation.member.view');
    
    // API endpoints for data
    Route::get('/api/latest', 'WalletController@latest')
        ->name('corpwalletmanager.latest')
        ->middleware('can:corporation.wallet.view');
    
    Route::get('/api/monthly-comparison', 'WalletController@monthlyComparison')
        ->name('corpwalletmanager.monthly')
        ->middleware('can:corporation.wallet.view');
        
    Route::get('/api/predictions', 'WalletController@predictions')
        ->name('corpwalletmanager.predictions')
        ->middleware('can:corporation.wallet.view');
        
    Route::get('/api/division-breakdown', 'WalletController@divisionBreakdown')
        ->name('corpwalletmanager.divisions')
        ->middleware('can:corporation.wallet.view');
        
    Route::get('/api/summary', 'WalletController@summary')
        ->name('corpwalletmanager.summary')
        ->middleware('can:corporation.wallet.view');
    
    // Settings routes
    Route::get('/settings', 'SettingsController@index')
        ->name('corpwalletmanager.settings')
        ->middleware('can:corporation.wallet.view');
    
    Route::post('/settings', 'SettingsController@update')
        ->name('corpwalletmanager.settings.update')
        ->middleware('can:corporation.wallet.view');
    
    Route::post('/settings/reset', 'SettingsController@reset')
        ->name('corpwalletmanager.settings.reset')
        ->middleware('can:corporation.wallet.view');
    
    // Maintenance routes
    Route::post('/settings/backfill', 'SettingsController@triggerBackfill')
        ->name('corpwalletmanager.settings.backfill')
        ->middleware('can:corporation.wallet.view');
    
    Route::post('/settings/prediction', 'SettingsController@triggerPrediction')
        ->name('corpwalletmanager.settings.prediction')
        ->middleware('can:corporation.wallet.view');
});
