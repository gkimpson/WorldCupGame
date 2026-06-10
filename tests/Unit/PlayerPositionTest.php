<?php

use App\Enums\PlayerPosition;

it('maps BBC position labels to the four broad categories', function (string $raw, PlayerPosition $expected) {
    expect(PlayerPosition::fromBbc($raw))->toBe($expected);
})->with([
    ['Goalkeeper', PlayerPosition::Goalkeeper],
    ['GK', PlayerPosition::Goalkeeper],
    ['Defender', PlayerPosition::Defender],
    ['Right-back', PlayerPosition::Defender],
    ['Centre-back', PlayerPosition::Defender],
    ['Midfielder', PlayerPosition::Midfielder],
    ['Forward', PlayerPosition::Forward],
    ['Striker', PlayerPosition::Forward],
    ['Winger', PlayerPosition::Forward],
]);

it('throws on an unmappable label', function () {
    PlayerPosition::fromBbc('Manager');
})->throws(ValueError::class);

it('exposes human-readable labels', function () {
    expect(PlayerPosition::Goalkeeper->label())->toBe('Goalkeeper')
        ->and(PlayerPosition::Defender->label())->toBe('Defender')
        ->and(PlayerPosition::Midfielder->label())->toBe('Midfielder')
        ->and(PlayerPosition::Forward->label())->toBe('Forward');
});
