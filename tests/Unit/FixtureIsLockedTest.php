<?php

use App\Models\Fixture;
use Tests\TestCase;

uses(TestCase::class);

it('is not locked when scheduled_at is null', function () {
    $fixture = new Fixture(['scheduled_at' => null]);

    expect($fixture->isLocked())->toBeFalse();
});

it('is not locked when kickoff is beyond the lock window', function () {
    $fixture = new Fixture(['scheduled_at' => now()->addHours(3)]);

    expect($fixture->isLocked())->toBeFalse();
});

it('is locked when kickoff is within the lock window', function () {
    $fixture = new Fixture(['scheduled_at' => now()->addHour()]);

    expect($fixture->isLocked())->toBeTrue();
});

it('is locked when kickoff has already passed', function () {
    $fixture = new Fixture(['scheduled_at' => now()->subHour()]);

    expect($fixture->isLocked())->toBeTrue();
});

it('is locked when the is_locked column is true regardless of kickoff time', function () {
    $fixture = new Fixture(['scheduled_at' => now()->addDays(10), 'is_locked' => true]);

    expect($fixture->isLocked())->toBeTrue();
});

it('is not locked when is_locked column is false and kickoff is far away', function () {
    $fixture = new Fixture(['scheduled_at' => now()->addDays(10), 'is_locked' => false]);

    expect($fixture->isLocked())->toBeFalse();
});

it('respects a custom lock window from config', function () {
    config(['predictions.lock_minutes_before_kickoff' => 60]);

    $justOutsideWindow = new Fixture(['scheduled_at' => now()->addMinutes(61)]);
    $justInsideWindow = new Fixture(['scheduled_at' => now()->addMinutes(59)]);

    expect($justOutsideWindow->isLocked())->toBeFalse()
        ->and($justInsideWindow->isLocked())->toBeTrue();
});
