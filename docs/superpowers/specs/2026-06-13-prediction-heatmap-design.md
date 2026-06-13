# Prediction Heatmap — Design Spec

**Date:** 2026-06-13
**Status:** Approved

---

## Overview

A visual summary of a user's prediction history, embedded on their public profile page and on the head-to-head compare page. Two grids are shown: a match outcome grid (all 104 fixtures colour-coded by result) and a score distribution grid (a matrix of predicted scorelines with accuracy intensity).

---

## Placement

| Location | Mode | Notes |
|---|---|---|
| `/users/{user}` (profile page) | Full | Both grids shown, below "Recent Predictions" |
| `/compare/{userA}/{userB}` (compare page) | Compact | Match outcome grid only, shown for each user below "Match by Match" |

---

## Component

**Class:** `app/Livewire/Users/PredictionHeatmap.php`
**View:** `resources/views/livewire/users/prediction-heatmap.blade.php`

### Props

```php
public User $user;
public bool $compact = false;  // hides score distribution grid when true
```

### Embedding

```blade
{{-- Profile page (full) --}}
<livewire:users.prediction-heatmap :user="$user" />

{{-- Compare page (compact, one per user) --}}
<livewire:users.prediction-heatmap :user="$userA" :compact="true" />
<livewire:users.prediction-heatmap :user="$userB" :compact="true" />
```

---

## Data & Queries

Both datasets are computed in `render()`. No new tables or columns are required.

### Match Outcome Grid

Fetch all fixtures ordered chronologically. Pre-load the user's scored predictions keyed by `fixture_id`, then map each fixture to a result type:

| Result | Condition |
|---|---|
| `exact` | `points >= 3` |
| `correct` | `points >= 1` |
| `wrong` | `points === 0` |
| `none` | No prediction or not yet scored |

```php
// Keyed predictions
$predictions = Prediction::where('user_id', $user->id)
    ->whereNotNull('points')
    ->get()
    ->keyBy('fixture_id');

// All 104 fixtures
$outcomeGrid = Fixture::orderBy('scheduled_at')
    ->get()
    ->map(fn($fixture) => [
        'fixture'    => $fixture,
        'prediction' => $predictions->get($fixture->id),
        'result'     => $this->resolveResult($predictions->get($fixture->id)),
    ]);
```

### Score Distribution Grid

Aggregate all scored predictions into a matrix keyed by `home_score-away_score`. Grid dimensions are dynamic: `max(home_score)` × `max(away_score)` across all predictions, with a minimum of 3×3.

Each cell stores:
- `count` — number of times this scoreline was predicted
- `correct` — predictions that earned any points (points > 0)
- `exact` — predictions that earned 3 points

```php
$scored = Prediction::where('user_id', $user->id)
    ->whereNotNull('points')
    ->get();

$maxHome = max(3, $scored->max('home_score') ?? 0);
$maxAway = max(3, $scored->max('away_score') ?? 0);

$matrix = $scored->groupBy(fn($p) => "{$p->home_score}-{$p->away_score}");
// Build $maxHome+1 × $maxAway+1 grid from $matrix
```

---

## Visual Design

### Match Outcome Grid

- Responsive CSS grid: `grid-cols-[13]` on desktop (Tailwind v4 arbitrary value), fewer columns on mobile
- Full mode cell size: ~28px square; compact mode: ~20px square
- Colour scheme (consistent with app amber/green/red conventions):

| Result | Colour |
|---|---|
| `exact` | Green (`bg-green-500`) |
| `correct` | Amber (`bg-amber-400`) |
| `wrong` | Red (`bg-red-400`) |
| `none` | Zinc (`bg-zinc-200 dark:bg-zinc-700`) |

- Alpine.js hover tooltip: match name, actual score, user's predicted score, points earned
- Legend below grid: ■ exact · ■ correct · ■ wrong · □ pending

### Score Distribution Grid

- N×N matrix, X-axis = predicted away score, Y-axis = predicted home score
- Cell colour intensity = accuracy rate (% of times prediction earned any points):
  - ≥ 67% correct → `bg-green-600`
  - 34–66% correct → `bg-green-300`
  - 1–33% correct → `bg-zinc-300 dark:bg-zinc-600`
  - 0% correct (predicted but always wrong) → `bg-red-200`
  - Never predicted → empty cell (no background)
- Cell text: prediction count (shown if > 0)
- Alpine.js hover tooltip: "Predicted N times · X correct (Y%)"
- Empty state: *"No scored predictions yet."* replaces the grid

---

## Layout Sketch

```
Match Results
┌─────────────────────────────────────────┐
│ ■ ■ ■ □ □ ■ ■ ■ □ ■ ■ ■ ■             │
│ ■ □ ■ ■ ■ ■ □ ■ ■ ■ □ ■ ■             │
│ ...                                     │
│ ■ exact  ■ correct  ■ wrong  □ pending  │
└─────────────────────────────────────────┘

Score Distribution  [hidden in compact mode]
        Away →  0    1    2    3
Home  0      [ 12] [  8] [  3] [  1]
  ↓  1      [ 15] [ 22] [  6] [  2]
     2      [  4] [ 11] [  9] [  1]
     3      [  1] [  2] [  3] [   ]
```

---

## Tests

| Test | Assertion |
|---|---|
| Match grid renders all 104 fixtures | `$outcomeGrid` has exactly 104 entries |
| Exact score cell | Prediction with `points >= 3` → `result = 'exact'` |
| Correct outcome cell | Prediction with `points = 1` → `result = 'correct'` |
| Wrong cell | Prediction with `points = 0` → `result = 'wrong'` |
| No prediction cell | Fixture with no prediction → `result = 'none'` |
| Score grid dimensions | Max home/away scores correctly determine grid size; minimum 3×3 |
| Score grid cell data | Cell stores correct `count`, `correct`, `exact` values |
| Empty state | User with no scored predictions renders without error |
| Compact mode | `compact=true` — score distribution grid absent from view data |
| Profile integration | Heatmap renders on `/users/{user}` |
| Compare integration | Two compact heatmaps render on `/compare/{a}/{b}` when both users selected |

---

## Files

### New
- `app/Livewire/Users/PredictionHeatmap.php`
- `resources/views/livewire/users/prediction-heatmap.blade.php`
- `tests/Feature/Users/PredictionHeatmapTest.php`

### Modified
- `resources/views/livewire/users/show-profile.blade.php` — embed full heatmap below recent predictions
- `resources/views/livewire/users/compare-users.blade.php` — embed two compact heatmaps below match-by-match section
