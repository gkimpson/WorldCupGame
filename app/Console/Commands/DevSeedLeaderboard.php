<?php

namespace App\Console\Commands;

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DevSeedLeaderboard extends Command
{
    protected $signature = 'dev:seed-leaderboard';

    protected $description = 'Reset and seed 20 users with 72 predictions each, then score the leaderboard (development only)';

    public function handle(
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): int {
        $this->reset();

        $users = $this->seedUsers();
        $fixtures = Fixture::where('match_number', '<=', 72)->get();

        $this->seedPredictions($users, $fixtures);
        $this->scoreFixtures($fixtures, $scorePredictions, $recalculateStats);
        $this->printLeaderboard();

        return self::SUCCESS;
    }

    private function reset(): void
    {
        $this->info('Resetting...');

        UserStat::query()->delete();
        Prediction::query()->delete();
        User::where('email', '!=', 'gkimpson@gmail.com')->delete();
    }

    /** @return Collection<int, User> */
    private function seedUsers(): Collection
    {
        $this->info('Creating 20 users...');

        User::factory()->count(20)->create();

        return User::where('email', '!=', 'gkimpson@gmail.com')->get();
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Fixture>  $fixtures
     */
    private function seedPredictions(
        Collection $users,
        Collection $fixtures,
    ): void {
        $this->info('Seeding 1,440 predictions...');

        $rows = [];
        $now = now();

        foreach ($fixtures as $fixture) {
            foreach ($users as $user) {
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

    /**
     * @param  Collection<int, Fixture>  $fixtures
     */
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
                'home_score' => fake()->numberBetween(0, 5),
                'away_score' => fake()->numberBetween(0, 5),
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

    private function printLeaderboard(): void
    {
        $this->newLine();
        $this->info('Leaderboard (Top 20)');
        $this->newLine();

        $entries = UserStat::with('user')
            ->orderBy('total_points', 'desc')
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get()
            ->map(fn (UserStat $stat, int $i) => [
                (string) ($i + 1),
                $stat->user->name,
                (string) $stat->total_points,
                $stat->predictions_made.' / 104',
            ]);

        $this->table(['#', 'Player', 'Points', 'Scored'], $entries);
    }
}
