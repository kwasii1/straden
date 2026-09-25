<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::livewire('setup', 'pages::auth.setup')->middleware('guest')->name('setup');

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::main-dashboard.main-dashboard')->name('dashboard');
    Route::livewire('notifications', 'pages::main-dashboard.notifications')->name('notifications');
    Route::livewire('projects', 'pages::main-dashboard.projects')->name('projects');
    // dashboard
    Route::livewire('projects/{project:slug}', 'pages::dashboard.overview')->name('projects.overview');
    Route::livewire('projects/{project:slug}/repositories', 'pages::dashboard.repositories')->name('projects.repositories');
    Route::livewire('projects/{project:slug}/repositories/{repository}', 'pages::dashboard.repository-browse')->name('projects.repository-browse');
    Route::livewire('projects/{project:slug}/runs', 'pages::dashboard.runs')->name('projects.runs');
    Route::livewire('projects/{project:slug}/runs/{run}', 'pages::dashboard.view-run')->name('projects.runs.view');
    Route::livewire('projects/{project:slug}/git-providers', 'pages::dashboard.git-providers')->name('projects.git-providers');
    Route::livewire('projects/{project:slug}/git-providers/{connector}/repositories', 'pages::dashboard.repository-picker')->name('projects.repository-picker');
    Route::livewire('projects/{project:slug}/tests', 'pages::dashboard.tests')->name('projects.tests');
    Route::livewire('projects/{project:slug}/connectors', 'pages::dashboard.connectors')->name('projects.connectors');
    Route::livewire('projects/{project:slug}/new-test', 'pages::dashboard.new-test')->name('projects.new-test');
    // view-test
    Route::livewire('projects/{project:slug}/test/{test:slug}', 'pages::dashboard.view-test')->name('projects.view-test');
    Route::livewire('projects/{project:slug}/test/{test:slug}/{script:slug}', 'pages::dashboard.view-test-script')->name('projects.view-test-script');
});

require __DIR__.'/settings.php';
