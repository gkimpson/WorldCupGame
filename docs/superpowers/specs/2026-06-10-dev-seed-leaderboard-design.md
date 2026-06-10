# Dev Seed Leaderboard — Design Spec

**Date:** 2026-06-10
**Status:** Approved

---

## Overview

An Artisan command for local development that populates the database with 20 users, 72 predictions each, and a fully scored leaderboard in a single invocation. Intended to replace manual tinker scripts during development.

---

## Command

```bash
php artisan dev:seed-leaderboard
```

Located at `app/Console/Commands/DevSeedLeaderboard.php`.

---

## Behaviour

### 1. Destructive reset

In order:
1. Truncate `user_stats`
2. Truncate `predictions`
3. Delete all users where `email != 'gkimpson@gmail.com'` (preserves the admin account)

### 2. Seed 20 users

Create 20 users via `User::factory()`.

### 3. Seed predictions

For each of the 72 fixtures with `match_number <= 72`, for each of the 20 new users, create a `Prediction` with:
- `home_score`: random integer 0–5
- `away_score`: random integer 0–5
- `points`: null (not yet scored)

Total: 1,440 predictions.

### 4. Complete fixtures and score predictions

For each of the 72 fixtures:
1. Update the fixture: set `home_score` and `away_score` to random integers 0–5, set `status = FixtureStatus::Completed`.
2. Call `RecalculateFixturePredictions::handle(new ResultImported($fixture->fresh()))` directly (bypasses queue — no worker needed).
3. Call `RecalculateUserStats::handle(new ResultImported($fixture->fresh()))` directly.

### 5. Output summary

Print to console:
- Users created: 20
- Predictions made: 1,440
- Top 20 leaderboard (rank, name, points, predictions scored / 72)

---

## Constraints

- Development-only command — prefixed `dev:` to signal it is not for production use. No environment guard needed (responsibility of the developer).
- Synchronous execution — no queue worker required.
- Idempotent per run — each run resets to a clean 20-user state.
- Admin account (`gkimpson@gmail.com`) is preserved across runs.

---

## Testing

One feature test: `tests/Feature/DevSeedLeaderboardTest.php`

| Test | Description |
|---|---|
| Command runs successfully | Exit code 0 |
| Creates exactly 20 non-admin users | `User::count()` equals 21 (20 + admin) |
| Creates 1,440 predictions | `Prediction::count()` equals 1,440 |
| All predictions are scored | `Prediction::whereNull('points')->count()` equals 0 |
| UserStat rows created for all 20 users | `UserStat::count()` equals 20 |
| Output contains leaderboard | Command output contains "Leaderboard" |

---

## Out of Scope

- No environment guard (dev-only by convention, not enforced)
- No `--users` or `--fixtures` options (hardcoded 20 / 72 for now)
- Does not seed season predictions or league data
