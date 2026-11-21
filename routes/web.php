<?php

use App\Http\Controllers\GroupInvitationController;
use App\Http\Controllers\GroupJoinController;
use Illuminate\Support\Facades\Route;

// Until the signed-in dashboard exists, signed-in visitors land on the hill list.
Route::get('/', fn () => auth()->check() ? redirect()->route('hills.index') : view('welcome'))->name('home');

Route::redirect('dashboard', '/')->name('dashboard');

Route::livewire('hills', 'pages::hills')->name('hills.index');
Route::livewire('hills/{hill:slug}', 'pages::hill-show')->name('hills.show');

// Links people are sent: a group's reusable link, and an admin's emailed invitation. Opening
// either changes nothing; only pressing a button does. They are throttled because they let
// someone with no account create one.
Route::middleware('throttle:group-join')->group(function () {
    Route::get('join/{token}', [GroupJoinController::class, 'show'])->name('groups.join');
    Route::post('join/{token}', [GroupJoinController::class, 'store'])->name('groups.join.store');

    Route::get('invitations/{token}', [GroupInvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/register', [GroupInvitationController::class, 'register'])->name('invitations.register');
    Route::post('invitations/{token}/accept', [GroupInvitationController::class, 'accept'])
        ->middleware('auth')
        ->name('invitations.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('groups', 'pages::groups')->name('groups.index');
    Route::livewire('groups/{group}', 'pages::group-show')->name('groups.show');
});

require __DIR__.'/settings.php';
