<?php

use Illuminate\Support\Facades\Route;

// Until the signed-in dashboard exists, signed-in visitors land on the hill list.
Route::get('/', fn () => auth()->check() ? redirect()->route('hills.index') : view('welcome'))->name('home');

Route::redirect('dashboard', '/')->name('dashboard');

Route::livewire('hills', 'pages::hills')->name('hills.index');
Route::livewire('hills/{hill:slug}', 'pages::hill-show')->name('hills.show');

require __DIR__.'/settings.php';
