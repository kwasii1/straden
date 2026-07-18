<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::main-dashboard.main-dashboard')->name('dashboard');
    Route::livewire('projects', 'pages::main-dashboard.projects')->name('projects');
    // dashboard
    Route::livewire('projects/{project:slug}', 'pages::dashboard.overview')->name('projects.overview');
    Route::livewire('projects/{project:slug}/repositories', 'pages::dashboard.repositories')->name('projects.repositories');
    Route::livewire('projects/{project:slug}/runs', 'pages::dashboard.runs')->name('projects.runs');
    Route::livewire('projects/{project:slug}/settings', 'pages::dashboard.settings')->name('projects.settings');
    Route::livewire('projects/{project:slug}/tests', 'pages::dashboard.tests')->name('projects.tests');
    Route::livewire('projects/{project:slug}/connectors', 'pages::dashboard.connectors')->name('projects.connectors');
    Route::livewire('projects/{project:slug}/new-test', 'pages::dashboard.new-test')->name('projects.new-test');
    // view-test
    Route::livewire('projects/{project:slug}/test/{test:slug}', 'pages::dashboard.view-test')->name('projects.view-test');
    Route::livewire('projects/{project:slug}/test/{test:slug}/{script:slug}', 'pages::dashboard.view-test-script')->name('projects.view-test-script');
});

require __DIR__.'/settings.php';
