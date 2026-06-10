# Global Leaderboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a public global leaderboard showing the top 100 users ranked by total prediction points, recalculated whenever fixture results are imported.

**Architecture:** A `predictions` table stores per-user fixture predictions and awarded points. A `user_stats` table stores aggregated totals per user. When a `ResultImported` event fires, two listeners run in sequence: `RecalculateFixturePredictions` scores each prediction via the existing `FixturePredictionScorer`, then `RecalculateUserStats` recomputes totals for affected users. A public Livewire page queries the top 100 and optionally pins the authenticated user's position below.

**Tech Stack:** Laravel 13, Livewire 4, Flux UI v2, Pest 4, MySQL 8

---

## File Map

| Action | Path | Responsibility |
|---|---|---|
| Create | `database/migrations/..._create_predictions_table.php` | `predictions` schema |
| Create | `database/migrations/..._create_user_stats_table.php` | `user_stats` schema |
| Create | `app/Models/Prediction.php` | Prediction model |
| Create | `app/Models/UserStat.php` | UserStat model |
| Create | `database/factories/PredictionFactory.php` | Prediction test factory |
| Create | `database/factories/UserStatFactory.php` | UserStat test factory |
| Create | `app/Events/ResultImported.php` | Event carrying a Fixture |
| Create | `app/Listeners/RecalculateFixturePredictions.php` | Scores predictions for a fixture |
| Create | `app/Listeners/RecalculateUserStats.php` | Recomputes user_stats totals |
| Create | `app/Livewire/Leaderboard/GlobalLeaderboard.php` | Full-page Livewire component |
| Create | `resources/views/livewire/leaderboard/global-leaderboard.blade.php` | Leaderboard view |
| Create | `tests/Feature/Leaderboard/GlobalLeaderboardTest.php` | Leaderboard page tests |
| Create | `tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php` | Listener tests |
| Create | `tests/Feature/Leaderboard/RecalculateUserStatsTest.php` | Listener tests |
| Modify | `app/Providers/AppServiceProvider.php` | Register event listeners |
| Modify | `routes/web.php` | Add leaderboard route |

> **Note:** `Global` is a reserved PHP keyword. The component class is named `GlobalLeaderboard`. The view file is `global-leaderboard.blade.php`.

---

## Task 1: Prediction Model, Migration, and Factory

**Files:**
- Create: `database/migrations/2026_06_10_200000_create_predictions_table.php`
- Create: `app/Models/Prediction.php`
- Create: `database/factories/PredictionFactory.php`
- Create: `tests/Feature/Leaderboard/PredictionModelTest.php`

- [ ] **Step 1: Make the test file**

```bash
php artisan make:test --pest PredictionModelTest
```

- [ ] **Step 2: Write the failing tests**

Replace the contents of `tests/Feature/Leaderboard/PredictionModelTest.php` — first move it:

```bash
mkdir -p tests/Feature/Leaderboard
mv tests/Feature/PredictionModelTest.php tests/Feature/Leaderboard/PredictionModelTest.php
```

`tests/Feature/Leaderboard/PredictionModelTest.php`:
```php
<?php

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;

it('uses a ULID primary key', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->id)->toBeString()
        ->and(strlen($prediction->id))->toBe(26);
});

it('has null points by default', function () {
    $prediction = Prediction::factory()->create();

    expect($prediction->points)->toBeNull();
});

it('enforces unique user and fixture combination', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->create();

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
    ]);

    expect(fn () => Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 3: Run to confirm all three tests fail**

```bash
php artisan test --compact tests/Feature/Leaderboard/PredictionModelTest.php
```

Expected: 3 failures — `Prediction` class not found.

- [ ] **Step 4: Generate the migration**

```bash
php artisan make:migration create_predictions_table --no-interaction
```

Replace its `up()` method:

```php
public function up(): void
{
    Schema::create('predictions', function (Blueprint $table) {
        $table->ulid('id')->primary()->charset('ascii');
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('fixture_id')->constrained()->cascadeOnDelete();
        $table->unsignedTinyInteger('home_score');
        $table->unsignedTinyInteger('away_score');
        $table->unsignedTinyInteger('points')->nullable();
        $table->timestamps();

        $table->unique(['user_id', 'fixture_id']);
    });
}

