# Global Leaderboard — Design Spec

**Date:** 2026-06-10
**Status:** Approved

---

## Overview

A public global leaderboard showing the top 100 users ranked by total prediction points. Authenticated users outside the top 100 see their own position pinned below the table. Leaderboard data is recalculated whenever fixture results are imported.

---

## Data Models

### `predictions` table (ULID PK)

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | |
| `user_id` | `foreignId` → `users.id` | BIGINT (framework table) |
| `fixture_id` | `foreignId` → `fixtures.id` | BIGINT (reference data) |
| `home_score` | `integer` | User's predicted home score |
| `away_score` | `integer` | User's predicted away score |
| `points` | `integer` nullable | Null until fixture is scored |
| timestamps | | |

- Unique constraint on `(user_id, fixture_id)`.
- `Prediction` model uses `HasUlids`.

### `user_stats` table (ULID PK)

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` PK | |
| `user_id` | `foreignId` → `users.id` | Unique — one row per user |
| `total_points` | `integer` | Default 0 |
| `predictions_made` | `integer` | Count of predictions with non-null points (out of 104) |
| timestamps | | |

- Row is created on first prediction, updated on each recalculation.
- `UserStat` model uses `HasUlids`.

---

## Event-Driven Score Recalculation

### Flow

1. **`ResultImported` event** — fired after a fixture's scores are persisted, carries the `Fixture` model.
2. **`RecalculateFixturePredictions` listener** — queries all `Prediction` rows for the fixture, runs each through `FixturePredictionScorer`, persists `predictions.points`.
3. **`RecalculateUserStats` listener** — triggered after step 2 (or chained), sums `predictions.points` and counts scored predictions per affected user, upserts `user_stats`.

### Notes

- Listeners are synchronous (no queue) until Redis/Horizon is added. Queueable later via a single trait.
- No result importer is built in this spec. The `ResultImported` event is dispatchable manually (e.g. from Filament or a future import command). The importer is a separate spec.
- Tie-breaking: equal `total_points` is broken by `user_stats.id` ASC (insertion order, which approximates registration order).

---

## Leaderboard Page

### Component

`app/Livewire/Leaderboard/Global.php` — full-page Livewire v4 component.

**`mount()` data loading:**
- Query 1: `UserStat` joined to `User`, ordered `total_points DESC`, `LIMIT 100` — top 100 rows with rank computed in PHP (1-indexed loop).
- Query 2 (authenticated users outside top 100 only): fetch the current user's `UserStat` row and compute their rank via a subquery using `ROW_NUMBER()` or a count of users with strictly more points.

### View

`resources/views/livewire/leaderboard/global.blade.php`

- Flux table with columns: **Rank**, **Username**, **Points**, **Predictions** (e.g. `48 / 104`).
- Current user's row highlighted if they appear in the top 100.
- If authenticated and outside top 100: a visually separated pinned row at the bottom of the table showing their rank, username, points, and predictions count.
- No auth gate — publicly accessible to guests.

### Route

```php
Route::get('/leaderboard', \App\Livewire\Leaderboard\Global::class)->name('leaderboard.global');
```

Added to `routes/web.php`.

---

## Testing Strategy

All tests are Pest feature tests using `RefreshDatabase`.

| Test | Description |
|---|---|
| User appears on leaderboard | A user with scored predictions shows correct points and prediction count |
| Top 100 cap | 101st-ranked user does not appear in the main list |
| Pinned row | Authenticated user outside top 100 sees their own row pinned |
| Public access | Unauthenticated visitors can load the leaderboard (no redirect) |
| `RecalculateFixturePredictions` | Firing `ResultImported` updates `predictions.points` correctly |
| `RecalculateUserStats` | `user_stats.total_points` and `predictions_made` reflect summed predictions |
| Tie-breaking | Users with equal points are ranked consistently |

---

## Out of Scope (this spec)

- Result importer (future spec)
- League / accuracy / perfect-104 leaderboards (future specs)
- Real-time updates via Reverb (Phase 2)
- Redis-backed sorted set (future optimisation)
