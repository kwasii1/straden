<?php

use App\Http\Middleware\RememberSettingsReturnUrl;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');

    Route::middleware(RememberSettingsReturnUrl::class)->group(function () {
        Route::livewire('settings/api-tokens', 'pages::settings.api-tokens')->name('settings.api-tokens');

        // Instance-wide configuration: admins only.
        Route::middleware('admin')->group(function () {
            Route::livewire('settings/ai-integrations', 'pages::settings.ai-integrations')->name('settings.ai-integrations');
            Route::livewire('settings/insights-model', 'pages::settings.insights-model')->name('settings.insights-model');
            Route::livewire('settings/users', 'pages::settings.users')->name('settings.users');
        });
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