public function down(): void
{
    Schema::dropIfExists('predictions');
}
```

- [ ] **Step 5: Generate and write the model**

```bash
php artisan make:model Prediction --no-interaction
```

`app/Models/Prediction.php`:
```php
<?php

namespace App\Models;

use Database\Factories\PredictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property int $fixture_id
 * @property int $home_score
 * @property int $away_score
 * @property int|null $points
 */
#[Fillable(['user_id', 'fixture_id', 'home_score', 'away_score', 'points'])]
class Prediction extends Model
{
    /** @use HasFactory<PredictionFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'home_score' => 'integer',
            'away_score' => 'integer',
            'points' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Fixture, $this> */
    public function fixture(): BelongsTo
    {
        return $this->belongsTo(Fixture::class);
    }
}
```

- [ ] **Step 6: Write the factory**

```bash
php artisan make:factory PredictionFactory --model=Prediction --no-interaction
```

`database/factories/PredictionFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'fixture_id' => Fixture::factory(),
            'home_score' => fake()->numberBetween(0, 5),
            'away_score' => fake()->numberBetween(0, 5),
            'points' => null,
        ];
    }

    public function withPoints(int $points): static
    {
        return $this->state(['points' => $points]);
    }
}
```

- [ ] **Step 7: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/Leaderboard/PredictionModelTest.php
```

Expected: 3 passed.

- [ ] **Step 8: Format**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 9: Commit**

```bash
git add database/migrations/*_create_predictions_table.php app/Models/Prediction.php database/factories/PredictionFactory.php tests/Feature/Leaderboard/PredictionModelTest.php
git commit -m "add Prediction model, migration, and factory"
```

---

## Task 2: UserStat Model, Migration, and Factory

**Files:**
- Create: `database/migrations/2026_06_10_200001_create_user_stats_table.php`
- Create: `app/Models/UserStat.php`
- Create: `database/factories/UserStatFactory.php`
- Create: `tests/Feature/Leaderboard/UserStatModelTest.php`

- [ ] **Step 1: Make the test file**

```bash
php artisan make:test --pest UserStatModelTest
mkdir -p tests/Feature/Leaderboard
mv tests/Feature/UserStatModelTest.php tests/Feature/Leaderboard/UserStatModelTest.php
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Leaderboard/UserStatModelTest.php`:
```php
<?php

use App\Models\User;
use App\Models\UserStat;

it('uses a ULID primary key', function () {
    $stat = UserStat::factory()->create();

    expect($stat->id)->toBeString()
        ->and(strlen($stat->id))->toBe(26);
});

it('defaults to zero points and zero predictions', function () {
    $stat = UserStat::factory()->create();

    expect($stat->total_points)->toBe(0)
        ->and($stat->predictions_made)->toBe(0);
});

it('enforces one stat row per user', function () {
    $user = User::factory()->create();

    UserStat::factory()->create(['user_id' => $user->id]);

    expect(fn () => UserStat::factory()->create(['user_id' => $user->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
```

- [ ] **Step 3: Run to confirm they fail**

```bash
php artisan test --compact tests/Feature/Leaderboard/UserStatModelTest.php
```

Expected: 3 failures — `UserStat` class not found.

- [ ] **Step 4: Generate the migration**

```bash
php artisan make:migration create_user_stats_table --no-interaction
```

`up()` method:
```php
public function up(): void
{
    Schema::create('user_stats', function (Blueprint $table) {
        $table->ulid('id')->primary()->charset('ascii');
        $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
        $table->unsignedInteger('total_points')->default(0);
        $table->unsignedSmallInteger('predictions_made')->default(0);
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('user_stats');
}
```

- [ ] **Step 5: Generate and write the model**

```bash
php artisan make:model UserStat --no-interaction
```

`app/Models/UserStat.php`:
```php
<?php

namespace App\Models;

use Database\Factories\UserStatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property int $total_points
 * @property int $predictions_made
 */
#[Fillable(['user_id', 'total_points', 'predictions_made'])]
class UserStat extends Model
{
    /** @use HasFactory<UserStatFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'predictions_made' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 6: Write the factory**

```bash
php artisan make:factory UserStatFactory --model=UserStat --no-interaction
```

`database/factories/UserStatFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStat>
 */
class UserStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_points' => 0,
            'predictions_made' => 0,
        ];
    }

    public function withPoints(int $points, int $predictionsMade = 10): static
    {
        return $this->state([
            'total_points' => $points,
            'predictions_made' => $predictionsMade,
        ]);
    }
}
```

- [ ] **Step 7: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/Leaderboard/UserStatModelTest.php
```

Expected: 3 passed.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*_create_user_stats_table.php app/Models/UserStat.php database/factories/UserStatFactory.php tests/Feature/Leaderboard/UserStatModelTest.php
git commit -m "add UserStat model, migration, and factory"
```

---

## Task 3: ResultImported Event and RecalculateFixturePredictions Listener

**Files:**
- Create: `app/Events/ResultImported.php`
- Create: `app/Listeners/RecalculateFixturePredictions.php`
- Create: `tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Make the test file**

```bash
php artisan make:test --pest RecalculateFixturePredictionsTest
mv tests/Feature/RecalculateFixturePredictionsTest.php tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php`:
```php
<?php

use App\Enums\FixtureStatus;
use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Prediction;

it('awards 3 points for an exact score prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 1,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(3);
});

it('awards 1 point for a correct outcome prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 3,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(1);
});

it('awards 0 points for a wrong prediction', function () {
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 1,
    ]);

    $prediction = Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 0,
        'away_score' => 2,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBe(0);
});

it('leaves points null when the fixture is not yet completed', function () {
    $fixture = Fixture::factory()->create([
        'status' => FixtureStatus::Scheduled,
    ]);

    $prediction = Prediction::factory()->create([
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    expect($prediction->fresh()->points)->toBeNull();
});
```

- [ ] **Step 3: Run to confirm they fail**

```bash
php artisan test --compact tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php
```

Expected: 4 failures — `ResultImported` class not found.

- [ ] **Step 4: Create the event**

```bash
php artisan make:event ResultImported --no-interaction
```

`app/Events/ResultImported.php`:
```php
<?php

namespace App\Events;

use App\Models\Fixture;
use Illuminate\Foundation\Events\Dispatchable;

class ResultImported
{
    use Dispatchable;

    public function __construct(public readonly Fixture $fixture) {}
}
```

- [ ] **Step 5: Create the listener**

```bash
php artisan make:listener RecalculateFixturePredictions --no-interaction
```

`app/Listeners/RecalculateFixturePredictions.php`:
```php
<?php

namespace App\Listeners;

use App\Events\ResultImported;
use App\Models\Prediction;
use App\Services\Scoring\FixturePredictionScorer;

class RecalculateFixturePredictions
{
    public function handle(ResultImported $event): void
    {
        $fixture = $event->fixture;
        $scorer = new FixturePredictionScorer();

        Prediction::where('fixture_id', $fixture->id)
            ->each(function (Prediction $prediction) use ($fixture, $scorer): void {
                $result = $scorer->score($fixture, $prediction->home_score, $prediction->away_score);
                $prediction->points = $result->isScored() ? $result->points : null;
                $prediction->save();
            });
    }
}
```

- [ ] **Step 6: Register the listener in AppServiceProvider**

`app/Providers/AppServiceProvider.php` — add to the top of the file after the existing imports:
```php
use App\Events\ResultImported;
use App\Listeners\RecalculateFixturePredictions;
use App\Listeners\RecalculateUserStats;
use Illuminate\Support\Facades\Event;
```

Inside `boot()`, after `$this->configureDefaults();`:
```php
Event::listen(ResultImported::class, RecalculateFixturePredictions::class, priority: 10);
Event::listen(ResultImported::class, RecalculateUserStats::class, priority: 0);
```

> `RecalculateUserStats` doesn't exist yet — this will be resolved in Task 4. PHP won't error until the event is actually fired.

- [ ] **Step 7: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php
```

Expected: 4 passed.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Events/ResultImported.php app/Listeners/RecalculateFixturePredictions.php app/Providers/AppServiceProvider.php tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php
git commit -m "add ResultImported event and RecalculateFixturePredictions listener"
```

---

## Task 4: RecalculateUserStats Listener

**Files:**
- Create: `app/Listeners/RecalculateUserStats.php`
- Create: `tests/Feature/Leaderboard/RecalculateUserStatsTest.php`

