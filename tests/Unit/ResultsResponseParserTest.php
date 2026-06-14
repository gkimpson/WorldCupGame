<?php

use App\Services\Results\ResultsResponseParser;

beforeEach(function () {
    $this->parser = new ResultsResponseParser;
});

// ── extractJson ──────────────────────────────────────────────────────────────

it('strips json markdown fences', function () {
    $input = "```json\n[{\"id\":1}]\n```";
    expect($this->parser->extractJson($input))->toBe('[{"id":1}]');
});

it('strips plain markdown fences', function () {
    $input = "```\n[{\"id\":1}]\n```";
    expect($this->parser->extractJson($input))->toBe('[{"id":1}]');
});

it('passes plain json through unchanged', function () {
    $input = '[{"id":1,"home_score":2}]';
    expect($this->parser->extractJson($input))->toBe($input);
});

// ── normalise ────────────────────────────────────────────────────────────────

it('normalises a valid completed result', function () {
    $decoded = [['id' => 'abc', 'home_score' => 2, 'away_score' => 0, 'status' => 'completed']];
    $result = $this->parser->normalise($decoded);

    expect($result)->toHaveKey('abc');
    expect($result['abc']['home_score'])->toBe(2);
    expect($result['abc']['away_score'])->toBe(0);
    expect($result['abc']['status'])->toBe('completed');
});

it('returns null scores when scores are missing', function () {
    $decoded = [['id' => 'abc', 'status' => 'not_started']];
    $result = $this->parser->normalise($decoded);

    expect($result['abc']['home_score'])->toBeNull();
    expect($result['abc']['away_score'])->toBeNull();
});

it('returns null scores when scores are wrong type', function () {
    $decoded = [['id' => 'abc', 'home_score' => '2', 'away_score' => '0', 'status' => 'completed']];
    $result = $this->parser->normalise($decoded);

    expect($result['abc']['home_score'])->toBeNull();
    expect($result['abc']['away_score'])->toBeNull();
});

it('defaults status to not_started when missing', function () {
    $decoded = [['id' => 'abc', 'home_score' => 1, 'away_score' => 0]];
    $result = $this->parser->normalise($decoded);

    expect($result['abc']['status'])->toBe('not_started');
});

it('keys results by id', function () {
    $decoded = [
        ['id' => 'aaa', 'home_score' => 1, 'away_score' => 0, 'status' => 'completed'],
        ['id' => 'bbb', 'home_score' => 2, 'away_score' => 1, 'status' => 'completed'],
    ];
    $result = $this->parser->normalise($decoded);

    expect($result)->toHaveKeys(['aaa', 'bbb']);
});
