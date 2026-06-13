<?php

use App\Models\Fixture;
use Illuminate\Support\Carbon;

it('assigns week 1 to a fixture on the tournament start date', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => Carbon::parse('2026-06-11')]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBe(1);
});

it('assigns week 1 to a fixture on the last day of the first week', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => Carbon::parse('2026-06-17')]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBe(1);
});

it('assigns week 2 to a fixture on the first day of the second week', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => Carbon::parse('2026-06-18')]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBe(2);
});

it('assigns week 6 to the final on 2026-07-19', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => Carbon::parse('2026-07-19')]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBe(6);
});

it('leaves week_number null for fixtures with no scheduled_at', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => null]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBeNull();
});

it('is idempotent when run multiple times', function () {
    $fixture = Fixture::factory()->create(['scheduled_at' => Carbon::parse('2026-06-11')]);

    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();
    $this->artisan('world-cup:assign-fixture-weeks')->assertSuccessful();

    expect($fixture->fresh()->week_number)->toBe(1);
});
