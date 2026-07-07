<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('dashboard', 'pages::main-dashboard.main-dashboard')->name('dashboard');
    Route::livewire('projects', 'pages::main-dashboard.projects')->name('projects');
    Route::livewire('projects/{id}', 'pages::dashboard.dashboard')->name('project');
});

require __DIR__.'/settings.php';
