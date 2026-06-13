<?php

use App\Livewire\Dashboard;
use App\Livewire\Fixtures\IndexFixtures;
use App\Livewire\Fixtures\ShowFixture;
use App\Livewire\Leaderboard\AccuracyLeaderboard;
use App\Livewire\Leaderboard\BiggestMovers;
use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Livewire\Leaderboard\PerfectLeaderboard;
use App\Livewire\League\MyLeagues;
use App\Livewire\League\ShowLeague;
use App\Livewire\Predictions\SubmitPredictions;
use App\Livewire\Users\CompareUsers;
use App\Livewire\Users\ShowProfile;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/users/{user}', ShowProfile::class)->name('users.show');

Route::livewire('/compare', CompareUsers::class)->name('users.compare');
Route::livewire('/compare/{userA}/{userB}', CompareUsers::class)->name('users.compare.show');

Route::livewire('/leaderboard', GlobalLeaderboard::class)->name('leaderboard.global');
Route::livewire('/leaderboard/accuracy', AccuracyLeaderboard::class)->name('leaderboard.accuracy');
Route::livewire('/leaderboard/perfect', PerfectLeaderboard::class)->name('leaderboard.perfect');
Route::livewire('/leaderboard/movers', BiggestMovers::class)->name('leaderboard.movers');

Route::middleware(['auth'])->group(function () {
    Route::impersonate();
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
    Route::livewire('fixtures', IndexFixtures::class)->name('fixtures.index');
    Route::livewire('fixtures/{fixture}', ShowFixture::class)->name('fixtures.show');
    Route::livewire('predictions', SubmitPredictions::class)->name('predictions.index');
    Route::livewire('leagues', MyLeagues::class)->name('leagues.index');
    Route::livewire('leagues/{league}', ShowLeague::class)->name('leagues.show');
});

require __DIR__.'/settings.php';
