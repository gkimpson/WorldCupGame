<?php

use App\Enums\PlayerPosition;
use App\Models\Player;
use App\Models\Team;
use Database\Seeders\SquadSeeder;

/**
 * @param  array<int, array<string, mixed>>  $data
 */
function writeSquadFixture(array $data): string
{
    $path = sys_get_temp_dir().'/squads_'.uniqid().'.json';
    file_put_contents($path, json_encode($data));

    return $path;
}

it('seeds teams and players from a json file', function () {
    $path = writeSquadFixture([
        [
            'name' => 'Brazil',
            'code' => 'BRA',
            'group' => 'A',
            'flag_code' => 'br',
            'players' => [
                ['name' => 'Alisson', 'position' => 'Goalkeeper', 'shirt_number' => 1],
                ['name' => 'Neymar', 'position' => 'Forward', 'shirt_number' => 10],
            ],
        ],
    ]);

    (new SquadSeeder)->seedFromFile($path);

    expect(Team::count())->toBe(1)
        ->and(Player::count())->toBe(2);

    $brazil = Team::firstWhere('code', 'BRA');
    expect($brazil->name)->toBe('Brazil')
        ->and($brazil->slug)->toBe('brazil')
        ->and($brazil->group)->toBe('A')
        ->and($brazil->flag_code)->toBe('br');

    $alisson = Player::firstWhere('name', 'Alisson');
    expect($alisson->position)->toBe(PlayerPosition::Goalkeeper)
        ->and($alisson->shirt_number)->toBe(1)
        ->and($alisson->team->is($brazil))->toBeTrue();
});

it('is idempotent across repeated runs', function () {
    $path = writeSquadFixture([
        [
            'name' => 'Brazil',
            'code' => 'BRA',
            'players' => [
                ['name' => 'Alisson', 'position' => 'Goalkeeper'],
            ],
        ],
    ]);

    (new SquadSeeder)->seedFromFile($path);
    (new SquadSeeder)->seedFromFile($path);

    expect(Team::count())->toBe(1)
        ->and(Player::count())->toBe(1);
});

it('committed squad data includes a blade flags code for every team', function () {
    /** @var array<int, array{name: string, flag_code: string|null}> $squads */
    $squads = json_decode((string) file_get_contents(resource_path('data/squads.json')), true, flags: JSON_THROW_ON_ERROR);

    $teamsMissingFlags = collect($squads)
        ->filter(fn (array $squad): bool => blank($squad['flag_code'] ?? null))
        ->pluck('name');

    expect($teamsMissingFlags)->toBeEmpty();
});

it('committed squad data only references blade flags icons that exist', function () {
    /** @var array<int, array{name: string, flag_code: string}> $squads */
    $squads = json_decode((string) file_get_contents(resource_path('data/squads.json')), true, flags: JSON_THROW_ON_ERROR);

    $teamsMissingIcons = collect($squads)
        ->filter(fn (array $squad): bool => ! is_file(base_path("vendor/outhebox/blade-flags/resources/svg/country-{$squad['flag_code']}.svg")))
        ->pluck('name');

    expect($teamsMissingIcons)->toBeEmpty();
});