- [ ] **Step 1: Make the test file**

```bash
php artisan make:test --pest RecalculateUserStatsTest
mv tests/Feature/RecalculateUserStatsTest.php tests/Feature/Leaderboard/RecalculateUserStatsTest.php
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Leaderboard/RecalculateUserStatsTest.php`:
```php
<?php

use App\Events\ResultImported;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Models\UserStat;

it('creates a user_stat row when a scored prediction is processed for the first time', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 1,
        'away_score' => 0,
    ]);

    event(new ResultImported($fixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat)->not->toBeNull()
        ->and($stat->total_points)->toBe(3)
        ->and($stat->predictions_made)->toBe(1);
});

it('accumulates points across multiple fixtures', function () {
    $user = User::factory()->create();

    $fixture1 = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);
    $fixture2 = Fixture::factory()->completed()->create([
        'home_score' => 2,
        'away_score' => 2,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture1->id,
    ]);

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture2->id,
    ]);

    event(new ResultImported($fixture2));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat->total_points)->toBe(6)
        ->and($stat->predictions_made)->toBe(2);
});

it('updates an existing user_stat row on re-import', function () {
    $user = User::factory()->create();
    $fixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);

    Prediction::factory()->withPoints(1)->create([
        'user_id' => $user->id,
        'fixture_id' => $fixture->id,
        'home_score' => 2,
        'away_score' => 0,
    ]);

    UserStat::factory()->create([
        'user_id' => $user->id,
        'total_points' => 1,
        'predictions_made' => 1,
    ]);

    // Re-import with updated points on the prediction
    Prediction::where('user_id', $user->id)
        ->where('fixture_id', $fixture->id)
        ->update(['points' => 3, 'home_score' => 1, 'away_score' => 0]);

    event(new ResultImported($fixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat->total_points)->toBe(3)
        ->and($stat->predictions_made)->toBe(1);
});

it('only counts predictions with non-null points toward predictions_made', function () {
    $user = User::factory()->create();

    $scoredFixture = Fixture::factory()->completed()->create([
        'home_score' => 1,
        'away_score' => 0,
    ]);
    $unscoredFixture = Fixture::factory()->create();

    Prediction::factory()->withPoints(3)->create([
        'user_id' => $user->id,
        'fixture_id' => $scoredFixture->id,
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'fixture_id' => $unscoredFixture->id,
        'points' => null,
    ]);

    event(new ResultImported($scoredFixture));

    $stat = UserStat::where('user_id', $user->id)->first();
    expect($stat->predictions_made)->toBe(1);
});
```

- [ ] **Step 3: Run to confirm they fail**

```bash
php artisan test --compact tests/Feature/Leaderboard/RecalculateUserStatsTest.php
```

Expected: 4 failures — `RecalculateUserStats` listener is registered but the class doesn't exist.

- [ ] **Step 4: Create the listener**

```bash
php artisan make:listener RecalculateUserStats --no-interaction
```

`app/Listeners/RecalculateUserStats.php`:
```php
<?php

namespace App\Listeners;

use App\Events\ResultImported;
use App\Models\Prediction;
use App\Models\UserStat;

class RecalculateUserStats
{
    public function handle(ResultImported $event): void
    {
        $userIds = Prediction::where('fixture_id', $event->fixture->id)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $totalPoints = Prediction::where('user_id', $userId)
                ->whereNotNull('points')
                ->sum('points');

            $predictionsMade = Prediction::where('user_id', $userId)
                ->whereNotNull('points')
                ->count();

            UserStat::updateOrCreate(
                ['user_id' => $userId],
                [
                    'total_points' => $totalPoints,
                    'predictions_made' => $predictionsMade,
                ]
            );
        }
    }
}
```

- [ ] **Step 5: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/Leaderboard/RecalculateUserStatsTest.php
```

Expected: 4 passed.

- [ ] **Step 6: Run all leaderboard listener tests together**

```bash
php artisan test --compact tests/Feature/Leaderboard/RecalculateFixturePredictionsTest.php tests/Feature/Leaderboard/RecalculateUserStatsTest.php
```

Expected: 8 passed.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Listeners/RecalculateUserStats.php tests/Feature/Leaderboard/RecalculateUserStatsTest.php
git commit -m "add RecalculateUserStats listener"
```

