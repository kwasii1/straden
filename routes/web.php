<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::main-dashboard.main-dashboard')->name('dashboard');
    Route::livewire('projects', 'pages::main-dashboard.projects')->name('projects');
    // dashboard
    Route::livewire('projects/{slug}', 'pages::dashboard.overview')->name('projects.overview');
    Route::livewire('projects/{slug}/repositories', 'pages::dashboard.repositories')->name('projects.repositories');
    Route::livewire('projects/{slug}/runs', 'pages::dashboard.runs')->name('projects.runs');
    Route::livewire('projects/{slug}/settings', 'pages::dashboard.settings')->name('projects.settings');
    Route::livewire('projects/{slug}/tests', 'pages::dashboard.tests')->name('projects.tests');
    Route::livewire('projects/{slug}/connectors', 'pages::dashboard.connectors')->name('projects.connectors');
    Route::livewire('projects/{slug}/new-test', 'pages::dashboard.new-test')->name('projects.new-test');
    // view-test
    Route::livewire('projects/{slug}/test/{test_slug}', 'pages::dashboard.view-test')->name('projects.view-test');
});

require __DIR__.'/settings.php';
