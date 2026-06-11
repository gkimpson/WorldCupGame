# Dashboard Design

**Date:** 2026-06-11
**Status:** Approved

## Context

The `/dashboard` route is the first page users land on after login. Currently it is a blank stub with placeholder elements. This spec describes replacing it with a real, data-driven page that gives users an immediate read on their competition standing, nudges them to fill in remaining predictions, and surfaces recent scoring activity.

## Goals

- Show rank + points as the emotional hook within 3 seconds of landing
- Drive users toward submitting remaining predictions (primary action)
- Surface recent results with points earned per match (dopamine loop)
- Show a league summary for users who are in leagues (social hook)
- Handle the new-user empty state gracefully

## Architecture

**Single Livewire component** — matches the existing pattern used by `GlobalLeaderboard`, `MyLeagues`, `ShowLeague`, etc.

- Component: `app/Livewire/Dashboard.php`
- View: `resources/views/livewire/dashboard.blade.php`
- Route: change existing `Route::view('/dashboard', ...)` to `Route::livewire('/dashboard', Dashboard::class)` in `routes/web.php`

All data loaded in `mount()`. No polling, no real-time events — pure read on page load.

## Layout

```
┌─────────────────────────────────────────────────────────┐
│  STAT CARDS (3 columns)                                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐  │
│  │ Global Rank │  │    Points   │  │  Predictions    │  │
│  │    #12      │  │   34 pts    │  │   27 / 104      │  │
│  └─────────────┘  └─────────────┘  └─────────────────┘  │
├─────────────────────────────────────────────────────────┤
│  LEFT COLUMN (60%)           │  RIGHT COLUMN (40%)       │
│                              │                           │
│  Upcoming Fixtures           │  Recent Results           │
│  ┌──────────────────────┐    │  ┌─────────────────────┐  │
│  │ ENG vs USA           │    │  │ MEX 2-1 RSA  +3pts  │  │
│  │ Tomorrow, 19:00      │    │  │ Predicted: 2-1 ✓    │  │
│  │               Predict│    │  ├─────────────────────┤  │
│  ├──────────────────────┤    │  │ ARG 1-1 FRA  +1pt   │  │
│  │ ARG vs FRA  ...      │    │  │ Predicted: 0-1 ✗    │  │
│  └──────────────────────┘    │  └─────────────────────┘  │
│                              │                           │
│  Your Leagues                │                           │
│  ┌──────────────────────┐    │                           │
│  │ The Lads  →  #3      │    │                           │
│  └──────────────────────┘    │                           │
└─────────────────────────────────────────────────────────┘
```

**Empty state** (zero predictions made): the two-column section is replaced by a single centred card — "You haven't made any predictions yet. The tournament is underway — get your picks in!" with a prominent button linking to `/predictions`.

UI components: `<flux:card>`, `<flux:badge>`, `<flux:button>`, `<flux:separator>`, existing `<x-team-flag>` and `<x-fixture-kickoff>` components.

## Data Layer

### Public properties on `Dashboard`

| Property | Type | Description |
|---|---|---|
| `$globalRank` | `int` | User's global rank |
| `$totalPoints` | `int` | From `UserStat` |
| `$predictionsMade` | `int` | From `UserStat` |
| `$upcomingFixtures` | `Collection` | Next 5 future, non-completed fixtures |
| `$recentResults` | `Collection` | Last 5 scored predictions with fixture |
| `$topLeague` | `?League` | League where user has best rank, or null |
| `$topLeagueRank` | `?int` | Rank within that league |
| `$hasAnyPredictions` | `bool` | Drives empty state |

### Queries

**Global rank:**
```php
UserStat::where('total_points', '>', $userStat->total_points)->count() + 1
```
If no `UserStat` row exists, rank is null and defaults to `—`.

**Upcoming fixtures:**
```php
Fixture::where('scheduled_at', '>', now())
    ->where('status', '!=', FixtureStatus::Completed)
    ->orderBy('scheduled_at')
    ->limit(5)
    ->with(['homeTeam', 'awayTeam'])
    ->get()
```

**Recent results:**
```php
Prediction::where('user_id', $user->id)
    ->whereNotNull('points')
    ->whereHas('fixture', fn($q) => $q->where('status', FixtureStatus::Completed))
    ->with(['fixture.homeTeam', 'fixture.awayTeam'])
    ->join('fixtures', 'fixtures.id', '=', 'predictions.fixture_id')
    ->orderBy('fixtures.scheduled_at', 'desc')
    ->limit(5)
    ->select('predictions.*')
    ->get()
```

**Top league:**
Load user's `LeagueMember` records with `league`, then for each league compute rank via COUNT query. Surface the league with the lowest rank number.

## Constants

- `Fixture::TOTAL_WORLD_CUP_MATCHES` (already exists) — used for the `27 / 104` display

## Testing

File: `tests/Feature/DashboardTest.php` (extend existing file)

| Test | Description |
|---|---|
| Unauthenticated redirect | `/dashboard` without auth → redirect to `/login` |
| Empty state shown | New user with no predictions sees empty state CTA |
| Empty state not shown | User with predictions sees widgets, not empty state |
| Stat cards | User with `UserStat` sees correct rank, points, predictions count |
| Upcoming fixtures | Future non-completed fixtures appear; completed ones do not |
| Recent results | Last 5 scored predictions shown, correct order, correct points |
| League widget shown | User in a league sees league name + rank |
| League widget hidden | User in no league sees no league section |

No Playwright/browser tests. Pest feature tests only, using factories and `RefreshDatabase`.
