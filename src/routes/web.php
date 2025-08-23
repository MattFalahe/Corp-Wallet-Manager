<?php
use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'Seat\CorpWalletManager\Http\Controllers',
    'middleware' => ['web', 'auth'],
    'prefix' => 'corp-wallet-manager',
], function () {
    Route::get('/director', 'WalletController@director')->name('corpwalletmanager.director')->middleware('can:corporation.wallet.view');
    Route::get('/member', 'WalletController@member')->name('corpwalletmanager.member')->middleware('can:corporation.member.view');

    Route::get('/latest', 'WalletController@latest')->name('corpwalletmanager.latest');
    Route::get('/monthly-comparison', 'WalletController@monthlyComparison')->name('corpwalletmanager.monthly');

    Route::get('/settings', 'SettingsController@index')->name('corpwalletmanager.settings');
    Route::post('/settings', 'SettingsController@update')->name('corpwalletmanager.settings.update');
    Route::post('/settings/reset', 'SettingsController@reset')->name('corpwalletmanager.settings.reset');
});
