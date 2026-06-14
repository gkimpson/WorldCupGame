# AI Results Provider Refactor — Design Spec

**Date:** 2026-06-14
**Status:** Approved

## Problem

`GeminiResultsService` does everything: builds prompts, calls the Gemini API, parses/strips JSON, and normalises the result shape. Adding an OpenAI equivalent would duplicate all of that logic. The command (`SyncResultsFromGemini`) is also coupled to the concrete class rather than an interface.

## Goal

- Add `OpenAiResultsService` (using `gpt-4o-mini` + web search) that does the same job as `GeminiResultsService`.
- Extract shared parsing logic into a reusable class.
- Introduce a contract so both providers are swappable and independently testable.
- Follow the existing `FootballDataProviderInterface` pattern already established in `app/Services/ApiFootball/`.

## Architecture

```
app/Services/Results/
├── Contracts/
│   └── WorldCupResultsProviderInterface.php
├── ResultsResponseParser.php
├── GeminiResultsService.php        (moved from app/Services/Gemini/)
└── OpenAiResultsService.php        (new)
```

`app/Services/Gemini/` is retired. `GeminiResultsService` moves into the unified `Results/` namespace.

## Components

### `WorldCupResultsProviderInterface`

```php
interface WorldCupResultsProviderInterface
{
    /** @return array<int, array{home_score: int|null, away_score: int|null, status: string}> */
    public function fetchResults(Collection $fixtures): array;

    public function fetchRawResults(?string $specificDate = null): string;
}
```

Both services implement this contract. The command and any future callers type-hint against the interface, not the concrete class.

### `ResultsResponseParser`

Holds the two responsibilities currently duplicated across both services:

- `extractJson(string $text): string` — strips markdown code fences so `json_decode` can parse cleanly.
- `normalise(array $decoded): array` — maps raw decoded array to the canonical `[home_score, away_score, status]` shape, with type guards.

Injected into both services via constructor. Can be unit-tested in isolation.

### `GeminiResultsService` (refactored)

- Moves to `App\Services\Results\GeminiResultsService`.
- Implements `WorldCupResultsProviderInterface`.
- Injects `ResultsResponseParser` and delegates parsing to it.
- Keeps its own private `buildPrompt()` and `buildRawPrompt()` — prompt phrasing may diverge between providers.
- API call shape unchanged (Google Generative Language API + `google_search` tool).

### `OpenAiResultsService` (new)

- Implements `WorldCupResultsProviderInterface`.
- Constructor: `string $apiKey`, `string $model` (default `gpt-4o-mini`), `ResultsResponseParser $parser`.
- Uses the OpenAI Chat Completions endpoint: `https://api.openai.com/v1/chat/completions`.
- Enables web search grounding via the `web_search_preview` tool in the request body.
- Extracts response text from `choices[0].message.content`.
- Has its own private `buildPrompt()` and `buildRawPrompt()` methods — same intent as Gemini's, phrasing adapted for OpenAI's search grounding.
- Throws `RuntimeException` on API failure, identical to Gemini.

### `SyncResultsFromOpenAi` (new Artisan command)

Mirrors `SyncResultsFromGemini` exactly:
- Same options: `--dry-run`, `--dummy`, `--data-only`, `--specific-date`.
- Same handle logic.
- Injects `OpenAiResultsService` (via interface).
- Signature: `world-cup:sync-results-openai`.

`SyncResultsFromGemini` is updated only to swap its concrete injection for the interface type-hint.

## Config & Bindings

`config/services.php` gains an `openai` entry:

```php
'openai' => [
    'key'   => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],
```

`AppServiceProvider` registers both as singletons and also binds the interface to whichever provider is preferred by default (or leaves both as named bindings for explicit injection).

## Error Handling

Both services throw `RuntimeException` on HTTP failure. Callers (commands) catch this and return `Command::FAILURE`. No change to existing error handling behaviour.

## Testing

- Unit test `ResultsResponseParser` — `extractJson` strips fences, `normalise` handles missing keys and wrong types.
- Feature test `OpenAiResultsService::fetchResults` — fake HTTP response, assert normalised output.
- Feature test `OpenAiResultsService::fetchRawResults` — fake HTTP, assert raw text returned.
- Existing Gemini tests updated to reflect the new namespace.

## What This Is Not

- No driver pattern / single-class branching.
- No shared prompt class — prompts stay private per service (YAGNI).
- No changes to scoring, leaderboard, or event logic.
