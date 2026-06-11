<?php

use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Livewire\Fixtures\IndexFixtures;
use App\Livewire\Fixtures\ShowFixture;
use App\Livewire\League\MyLeagues;
use App\Livewire\League\ShowLeague;
use App\Livewire\Predictions\SubmitPredictions;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/leaderboard', GlobalLeaderboard::class)->name('leaderboard.global');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('fixtures', IndexFixtures::class)->name('fixtures.index');
    Route::livewire('fixtures/{fixture}', ShowFixture::class)->name('fixtures.show');
    Route::livewire('predictions', SubmitPredictions::class)->name('predictions.index');
    Route::livewire('leagues', MyLeagues::class)->name('leagues.index');
    Route::livewire('leagues/{league}', ShowLeague::class)->name('leagues.show');
});

require __DIR__.'/settings.php';
