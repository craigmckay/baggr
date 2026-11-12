<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('hills', 'pages::hills')->name('hills.index');
Route::livewire('hills/{hill:slug}', 'pages::hill-show')->name('hills.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
