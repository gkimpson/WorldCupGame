# Dev Seed Leaderboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `php artisan dev:seed-leaderboard` — a development command that resets and populates the database with 20 users, 1,440 predictions across 72 fixtures, and a fully scored leaderboard in a single invocation.

**Architecture:** A single Artisan command class handles the full flow: destructive reset (preserve admin), user + prediction seeding, synchronous scoring via direct listener calls (no queue worker needed), and a top-20 leaderboard summary printed to the console.

**Tech Stack:** Laravel 13, PHP 8.3, Pest 4, MySQL 8

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `app/Console/Commands/DevSeedLeaderboard.php` | The command |
| Create | `tests/Feature/DevSeedLeaderboardTest.php` | Feature tests |

---

## Task 1: DevSeedLeaderboard Command

**Files:**
- Create: `app/Console/Commands/DevSeedLeaderboard.php`
- Create: `tests/Feature/DevSeedLeaderboardTest.php`

### Context for the implementer

- **Admin email to preserve:** `gkimpson@gmail.com`
- **Fixtures to use:** the 72 rows where `match_number <= 72` (already seeded)
- **Listeners to call directly** (bypassing queue):
  - `App\Listeners\RecalculateFixturePredictions::handle(ResultImported $event)`
  - `App\Listeners\RecalculateUserStats::handle(ResultImported $event)`
- **`RecalculateFixturePredictions`** is constructor-injected with `FixturePredictionScorer` — resolve it via `app()`.
- **Scores:** random integers 0–5 for both `home_score` and `away_score`.
- **`FixtureStatus::Completed`** lives at `App\Enums\FixtureStatus`.

---

- [ ] **Step 1: Create the test file**

```bash
php artisan make:test --pest DevSeedLeaderboardTest
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/DevSeedLeaderboardTest.php`:
```php
<?php

use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create(['email' => 'gkimpson@gmail.com']);
    $admin->assignRole('admin');
});

it('exits successfully', function () {
    $this->artisan('dev:seed-leaderboard')->assertExitCode(0);
});

it('creates exactly 20 non-admin users', function () {
    $this->artisan('dev:seed-leaderboard');

    expect(User::count())->toBe(21)
        ->and(User::where('email', 'gkimpson@gmail.com')->exists())->toBeTrue();
});

it('creates 1440 predictions', function () {
    $this->artisan('dev:seed-leaderboard');

    expect(Prediction::count())->toBe(1440);
});

it('scores all predictions', function () {
    $this->artisan('dev:seed-leaderboard');

    expect(Prediction::whereNull('points')->count())->toBe(0);
});

it('creates a user_stat row for every non-admin user', function () {
    $this->artisan('dev:seed-leaderboard');

    expect(UserStat::count())->toBe(20);
});

it('outputs a leaderboard', function () {
    $this->artisan('dev:seed-leaderboard')
        ->expectsOutputToContain('Leaderboard');
});

it('is idempotent — running twice still yields 20 users and 1440 predictions', function () {
    $this->artisan('dev:seed-leaderboard');
    $this->artisan('dev:seed-leaderboard');

    expect(User::count())->toBe(21)
        ->and(Prediction::count())->toBe(1440)
        ->and(UserStat::count())->toBe(20);
});
```

- [ ] **Step 3: Run to confirm they fail**

```bash
php artisan test --compact tests/Feature/DevSeedLeaderboardTest.php
```

Expected: failures — command `dev:seed-leaderboard` not found.

- [ ] **Step 4: Generate the command**

```bash
php artisan make:command DevSeedLeaderboard --no-interaction
```

- [ ] **Step 5: Write the command**

`app/Console/Commands/DevSeedLeaderboard.php`:
```php
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

    /** @return \Illuminate\Database\Eloquent\Collection<int, User> */
    private function seedUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $this->info('Creating 20 users...');

        User::factory()->count(20)->create();

        return User::where('email', '!=', 'gkimpson@gmail.com')->get();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users
     * @param \Illuminate\Database\Eloquent\Collection<int, Fixture> $fixtures
     */
    private function seedPredictions(
        \Illuminate\Database\Eloquent\Collection $users,
        \Illuminate\Database\Eloquent\Collection $fixtures,
    ): void {
        $this->info('Seeding 1,440 predictions...');

        $rows = [];
        $now = now();

        foreach ($fixtures as $fixture) {
            foreach ($users as $user) {
                $rows[] = [
                    'id' => \Illuminate\Support\Str::ulid(),
                    'user_id' => $user->id,
                    'fixture_id' => $fixture->id,
                    'home_score' => rand(0, 5),
                    'away_score' => rand(0, 5),
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
     * @param \Illuminate\Database\Eloquent\Collection<int, Fixture> $fixtures
     */
    private function scoreFixtures(
        \Illuminate\Database\Eloquent\Collection $fixtures,
        RecalculateFixturePredictions $scorePredictions,
        RecalculateUserStats $recalculateStats,
    ): void {
        $this->info('Scoring 72 fixtures...');

        $bar = $this->output->createProgressBar($fixtures->count());
        $bar->start();

        foreach ($fixtures as $fixture) {
            $fixture->update([
                'home_score' => rand(0, 5),
                'away_score' => rand(0, 5),
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
                $stat->predictions_made . ' / 72',
            ]);

        $this->table(['#', 'Player', 'Points', 'Scored'], $entries);
    }
}
```

- [ ] **Step 6: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/DevSeedLeaderboardTest.php
```

Expected: 7 passed.

- [ ] **Step 7: Run the full test suite to check for regressions**

```bash
php artisan test --compact
```

Expected: all tests pass (1 pre-existing skip).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Console/Commands/DevSeedLeaderboard.php tests/Feature/DevSeedLeaderboardTest.php
git commit -m "add dev:seed-leaderboard command"
```

- [ ] **Step 9: Smoke-test it manually**

```bash
php artisan dev:seed-leaderboard
```

Expected output:
```
Resetting...
Creating 20 users...
Seeding 1,440 predictions...
Scoring 72 fixtures...
[████████████████████] 72/72

Leaderboard (Top 20)
+----+------------------+--------+---------+
| #  | Player           | Points | Scored  |
+----+------------------+--------+---------+
| 1  | ...              | ...    | xx / 72 |
...
+----+------------------+--------+---------+
```
