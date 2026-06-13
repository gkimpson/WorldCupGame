# Head-to-Head Comparison — Design Spec

**Date:** 2026-06-13  
**Status:** Approved

---

## Overview

A publicly accessible comparison feature that puts two users' stats side by side. Users can reach it via a "Compare with me" button on any public profile, or by navigating directly to a standalone `/compare` page and searching for any two players.

---

## Routes

| URL | Behaviour |
|---|---|
| `/compare` | Standalone page — both search inputs empty, prompts user to pick two players |
| `/compare/{userA}/{userB}` | Pre-loaded comparison between two users (shareable link) |

Both routes are **public** — no authentication required.  
`{userA}` and `{userB}` are route-model-bound to `User` by their ULID (the route key Laravel derives from `HasUlids`).

---

## Entry Points

### Profile → "Compare with me"
On `ShowProfile` (`app/Livewire/Users/ShowProfile.php`), when the logged-in user is viewing **someone else's** profile, show a **"Compare with me"** button that links to `/compare/{auth()->id()}/{user->id}`.

When viewing as a guest or viewing your own profile, the button is hidden.

### Standalone `/compare`
Two Flux search/autocomplete inputs (User name search) bound via Livewire. Selecting both users navigates to `/compare/{userA}/{userB}` via `$this->redirect()`.

---

## Component

**Class:** `app/Livewire/Users/CompareUsers.php`  
**View:** `resources/views/livewire/users/compare-users.blade.php`  
**Route name:** `users.compare`

### Properties

```php
public ?User $userA = null;   // route-model-bound
public ?User $userB = null;   // route-model-bound

#[Url] public string $searchA = '';
#[Url] public string $searchB = '';
```

### Data passed to view

| Variable | Type | Description |
|---|---|---|
| `$statsA` / `$statsB` | `?UserStat` | Overall stats for each user |
| `$weeklyA` / `$weeklyB` | `Collection<UserWeeklyStat>` | Weekly stats, ordered by week_number |
| `$allWeeks` | `Collection<int>` | Union of all week numbers across both users |
| `$matches` | `Collection` | Completed fixtures with both users' predictions joined |
| `$accuracyA` / `$accuracyB` | `float` | Computed accuracy % |
| `$rankA` / `$rankB` | `int` | Global rank from UserStat (COUNT query, same as ShowProfile) |

### Match list query

Fetch all `Fixture` records with `status = Completed`, eager-load both users' `Prediction` records in a single query (use `whereIn` on user IDs). Only include fixtures where **at least one** of the two users has a scored prediction.

---

## Layout (approved)

```
[Search: User A]  vs  [Search: User B]

┌──────────┬──────────┬──────────┐
│  Alice   │          │   Bob    │
│  Rank #3 │          │  Rank #7 │
├──────────┼──────────┼──────────┤
│   142    │  Points  │   98     │
│   72%    │ Accuracy │   61%    │
│    8     │  Exact   │    5     │
│   48     │ Preds    │   44     │
└──────────┴──────────┴──────────┘

Week by Week
┌────────┬────────┬────────┬────────┐
│ Wk 1   │ Wk 2   │ Wk 3   │ Wk 4   │
│ A: 24  │ A: 38  │ A: 58  │ A: 22  │
│ B: 18  │ B: 22  │ B: 40  │ B: 18  │
└────────┴────────┴────────┴────────┘

Match by Match (completed only)
Alice          Match            Bob
2–1 ✓    Brazil 2–1 France    1–0 ~
3–0 ★   Germany 3–0 Spain     1–1 ✗
0–1 ✗   Argentina 1–1 England  2–0 ✗
```

**Badge legend:** ★ exact score (3 pts) · ✓ correct outcome (1 pt) · ✗ wrong (0 pts)  
**Colour coding:** User A = amber, User B = blue (consistent with existing leaderboard highlight colours)

---

## State: No users selected

When `/compare` is accessed with no users, show the two search inputs and a prompt: *"Search for two players to compare."*

## State: One user missing stats

If a user has no `UserStat` record yet (they haven't had any predictions scored), show their name but display `—` for all stat values. Don't error.

## State: User not found

If either route segment doesn't resolve to a real user, abort with a 404.

---

## Search Autocomplete

Each search input queries `users` by name (`LIKE %term%`) excluding `is_dummy = true`, returning up to 10 results. Implemented as a Livewire `$searchA` / `$searchB` string property with a `updatedSearchA` / `updatedSearchB` computed list rendered as a dropdown.

When a user is selected from the dropdown, `$this->redirect(route('users.compare', [$selectedA->id, $selectedB->id]))` navigates to the pre-filled URL.

---

## Sidebar Navigation

Add a link to `/compare` in the sidebar under a new **Players** group (or append to an existing group if one exists). Icon: `users`.

---

## Tests

| Test | Assertion |
|---|---|
| Public access | `GET /compare` returns 200 without auth |
| Pre-filled URL | `GET /compare/{a}/{b}` renders both users' names |
| Stat tiles | View receives correct points/accuracy/rank for both users |
| Weekly grid | All week numbers from both users appear in `$allWeeks` |
| Match list | Only completed fixtures with at least one scored prediction appear |
| Dummy excluded from search | `is_dummy` users do not appear in autocomplete results |
| Unknown user 404 | Invalid user ID in URL returns 404 |
| Profile button | "Compare with me" link appears when logged in and viewing another user |
| Profile button hidden | Button absent when viewing own profile or as guest |

---

## Files

### New
- `app/Livewire/Users/CompareUsers.php`
- `resources/views/livewire/users/compare-users.blade.php`
- `tests/Feature/Users/CompareUsersTest.php`

### Modified
- `routes/web.php` — add two routes (`/compare` and `/compare/{userA}/{userB}`)
- `app/Livewire/Users/ShowProfile.php` — add "Compare with me" button logic
- `resources/views/livewire/users/show-profile.blade.php` — render the button
- `resources/views/layouts/app/sidebar.blade.php` — add Compare nav link
