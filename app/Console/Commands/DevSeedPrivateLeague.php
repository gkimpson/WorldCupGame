<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Enums\LeagueMemberRole;
use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevSeedPrivateLeague extends Command
{
    protected $signature = 'dev:seed-private-league';

    protected $description = 'Seed a private league with named members, predictions, and scored fixtures (development only)';

    private const LEAGUE_NAME = 'Black Banter';

    /** @var array<string, string> name => email */
    private const MEMBERS = [
        'BiGz' => 'bigz@dev.test',
        'McDon' => 'mcdon@dev.test',
        'Scrams' => 'scrams@dev.test',
        'Bilal' => 'bilal@dev.test',
        'Leon' => 'leon@dev.test',
        'Az' => 'az@dev.test',
        'Jordz' => 'jordz@dev.test',
        'Izz' => 'izz@dev.test',
        'Tyrone' => 'tyrone@dev.test',
        'Kevin' => 'kevin@dev.test',
        'Serendipity' => 'serendipity@dev.test',
        'Ada' => 'ada@dev.test',
        'Luke' => 'luke@dev.test',
    ];

    public function handle(
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): int {
        $this->reset();

        $owner = $this->resolveOwner();
        $members = $this->seedMembers();
        $allUsers = $members->prepend($owner);

        $league = $this->createLeague($owner, $allUsers);

        $fixtures = Fixture::where('match_number', '<=', 72)->get();

        $this->seedPredictions($allUsers, $fixtures);
        $this->scoreFixtures($fixtures, $scorePredictions, $recalculateStats);
        $this->printLeaderboard($league);

        return self::SUCCESS;
    }

    private function reset(): void
    {
        $this->info('Resetting previous dev league data...');

        $memberEmails = array_values(self::MEMBERS);

        $memberIds = User::whereIn('email', $memberEmails)->pluck('id');

        Prediction::whereIn('user_id', $memberIds)->delete();
        UserStat::whereIn('user_id', $memberIds)->delete();
        User::whereIn('email', $memberEmails)->delete();

        League::where('name', self::LEAGUE_NAME)->each(function (League $league): void {
            LeagueMember::where('league_id', $league->id)->delete();
            $league->delete();
        });
    }

    private function resolveOwner(): User
    {
        $owner = User::where('email', 'gkimpson@gmail.com')->first();

        if ($owner === null) {
            $owner = User::factory()->create([
                'name' => 'Gavin',
                'email' => 'gkimpson@gmail.com',
            ]);
        }

        // Reset Gavin's predictions/stats so they reflect only this league's data
        Prediction::where('user_id', $owner->id)->delete();
        UserStat::where('user_id', $owner->id)->delete();

        return $owner;
    }

    /** @return Collection<int, User> */
    private function seedMembers(): Collection
    {
        $this->info('Creating '.count(self::MEMBERS).' members...');

        foreach (self::MEMBERS as $name => $email) {
            User::factory()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
            ]);
        }

        return User::whereIn('email', array_values(self::MEMBERS))->get();
    }

    /** @param Collection<int, User> $allUsers */
    private function createLeague(User $owner, Collection $allUsers): League
    {
        $this->info('Creating league "'.self::LEAGUE_NAME.'"...');

        $league = League::create([
            'owner_user_id' => $owner->id,
            'name' => self::LEAGUE_NAME,
            'join_code' => League::generateJoinCode(),
        ]);

        $now = now();

        $rows = $allUsers->map(fn (User $user) => [
            'id' => Str::ulid(),
            'league_id' => $league->id,
            'user_id' => $user->id,
            'role' => $user->id === $owner->id ? LeagueMemberRole::Owner->value : LeagueMemberRole::Member->value,
            'joined_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('league_members')->insert($rows);

        return $league;
    }

    /**
     * @param  Collection<int, User>  $allUsers
     * @param  Collection<int, Fixture>  $fixtures
     */
    private function seedPredictions(Collection $allUsers, Collection $fixtures): void
    {
        $count = $allUsers->count() * $fixtures->count();
        $this->info("Seeding {$count} predictions...");

        $rows = [];
        $now = now();

        foreach ($fixtures as $fixture) {
            foreach ($allUsers as $user) {
                $rows[] = [
                    'id' => Str::ulid(),
                    'user_id' => $user->id,
                    'fixture_id' => $fixture->id,
                    'home_score' => fake()->numberBetween(0, 5),
                    'away_score' => fake()->numberBetween(0, 5),
                    'points' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Prediction::insert($chunk);
        }
    }

    /** @param Collection<int, Fixture> $fixtures */
    private function scoreFixtures(
        Collection $fixtures,
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): void {
        $this->info('Scoring 72 fixtures...');

        $bar = $this->output->createProgressBar($fixtures->count());
        $bar->start();

        foreach ($fixtures as $fixture) {
            $fixture->update([
                'home_score' => fake()->numberBetween(0, 4),
                'away_score' => fake()->numberBetween(0, 4),
                'status' => FixtureStatus::Completed,
            ]);

            $event = new ResultImported($fixture->fresh());
            $scorePredictions->handle($event);
            $recalculateStats->handle($event);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    private function printLeaderboard(League $league): void
    {
        $this->newLine();
        $this->info('"'.self::LEAGUE_NAME.'" Leaderboard (join code: '.$league->join_code.')');
        $this->newLine();

        $entries = LeagueMember::with(['user.stat'])
            ->where('league_id', $league->id)
            ->get()
            ->sortByDesc(fn (LeagueMember $m) => $m->user->stat?->total_points ?? 0)
            ->values()
            ->map(fn (LeagueMember $m, int $i) => [
                (string) ($i + 1),
                $m->user->name.($m->role === LeagueMemberRole::Owner ? ' 👑' : ''),
                (string) ($m->user->stat?->total_points ?? 0),
                ($m->user->stat?->predictions_made ?? 0).' / '.Fixture::TOTAL_WORLD_CUP_MATCHES,
            ]);

        $this->table(['#', 'Player', 'Points', 'Scored'], $entries);
    }
}
