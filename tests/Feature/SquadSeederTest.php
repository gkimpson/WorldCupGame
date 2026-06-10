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
        ->and($brazil->group)->toBe('A');

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