---

## Task 5: GlobalLeaderboard Livewire Component, View, and Route

**Files:**
- Create: `app/Livewire/Leaderboard/GlobalLeaderboard.php`
- Create: `resources/views/livewire/leaderboard/global-leaderboard.blade.php`
- Create: `tests/Feature/Leaderboard/GlobalLeaderboardTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Make the test file**

```bash
php artisan make:test --pest GlobalLeaderboardTest
mv tests/Feature/GlobalLeaderboardTest.php tests/Feature/Leaderboard/GlobalLeaderboardTest.php
```

- [ ] **Step 2: Write the failing tests**

`tests/Feature/Leaderboard/GlobalLeaderboardTest.php`:
```php
<?php

use App\Livewire\Leaderboard\GlobalLeaderboard;
use App\Models\User;
use App\Models\UserStat;
use Livewire\Livewire;

it('is publicly accessible without authentication', function () {
    $this->get(route('leaderboard.global'))->assertOk();
});

it('shows users ordered by total points descending', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    UserStat::factory()->withPoints(30)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(20)->create(['user_id' => $userB->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSeeInOrder(['Alice', 'Bob']);
});

it('assigns correct ranks starting at 1', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    UserStat::factory()->withPoints(10)->create(['user_id' => $user->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('topEntries', fn ($entries) => $entries[0]['rank'] === 1);
});

it('caps the leaderboard at 100 entries', function () {
    User::factory()->count(101)->create()->each(function (User $user, int $i): void {
        UserStat::factory()->withPoints(200 - $i)->create(['user_id' => $user->id]);
    });

    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('topEntries', fn ($entries) => count($entries) === 100);
});

it('shows no pinned entry for a guest', function () {
    Livewire::test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', null);
});

it('shows no pinned entry when the authenticated user is in the top 100', function () {
    $me = User::factory()->create();
    UserStat::factory()->withPoints(50)->create(['user_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', null);
});

it('pins the authenticated user when they are outside the top 100', function () {
    // Create 100 users with higher points
    User::factory()->count(100)->create()->each(function (User $user, int $i): void {
        UserStat::factory()->withPoints(200 - $i)->create(['user_id' => $user->id]);
    });

    $me = User::factory()->create(['name' => 'Outsider']);
    UserStat::factory()->withPoints(1, 5)->create(['user_id' => $me->id]);

    Livewire::actingAs($me)
        ->test(GlobalLeaderboard::class)
        ->assertViewHas('pinnedEntry', fn ($entry) => $entry !== null
            && $entry['name'] === 'Outsider'
            && $entry['rank'] === 101
            && $entry['total_points'] === 1
            && $entry['predictions_made'] === 5);
});

it('breaks ties consistently by user_stat id', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);

    // Same points — Alice's stat created first (lower ULID = earlier alphabetically)
    UserStat::factory()->withPoints(10)->create(['user_id' => $userA->id]);
    UserStat::factory()->withPoints(10)->create(['user_id' => $userB->id]);

    Livewire::test(GlobalLeaderboard::class)
        ->assertSeeInOrder(['Alice', 'Bob']);
});
```

- [ ] **Step 3: Run to confirm they fail**

```bash
php artisan test --compact tests/Feature/Leaderboard/GlobalLeaderboardTest.php
```

Expected: failures — `GlobalLeaderboard` class not found and route not registered.

- [ ] **Step 4: Create the Livewire component**

```bash
mkdir -p app/Livewire/Leaderboard
```

`app/Livewire/Leaderboard/GlobalLeaderboard.php`:
```php
<?php

namespace App\Livewire\Leaderboard;

use App\Models\UserStat;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Global Leaderboard')]
class GlobalLeaderboard extends Component
{
    public function render(): View
    {
        $userId = auth()->id();

        $stats = UserStat::with('user')
            ->orderBy('total_points', 'desc')
            ->orderBy('id', 'asc')
            ->limit(100)
            ->get();

        $inTop100 = false;
        $topEntries = $stats->map(function (UserStat $stat, int $index) use ($userId, &$inTop100): array {
            $isCurrentUser = $stat->user_id === $userId;
            if ($isCurrentUser) {
                $inTop100 = true;
            }

            return [
                'rank' => $index + 1,
                'name' => $stat->user->name,
                'total_points' => $stat->total_points,
                'predictions_made' => $stat->predictions_made,
                'is_current_user' => $isCurrentUser,
            ];
        })->all();

        $pinnedEntry = null;
        if ($userId !== null && ! $inTop100) {
            $userStat = UserStat::where('user_id', $userId)->first();
            if ($userStat !== null) {
                $rank = UserStat::where('total_points', '>', $userStat->total_points)->count()
                    + UserStat::where('total_points', $userStat->total_points)
                        ->where('id', '<', $userStat->id)
                        ->count()
                    + 1;

                $pinnedEntry = [
                    'rank' => $rank,
                    'name' => auth()->user()->name,
                    'total_points' => $userStat->total_points,
                    'predictions_made' => $userStat->predictions_made,
                ];
            }
        }

        return view('livewire.leaderboard.global-leaderboard', [
            'topEntries' => $topEntries,
            'pinnedEntry' => $pinnedEntry,
        ]);
    }
}
```

- [ ] **Step 5: Create the view**

```bash
mkdir -p resources/views/livewire/leaderboard
```

`resources/views/livewire/leaderboard/global-leaderboard.blade.php`:
```blade
<div class="space-y-6">
    <flux:heading size="xl">Global Leaderboard</flux:heading>

    <flux:table>
        <flux:columns>
            <flux:column class="w-16">#</flux:column>
            <flux:column>Player</flux:column>
            <flux:column>Points</flux:column>
            <flux:column>Predictions</flux:column>
        </flux:columns>

        <flux:rows>
            @forelse ($topEntries as $entry)
                <flux:row :class="$entry['is_current_user'] ? 'bg-amber-50 font-medium dark:bg-amber-950/20' : ''">
                    <flux:cell>{{ $entry['rank'] }}</flux:cell>
                    <flux:cell>{{ $entry['name'] }}</flux:cell>
                    <flux:cell>{{ $entry['total_points'] }}</flux:cell>
                    <flux:cell>{{ $entry['predictions_made'] }} / 104</flux:cell>
                </flux:row>
            @empty
                <flux:row>
                    <flux:cell colspan="4">
                        <flux:text class="text-center text-zinc-500">No predictions have been scored yet.</flux:text>
                    </flux:cell>
                </flux:row>
            @endforelse
        </flux:rows>
    </flux:table>

    @if ($pinnedEntry !== null)
        <div class="border-t-2 border-dashed border-zinc-300 pt-2 dark:border-zinc-600">
            <flux:table>
                <flux:rows>
                    <flux:row class="bg-amber-50 font-medium dark:bg-amber-950/20">
                        <flux:cell class="w-16">{{ $pinnedEntry['rank'] }}</flux:cell>
                        <flux:cell>
                            {{ $pinnedEntry['name'] }}
                            <flux:badge class="ml-2" size="sm" color="amber">You</flux:badge>
                        </flux:cell>
                        <flux:cell>{{ $pinnedEntry['total_points'] }}</flux:cell>
                        <flux:cell>{{ $pinnedEntry['predictions_made'] }} / 104</flux:cell>
                    </flux:row>
                </flux:rows>
            </flux:table>
        </div>
    @endif
</div>
```

- [ ] **Step 6: Add the route**

`routes/web.php` — add after `Route::view('/', 'welcome')`:
```php
use App\Livewire\Leaderboard\GlobalLeaderboard;

Route::livewire('/leaderboard', GlobalLeaderboard::class)->name('leaderboard.global');
```

- [ ] **Step 7: Run the tests and confirm they pass**

```bash
php artisan test --compact tests/Feature/Leaderboard/GlobalLeaderboardTest.php
```

Expected: 8 passed.

- [ ] **Step 8: Run the full leaderboard test suite**

```bash
php artisan test --compact tests/Feature/Leaderboard/
```

Expected: all tests pass.

- [ ] **Step 9: Run the full test suite to check for regressions**

```bash
php artisan test --compact
```

Expected: all tests pass.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Leaderboard/GlobalLeaderboard.php resources/views/livewire/leaderboard/global-leaderboard.blade.php routes/web.php tests/Feature/Leaderboard/GlobalLeaderboardTest.php
git commit -m "add GlobalLeaderboard Livewire component and route"
```
