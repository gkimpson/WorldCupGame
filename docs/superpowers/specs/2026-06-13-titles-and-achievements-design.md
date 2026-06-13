# Titles & Achievements — Design Spec

**Date:** 2026-06-13
**Status:** Approved

## Overview

Users earn titles based on their prediction performance. Titles are displayed next to user names on leaderboards, the comparison page, and as a full list on public profiles. The most recently earned title is the active one shown in abbreviated contexts.

Two types exist:
- **Permanent** — awarded once when a milestone is reached, never revoked
- **Dynamic** — reflects current standing, re-evaluated after every result batch (old record replaced)

## Starter Title Set

### Permanent

| Key | Label | Trigger |
|---|---|---|
| `first_blood` | First Blood | First prediction with any points scored |
| `hat_trick_hero` | Hat-trick Hero | 3 exact scores in a single week |
| `perfect_week` | Perfect Week | 100% correct outcomes in a week (every scored prediction earns points; weeks with zero scored predictions do not qualify) |
| `sharp_shooter` | Sharp Shooter | 10 exact scores total |
| `centurion` | Centurion | 100 total points |
| `streak_master` | Streak Master | 5 consecutive correct predictions (points ≥ 1) |
| `fortune_teller` | Fortune Teller | 20 exact scores total |

### Dynamic

| Key | Label | Trigger |
|---|---|---|
| `top_predictor` | Top Predictor | Currently #1 on the global leaderboard |
| `week_warrior` | Week Warrior | Currently #1 on the current weekly leaderboard |
| `rising_star` | Rising Star | Biggest mover this week (most positions gained vs previous week) |

## Data Model

### `user_titles` table

```
id          CHAR(26)  ULID primary key
user_id     BIGINT    FK → users.id
title       VARCHAR   TitleType enum key (e.g. 'centurion')
type        VARCHAR   'permanent' | 'dynamic'
earned_at   TIMESTAMP
```

Titles are **code-defined** — no `titles` reference table. Rules live in the `TitleType` enum.

### `UserTitle` model

- ULID PK via `HasUlids`
- `belongsTo(User::class)`
- `title` cast to `TitleType` enum
- `type` cast to `TitleCategory` enum (`permanent` | `dynamic`)

### `TitleType` enum (`app/Enums/TitleType.php`)

Each case carries:
- `label(): string` — human-readable name
- `category(): TitleCategory` — permanent or dynamic
- `check(User $user): bool` — whether the user currently qualifies

### User model additions

```php
public function titles(): HasMany      // all UserTitle records
public function activeTitle(): HasOne  // most recent by earned_at desc
```

## Evaluation Architecture

### Event ordering

`EvaluateUserTitles` must run after `RecalculateUserStats` so stats are fresh. To guarantee this, `RecalculateUserStats` dispatches a second event `UserStatsRecalculated` at the end of its `handle()` method. `EvaluateUserTitles` listens to `UserStatsRecalculated`, not `ResultImported`.

### `EvaluateUserTitles` listener

- Registered on `UserStatsRecalculated` event in `AppServiceProvider`
- For each user with a prediction on the imported fixture:
  1. Load user with `stat`, `titles`, and relevant predictions
  2. For each `TitleType` case:
     - **Permanent:** skip if already awarded; award if `check()` returns true
     - **Dynamic:** delete existing dynamic record for this title key; re-award if `check()` returns true

### Streak calculation

`streak_master` requires querying the `predictions` table directly — ordered by fixture `scheduled_at`, filtered to scored predictions, counting the current consecutive run of `points >= 1`. This is done inside `TitleType::streak_master->check()`.

### Rising Star calculation

Compare the user's rank on the current week's leaderboard vs the previous week's leaderboard. Biggest positive delta wins. Evaluated against all users, awarded to the single user with the largest gain (ties: lower `user_id` wins — stable and deterministic).

## Display

### Leaderboard rows (Global & Accuracy)
Active title shown as a small muted badge beneath the player's name. No change to ranking logic.

### Comparison page
Active title shown beneath each user's name header.

### Public profile
Full titles list: permanent titles as one group, dynamic titles as another. Each shown as a badge with label and earned date.

## Out of Scope

- Admin UI for manually awarding/revoking titles (titles are fully automatic)
- Notifications when a title is earned (Phase 2 — requires WebSockets)
- Title rarity tiers or point values

## Testing

- `tests/Feature/Titles/` — one test per title
- Each test: seeds qualifying condition → dispatches `ResultImported` → asserts `user_titles` record created
- Dynamic title tests: assert old record replaced, not duplicated; assert only one user holds `top_predictor` at a time
- `activeTitle()` test: user with multiple titles returns most recently earned
- Streak test: 4 correct + 1 wrong + 5 correct → `streak_master` awarded on 5th consecutive
