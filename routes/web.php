<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::main-dashboard.main-dashboard')->name('dashboard');
    Route::livewire('projects', 'pages::main-dashboard.projects')->name('projects');
    // dashboard
    Route::livewire('projects/{id}', 'pages::dashboard.overview')->name('projects.overview');
    Route::livewire('projects/{id}/repositories', 'pages::dashboard.repositories')->name('projects.repositories');
    Route::livewire('projects/{id}/runs', 'pages::dashboard.runs')->name('projects.runs');
    Route::livewire('projects/{id}/settings', 'pages::dashboard.settings')->name('projects.settings');
    Route::livewire('projects/{id}/tests', 'pages::dashboard.tests')->name('projects.tests');
    Route::livewire('projects/{id}/connectors', 'pages::dashboard.connectors')->name('projects.connectors');
});

require __DIR__.'/settings.php';
