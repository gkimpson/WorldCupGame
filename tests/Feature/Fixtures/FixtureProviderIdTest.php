<?php

use App\Models\Fixture;
use Illuminate\Database\QueryException;

it('stores a provider_fixture_id on a fixture', function (): void {
    $fixture = Fixture::factory()->create(['provider_fixture_id' => 99999]);
    expect($fixture->provider_fixture_id)->toBe(99999);
});

it('enforces uniqueness on provider_fixture_id', function (): void {
    Fixture::factory()->create(['provider_fixture_id' => 88888]);
    expect(fn () => Fixture::factory()->create(['provider_fixture_id' => 88888]))
        ->toThrow(QueryException::class);
});
