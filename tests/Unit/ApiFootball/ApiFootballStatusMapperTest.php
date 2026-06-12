<?php

use App\Enums\FixtureStatus;
use App\Services\ApiFootball\ApiFootballStatusMapper;

it('maps NS and TBD to scheduled', function (string $code): void {
    expect((new ApiFootballStatusMapper)->map($code))->toBe(FixtureStatus::Scheduled);
})->with(['NS', 'TBD']);

it('maps FT, AET, PEN to completed', function (string $code): void {
    expect((new ApiFootballStatusMapper)->map($code))->toBe(FixtureStatus::Completed);
})->with(['FT', 'AET', 'PEN']);

it('maps live status codes to in_progress', function (string $code): void {
    expect((new ApiFootballStatusMapper)->map($code))->toBe(FixtureStatus::InProgress);
})->with(['LIVE', 'HT', '1H', '2H', 'ET', 'BT', 'P', 'SUSP']);

it('maps PST, CANC, ABD to postponed', function (string $code): void {
    expect((new ApiFootballStatusMapper)->map($code))->toBe(FixtureStatus::Postponed);
})->with(['PST', 'CANC', 'ABD']);

it('falls back to scheduled for unknown codes', function (): void {
    expect((new ApiFootballStatusMapper)->map('UNKNOWN'))->toBe(FixtureStatus::Scheduled);
});
