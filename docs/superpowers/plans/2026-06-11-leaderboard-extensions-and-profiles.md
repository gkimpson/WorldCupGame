# Leaderboard Extensions & Public User Profiles

**Date:** 2026-06-11
**Status:** Ready to implement

---

## Context

The platform has a working global leaderboard (`/leaderboard`) sorted by total points, and a league leaderboard inside each league page. The PRD calls for two additional leaderboards — **accuracy** (who gets outcomes right most consistently) and **perfect 104** (who nails exact scores most often) — plus **public user profiles**. These are all Tier 2 items; Tier 1 is complete.

`UserStat` currently stores `total_points` and `predictions_made`. Accuracy and exact-score stats can be derived from existing `predictions.points` data (3pts = exact score, ≥1pt = correct outcome) with no changes to the `predictions` table.

---

## Stack & Conventions

- **PHP 8.3 / Laravel 13 / Livewire 4 / Flux UI v2 / Pest 4**
- ULID PKs on domain models; `user_id` FKs stay BIGINT
- Full-page Livewire components mounted via `Route::livewire()`
- Tests: `Livewire::test()` + `Livewire::actingAs()` pattern, `RefreshDatabase` auto-applied to all `tests/Feature/`
- `vendor/bin/pint --dirty --format agent` after every PHP change
- TDD: write failing test → verify RED → implement → verify GREEN

---

## Part 1 — Data Layer (shared by all three features)

### 1a. Migration — add columns to `user_stats`

```bash
php artisan make:migration add_accuracy_columns_to_user_stats_table --no-interaction
```

Add to the migration:
```php
$table->unsignedSmallInteger('correct_outcomes')->default(0)->after('predictions_made');
$table->unsignedSmallInteger('exact_scores')->default(0)->after('correct_outcomes');
```

### 1b. Update `UserStat` model — `app/Models/UserStat.php`

Add `correct_outcomes` and `exact_scores` to:
- `#[Fillable([...])]` attribute
- `casts()` method (`'integer'` for both)
- PHPDoc `@property` block

### 1c. Update `RecalculateUserStats` listener — `app/Listeners/RecalculateUserStats.php`

Current query:
```php
->selectRaw('SUM(points) as total_points, COUNT(*) as predictions_made')
```

Replace with:
```php
->selectRaw('
    SUM(points) as total_points,
    COUNT(*) as predictions_made,
    SUM(CASE WHEN points >= 1 THEN 1 ELSE 0 END) as correct_outcomes,
    SUM(CASE WHEN points = 3 THEN 1 ELSE 0 END) as exact_scores
')
```

Update the `updateOrCreate` call to include the new columns.

### 1d. Update `UserStatFactory` — `database/factories/UserStatFactory.php`

Add `correct_outcomes` and `exact_scores` to the `definition()` defaults (both `0`).

Add a factory state for accuracy testing:
```php
public function withAccuracy(int $correctOutcomes, int $exactScores, int $predictionsMade, int $points): static
{
    return $this->state([
        'correct_outcomes' => $correctOutcomes,
        'exact_scores' => $exactScores,
        'predictions_made' => $predictionsMade,
        'total_points' => $points,
    ]);
}
```

### Tests for Part 1

Extend `tests/Feature/Leaderboard/RecalculateUserStatsTest.php`:

- **correct_outcomes is counted correctly** — user with 1pt and 3pt predictions gets `correct_outcomes = 2`
- **exact_scores is counted correctly** — user with one 3pt prediction gets `exact_scores = 1`
- **0pt predictions do not count toward correct_outcomes** — prediction with 0pts leaves `correct_outcomes = 0`

---

## Part 2 — Accuracy Leaderboard

**Route:** `GET /leaderboard/accuracy` → public (no auth required, matches global leaderboard pattern)
**Route name:** `leaderboard.accuracy`

### Component — `app/Livewire/Leaderboard/AccuracyLeaderboard.php`

