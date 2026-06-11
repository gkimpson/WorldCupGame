# Prediction Deadline Enforcement Design

**Date:** 2026-06-11
**Status:** Approved

## Context

The `SubmitPredictions` component already rejects saves for fixtures within 2 hours of kickoff and dims those rows in the UI. However:
1. The lock logic is duplicated inline across `save()` and the Blade view with no single source of truth
2. Users see dimmed/disabled inputs with no explanation — no label indicating the row is locked
3. The fixtures index page shows no lock status at all

This spec addresses those three gaps without changing the existing 2-hour default behaviour.

## Goals

1. `Fixture::isLocked()` — single source of truth for deadline logic, config-backed window
2. Explicit "Locked" badge on the predictions page alongside locked fixture rows
3. "Locked" badge on the fixtures index page when a fixture is locked but not yet completed

## Architecture

### Config file — `config/predictions.php`

```php
return [
    'lock_minutes_before_kickoff' => env('PREDICTIONS_LOCK_MINUTES', 120),
];
```

Defaults to 120 minutes (preserving current behaviour). Overridable via `.env`.

### `Fixture::isLocked(): bool`

```php
public function isLocked(): bool
{
    if ($this->scheduled_at === null) {
        return false;
    }
    $window = (int) config('predictions.lock_minutes_before_kickoff', 120);
    return $this->scheduled_at->subMinutes($window)->isPast();
}
```

### `SubmitPredictions` component (`save()`)

Replace inline:
```php
->where('scheduled_at', '>', $now->copy()->addHours(2))
```
with a collection filter using `$fixture->isLocked()` after loading fixtures, or keep the DB query and add a post-filter. Simplest: load eligible fixture IDs by calling `isLocked()` on a fetched collection — avoids duplicating the minutes calculation in raw SQL.

### Predictions view (`submit-predictions.blade.php`)

- Replace `$locked = $fixture->scheduled_at !== null && $fixture->scheduled_at <= $now->copy()->addHours(2)` with `$locked = $fixture->isLocked()`
- Add `<flux:badge size="sm" color="zinc">Locked</flux:badge>` in the kickoff time column when `$locked` is true
- Remove the `$now` variable from the `render()` return (no longer needed in the view)

### Fixtures index view (`index-fixtures.blade.php`)

In the status column, add a "Locked" badge when `$fixture->isLocked() && $fixture->status !== FixtureStatus::Completed`:

```blade
@if($fixture->isLocked() && $fixture->status !== \App\Enums\FixtureStatus::Completed)
    <flux:badge size="sm" color="amber">Locked</flux:badge>
@endif
```

## Testing

**Unit test** — `tests/Unit/FixtureIsLockedTest.php`:
- Returns `false` when `scheduled_at` is null
- Returns `false` when kickoff is beyond the lock window
- Returns `true` when kickoff is within the lock window
- Returns `true` when kickoff has already passed

**Feature test** — extend `tests/Feature/SubmitPredictionsTest.php`:
- Cannot save prediction for a locked fixture (existing test updated to use `isLocked()` semantics — no logic change, just clarity)

No Playwright/browser tests.
