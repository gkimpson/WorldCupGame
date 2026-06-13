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
    protected $signature = 'dev:seed-leaderboard {--count=20 : Number of additional users to create}';

    protected $description = 'Reset and seed users with predictions for all 104 fixtures, simulate results, then score the leaderboard (development only)';

    public function handle(
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): int {
        $count = (int) $this->option('count');

        $this->reset();

        $this->seedUsers($count);
        $users = User::where('is_admin', false)->where('is_dummy', false)->get();

        $fixtures = Fixture::orderBy('match_number')->get();

        if ($fixtures->isEmpty()) {
            $this->error('No fixtures found. Run the fixture seeder or sync first.');

            return self::FAILURE;
        }

        $this->seedPredictions($users, $fixtures);
        $this->simulateAndScore($fixtures, $scorePredictions, $recalculateStats);
        $this->printLeaderboard();

        return self::SUCCESS;
    }

    private function reset(): void
    {
        $this->info('Resetting predictions and stats...');

        UserStat::query()->delete();
        Prediction::query()->delete();
        User::where('is_admin', false)
            ->where('is_dummy', false)
            ->where('email', '!=', 'gkimpson@gmail.com')
            ->delete();
    }

    private function seedUsers(int $count): void
    {
        $this->info("Creating {$count} users (is_admin=0, is_dummy=0)...");

        User::factory()->count($count)->create([
            'is_admin' => false,
            'is_dummy' => false,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Fixture>  $fixtures
     */
    private function seedPredictions(Collection $users, Collection $fixtures): void
    {
        $total = $users->count() * $fixtures->count();
        $this->info("Seeding {$total} predictions ({$users->count()} users × {$fixtures->count()} fixtures)...");

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

    /** @param  Collection<int, Fixture>  $fixtures */
    private function simulateAndScore(
        Collection $fixtures,
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): void {
        $this->info("Simulating results and scoring all {$fixtures->count()} fixtures...");

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
                $stat->predictions_made.' / '.Fixture::TOTAL_WORLD_CUP_MATCHES,
                (string) $stat->exact_scores,
            ]);

        $this->table(['#', 'Player', 'Points', 'Scored', 'Exact'], $entries);
    }
}
