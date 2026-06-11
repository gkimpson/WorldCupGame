# Prediction Lock at Kickoff — Design Spec

## Summary

Predictions lock at the exact kickoff time (`scheduled_at`). A user can submit or update a prediction up until the second the match starts; once that moment has passed the fixture is locked and no further changes are accepted.

## Changes

### `config/predictions.php`
Change the default value of `lock_minutes_before_kickoff` from `120` to `0`.

### `.env`
Add `PREDICTIONS_LOCK_MINUTES=0` to make the intent explicit and allow per-environment overrides.

## What stays the same

- `Fixture::isLocked()` — logic is unchanged. `scheduled_at->subMinutes(0)->isPast()` correctly resolves to `scheduled_at->isPast()`.
- `SubmitPredictions::saveFixture()` and `save()` — no changes. The `status === Scheduled` check remains a valid safety net.
- `is_locked` boolean — still works as an admin override to force-lock any fixture early.
- UI — template already reads `isLocked()` to disable/hide prediction inputs; no template changes needed.

## Behaviour

| Scenario | Result |
|---|---|
| 1 minute before `scheduled_at` | Prediction accepted |
| Exactly at `scheduled_at` | Locked (isPast returns true) |
| 1 minute after `scheduled_at` | Locked |
| `is_locked = true` regardless of time | Locked |

## Out of scope

- No grace period after kickoff.
- No status-based locking (`InProgress`, `Completed`) — the time check is the single source of truth.
