# Weekly Leaderboards

## Problem

Users who join late (e.g. week 7 of the tournament) have no realistic chance of topping the overall leaderboard. This kills engagement for latecomers. A weekly leaderboard resets the playing field every week — anyone can win a given week regardless of when they signed up.

## Goal

Add a **week selector** (Wk1, Wk2, … All) to every leaderboard page. All three leaderboard types (Global / Accuracy / Perfect) gain weekly views without changing their existing overall behaviour.

---

## Data Model

### 1. Add `week_number` to `fixtures`

A new `tinyint unsigned` column on `fixtures` that records which tournament week the match falls in. Computed from `scheduled_at` relative to the tournament start date (2026-06-11 = Week 1).

**Migration:** `add_week_number_to_fixtures_table`

```
fixtures.week_number  tinyint unsigned, nullable
```

Week assignment formula: `CEIL(DATEDIFF(scheduled_at, '2026-06-11') / 7) + 1`

Expected weeks for the 2026 World Cup:
| Week | Dates | Matches |
|------|-------|---------|
| 1 | Jun 11–17 | Group Stage openers |
| 2 | Jun 18–24 | Group Stage middle |
| 3 | Jun 25 – Jul 1 | Group Stage closers + R32 |
| 4 | Jul 2–8 | R16 + QF |
| 5 | Jul 9–15 | SF |
| 6 | Jul 16–19 | 3rd place + Final |

Populate existing fixtures via an Artisan command (see below).

### 2. New `user_weekly_stats` table

Mirrors the `user_stats` columns but scoped to a single week. Queried by leaderboards instead of `user_stats` when a week is selected.

**Migration:** `create_user_weekly_stats_table`

```
id            ulid primary, ascii
user_id       foreignId → users, cascade delete
week_number   tinyint unsigned
total_points       unsigned int, default 0
predictions_made   unsigned smallint, default 0
correct_outcomes   unsigned smallint, default 0
exact_scores       unsigned smallint, default 0
timestamps

unique(user_id, week_number)
```

**Model:** `app/Models/UserWeeklyStat.php`
- `HasUlids`, `#[Fillable]`, `casts()`
- Scope `forRealUsers()` — same join as `UserStat::forRealUsers()`

---

## Scoring Integration

### `RecalculateUserStats` listener

Currently updates `user_stats` only. Extend it to also upsert the `user_weekly_stats` row for the affected week.

After updating `UserStat`, for each affected user:

```php
$weekNumber = $fixture->week_number;

$weeklyTotals = Prediction::query()
    ->where('user_id', $userId)
    ->whereHas('fixture', fn ($q) => $q->where('week_number', $weekNumber))
    ->whereNotNull('points')
    ->selectRaw('
        SUM(points) as total_points,
        COUNT(*) as predictions_made,
        SUM(CASE WHEN points >= 1 THEN 1 ELSE 0 END) as correct_outcomes,
        SUM(CASE WHEN points = 3 THEN 1 ELSE 0 END) as exact_scores
    ')
    ->first();

UserWeeklyStat::updateOrCreate(
    ['user_id' => $userId, 'week_number' => $weekNumber],
    $weeklyTotals->toArray()
);
```

### `RecalculateAllUserStats` Artisan command (existing or new)

If a bulk recalculation command exists, extend it to also rebuild `user_weekly_stats`. If not, a new `world-cup:recalculate-weekly-stats` command should iterate all fixtures with scored predictions and rebuild weekly stats.

---

## Artisan: Assign Week Numbers

**Command:** `world-cup:assign-fixture-weeks`

Iterates all fixtures with a non-null `scheduled_at` and sets `week_number` using the formula above. Idempotent (safe to re-run). Run once after migration, then handled automatically during fixture import.

Also update fixture importers (API-Football, manual) to set `week_number` when a fixture is created/updated.

---

## Leaderboard Components

All three components (`GlobalLeaderboard`, `AccuracyLeaderboard`, `PerfectLeaderboard`) share the same week-filter pattern.

### Livewire property

Add `public ?int $week = null;` to each component. Wire it to a URL query parameter so links are shareable:

```php
#[Url(as: 'week')]
public ?int $week = null;
```

### Query switching

Replace the direct `UserStat::forRealUsers()` query with a method that returns the right model/table:

```php
private function statsQuery(): Builder
{
    if ($this->week === null) {
        return UserStat::forRealUsers();
    }

    return UserWeeklyStat::forRealUsers()->where('week_number', $this->week);
}
```

The sort columns and rank logic stay identical — `UserWeeklyStat` has the same column names.

### Available weeks helper

Both the component and the view need the list of weeks that have at least one scored prediction (to build the tab list dynamically):

```php
public function availableWeeks(): Collection
{
    return Fixture::query()
        ->whereNotNull('week_number')
        ->whereHas('predictions', fn ($q) => $q->whereNotNull('points'))
        ->distinct()
        ->orderBy('week_number')
        ->pluck('week_number');
}
```

Expose as a computed property so it's cached per render.

---

## UI

### Week selector (shared Blade component)

Extract to `resources/views/components/leaderboard/week-selector.blade.php`:

```html
<!-- "All" tab + Wk1, Wk2, … tabs -->
<div class="flex flex-wrap gap-2">
    <flux:button :variant="$week === null ? 'primary' : 'ghost'" wire:click="$set('week', null)">All</flux:button>
    @foreach ($availableWeeks as $w)
        <flux:button :variant="$week === $w ? 'primary' : 'ghost'" wire:click="$set('week', {{ $w }})">Wk{{ $w }}</flux:button>
    @endforeach
</div>
```

Place the selector above the leaderboard table in all three views. When no weeks are available yet (early in tournament), the selector is not rendered.

### Empty state

When a week is selected but the user has no predictions scored for that week, show a friendly message: "No scored predictions for this week yet." rather than a blank table.

### Pinned row

The existing "pinned current user" logic works unchanged — just apply it to `UserWeeklyStat` rows when a week is selected.

---

## Routes

No new routes. Week is a Livewire URL query parameter (`?week=2`) — fully shareable and bookmarkable.

---

## Implementation Order

1. Migration: add `week_number` to `fixtures`
2. Migration: create `user_weekly_stats` table
3. Model: `UserWeeklyStat`
4. Command: `world-cup:assign-fixture-weeks` + run it
5. Update fixture importers to set `week_number` on create/update
6. Extend `RecalculateUserStats` listener → also update `UserWeeklyStat`
7. Extend leaderboard Livewire components with `$week` property + `statsQuery()` switch
8. Add week selector UI to all three leaderboard views
9. Tests: weekly stat calculation, leaderboard filtering, week selector rendering

---

## Out of Scope (this phase)

- Per-week notifications or emails
- "Best week" trophy / badge (can follow from this foundation)
- Weekly prediction deadline reminders
- Week-over-week rank change indicators (but the data will support it later)
