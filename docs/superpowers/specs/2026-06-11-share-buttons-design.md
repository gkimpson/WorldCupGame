# Share Buttons Design

**Date:** 2026-06-11
**Status:** Approved

## Overview

Add share functionality across three surfaces in the app. All sharing uses the Web Share API (`navigator.share`) with a clipboard-copy fallback. Shares contain text + URL only (no generated images). A single reusable Blade component handles the mechanics; each surface composes its own share payload from data already in scope.

## Architecture

### Reusable Component: `<x-share-button>`

**File:** `resources/views/components/share-button.blade.php`

Props:
- `title` — share dialog title (short, e.g. "My World Cup 104 Stats")
- `text` — the message body
- `url` — the canonical URL to share
- `label` — button label text (default: "Share")

Behaviour (Alpine.js):
1. Call `navigator.share({ title, text, url })` if supported
2. Fallback: copy `url` to clipboard via `navigator.clipboard.writeText`, show a brief "Copied!" tooltip
3. Button is not rendered server-side for bots (wraps in `x-cloak` / `x-show` guarded by `'share' in navigator || navigator.clipboard`)

No Livewire round-trip — all data is composed inline in the Blade template and passed as props.

## Surface 1 — Profile Page (own profile only)

**Location:** `resources/views/livewire/users/show-profile.blade.php`  
**Condition:** Only rendered when `auth()->id() === $user->id`

Button placement: Header area, right-aligned next to the username/rank block.

Share payload:
```
title: "My World Cup 104 Stats"
text:  "I'm ranked #{{ $globalRank }} with {{ $totalPoints }} pts & {{ $accuracyPct }}% accuracy at World Cup 104 ⚽"
url:   route('users.show', $user)
```

If `$globalRank === 0` (no ranking yet): omit rank from text — "I've scored {{ $totalPoints }} pts at World Cup 104 ⚽"

## Surface 2 — Match Result (scored prediction only)

**Location:** `resources/views/livewire/fixtures/show-fixture.blade.php`  
**Condition:** Only rendered when `$userPrediction !== null && $userPrediction->points !== null`

Button placement: Inside the "Your Prediction" card header, inline with the points badge.

Share payload:
```
title: "{{ $homeName }} {{ $fixture->home_score }}–{{ $fixture->away_score }} {{ $awayName }} · World Cup 104"
text:  "I predicted {{ $userPrediction->home_score }}–{{ $userPrediction->away_score }} and scored {{ $userPrediction->points }} pts on {{ $homeName }} vs {{ $awayName }} at World Cup 104 ⚽"
url:   route('fixtures.show', $fixture)
```

## Surface 3 — Leaderboard Rank

**Location:** `resources/views/livewire/leaderboard/global-leaderboard.blade.php`  
**Condition:** Only rendered on the pinned entry row (`$pinnedEntry !== null`)

Button placement: Extra cell on the right of the pinned "You" row.

Share payload:
```
title: "My World Cup 104 Rank"
text:  "I'm ranked #{{ $pinnedEntry['rank'] }} with {{ $pinnedEntry['total_points'] }} pts on the World Cup 104 Global Leaderboard 🏆"
url:   route('leaderboard.global')
```

## Component API Summary

```blade
<x-share-button
    title="My World Cup 104 Stats"
    text="I'm ranked #42 with 245 pts..."
    url="{{ route('users.show', $user) }}"
    label="Share my stats"
/>
```

## Fallback Behaviour

| Browser support | Behaviour |
|---|---|
| `navigator.share` available | Opens native OS share sheet |
| No share API, has clipboard API | Copies URL, shows "Copied!" tooltip for 2s |
| Neither available | Button hidden entirely |

## Out of Scope

- Accuracy and Perfect leaderboard share buttons (can be added later following same pattern)
- League leaderboard share button
- OG/Twitter meta tags (separate concern, not part of this feature)
- Generated image cards