Mirror the structure of `GlobalLeaderboard`. Key differences:
- Order by `correct_outcomes DESC`, ties broken by `predictions_made ASC` (fewer predictions needed = more impressive), then `id ASC` for consistency
- Pinned entry for authenticated users outside top 100 — same COUNT query pattern as GlobalLeaderboard but counting users with more `correct_outcomes`
- Each entry exposes: `rank`, `name`, `correct_outcomes`, `predictions_made`, `accuracy_pct` (computed as `correct_outcomes / predictions_made * 100`, rounded to 1 decimal; `0` if `predictions_made = 0`)

```php
// Rank query (outside top 100 pinned entry)
UserStat::where('correct_outcomes', '>', $userStat->correct_outcomes)->count()
+ UserStat::where('correct_outcomes', $userStat->correct_outcomes)
    ->where('predictions_made', '<', $userStat->predictions_made)
    ->count()
+ UserStat::where('correct_outcomes', $userStat->correct_outcomes)
    ->where('predictions_made', $userStat->predictions_made)
    ->where('id', '<', $userStat->id)
    ->count()
+ 1;
```

### View — `resources/views/livewire/leaderboard/accuracy-leaderboard.blade.php`

Columns: `#` | `Player` | `Correct Outcomes` | `Predictions Made` | `Accuracy %`

Reuse the existing leaderboard Blade structure from `global-leaderboard.blade.php`. Highlight current user row with amber bg (same pattern).

### Route — `routes/web.php`

```php
Route::livewire('/leaderboard/accuracy', AccuracyLeaderboard::class)->name('leaderboard.accuracy');
```

Add this alongside the existing `leaderboard.global` route (public, outside auth middleware).

### Tests — `tests/Feature/Leaderboard/AccuracyLeaderboardTest.php`

- Publicly accessible without auth
- Ordered by correct_outcomes descending
- Ties broken by predictions_made ascending
- Assigns correct ranks starting at 1
- Caps at 100 entries
- Shows no pinned entry when auth user is in top 100
- Pins auth user when outside top 100 with correct rank

---

## Part 3 — Perfect 104 Leaderboard

**Route:** `GET /leaderboard/perfect` → public
**Route name:** `leaderboard.perfect`

### Component — `app/Livewire/Leaderboard/PerfectLeaderboard.php`

Same pattern as AccuracyLeaderboard. Key differences:
- Order by `exact_scores DESC`, ties broken by `total_points DESC` (more total points = better all-round), then `id ASC`
- Each entry exposes: `rank`, `name`, `exact_scores`, `total_points`, `predictions_made`
- Pinned entry uses COUNT on `exact_scores`

### View — `resources/views/livewire/leaderboard/perfect-leaderboard.blade.php`

Columns: `#` | `Player` | `Exact Scores` | `Total Points` | `Predictions Made`

### Route — `routes/web.php`

```php
Route::livewire('/leaderboard/perfect', PerfectLeaderboard::class)->name('leaderboard.perfect');
```

### Tests — `tests/Feature/Leaderboard/PerfectLeaderboardTest.php`

Same shape as AccuracyLeaderboardTest, adjusted for `exact_scores` ordering.

---

## Part 4 — Public User Profiles

**Route:** `GET /users/{user}` → public (no auth required)
**Route name:** `users.show`

### Component — `app/Livewire/Users/ShowProfile.php`

```php
public User $user;

public function mount(User $user): void
{
    $this->user = $user->load('stat');
}
```

Passes to view:
- `$user` — name, stat (total_points, predictions_made, correct_outcomes, exact_scores)
- `$globalRank` — COUNT query: `UserStat::where('total_points', '>', $stat->total_points)->count() + 1`
- `$accuracyPct` — `correct_outcomes / predictions_made * 100` (0 if none made)
- `$recentResults` — last 10 scored predictions with fixture + points (same query as Dashboard but limit 10, and for the profile user not auth user)

### View — `resources/views/livewire/users/show-profile.blade.php`

Layout:
```
┌──────────────────────────────────────────────┐
│  NAME                         Global Rank #N  │
├──────────┬──────────┬──────────┬─────────────┤
│  Points  │ Accuracy │  Exact   │ Predictions  │
│   stat   │   stat   │  Scores  │  Made/104   │
├──────────┴──────────┴──────────┴─────────────┤
│  Recent Predictions (last 10)                 │
│  Match result | predicted | +Xpts badge       │
└──────────────────────────────────────────────┘
```

Use `<flux:card>` for stat tiles, same badge pattern as Dashboard recent results.

### Route — `routes/web.php`

```php
Route::livewire('/users/{user}', ShowProfile::class)->name('users.show');
```

Add outside any middleware group (public page).

### Tests — `tests/Feature/Users/ShowProfileTest.php`

- Profile is publicly accessible without auth
- Shows the correct user's name
- Shows total_points from their UserStat
- Shows correct global rank
- Shows accuracy percentage
- Shows recent scored predictions (max 10)
- Returns 404 for non-existent user (route model binding handles this)

---

## Implementation Order

Follow TDD throughout. Recommended sequence:

1. **Migration + model + factory + listener** (Part 1) — all tests first, then implement
2. **Accuracy leaderboard** (Part 2) — tests first, then component + view + route
3. **Perfect leaderboard** (Part 3) — tests first, then component + view + route
4. **User profiles** (Part 4) — tests first, then component + view + route

Each part can be committed separately.

---

## Key Files to Touch

| File | Change |
|---|---|
| `database/migrations/XXXX_add_accuracy_columns_to_user_stats_table.php` | New migration |
| `app/Models/UserStat.php` | Add `correct_outcomes`, `exact_scores` to fillable/casts/docblock |
| `app/Listeners/RecalculateUserStats.php` | Extend selectRaw, updateOrCreate |
| `database/factories/UserStatFactory.php` | Add columns to definition + new `withAccuracy()` state |
| `app/Livewire/Leaderboard/AccuracyLeaderboard.php` | New component |
| `app/Livewire/Leaderboard/PerfectLeaderboard.php` | New component |
| `app/Livewire/Users/ShowProfile.php` | New component |
| `resources/views/livewire/leaderboard/accuracy-leaderboard.blade.php` | New view |
| `resources/views/livewire/leaderboard/perfect-leaderboard.blade.php` | New view |
| `resources/views/livewire/users/show-profile.blade.php` | New view |
| `routes/web.php` | 3 new routes |
| `tests/Feature/Leaderboard/RecalculateUserStatsTest.php` | Extend with 3 new tests |
| `tests/Feature/Leaderboard/AccuracyLeaderboardTest.php` | New test file |
| `tests/Feature/Leaderboard/PerfectLeaderboardTest.php` | New test file |
| `tests/Feature/Users/ShowProfileTest.php` | New test file |

---

## Existing Patterns to Reuse

- **Rank query pattern:** `GlobalLeaderboard.php` lines 37–43 — copy and adjust for new sort column
- **Pinned entry pattern:** `GlobalLeaderboard.php` lines 34–51 — same shape for all leaderboards
- **Leaderboard view structure:** `global-leaderboard.blade.php` — copy and rename columns
- **Recent results query:** `Dashboard.php` `mount()` — same join + limit pattern for profile recent predictions
- **`UserStat::with('user')` eager load** — already used in GlobalLeaderboard, reuse in new leaderboards

---

## Verification

```bash
# Run all leaderboard + profile tests
php artisan test --compact tests/Feature/Leaderboard/ tests/Feature/Users/

# Run full suite to check for regressions
php artisan test --compact

# Type check new files
vendor/bin/phpstan analyse app/Livewire/Leaderboard/AccuracyLeaderboard.php \
  app/Livewire/Leaderboard/PerfectLeaderboard.php \
  app/Livewire/Users/ShowProfile.php \
  app/Listeners/RecalculateUserStats.php --level=5

# Format
vendor/bin/pint --dirty --format agent
```

Visual checks:
- `/leaderboard/accuracy` — loads without auth, shows correct order
- `/leaderboard/perfect` — loads without auth, shows correct order
- `/users/{user-id}` — loads publicly, shows correct stats
