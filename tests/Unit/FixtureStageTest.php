<?php

use App\Enums\FixtureStage;

it('returns human-readable labels', function (FixtureStage $stage, string $expected) {
    expect($stage->label())->toBe($expected);
})->with([
    [FixtureStage::GroupStage, 'Group Stage'],
    [FixtureStage::RoundOf32, 'Round of 32'],
    [FixtureStage::RoundOf16, 'Round of 16'],
    [FixtureStage::QuarterFinal, 'Quarter-Final'],
    [FixtureStage::SemiFinal, 'Semi-Final'],
    [FixtureStage::ThirdPlace, 'Third Place Play-off'],
    [FixtureStage::Final, 'Final'],
]);

it('identifies group stage as non-knockout', function () {
    expect(FixtureStage::GroupStage->isKnockout())->toBeFalse();
});

it('identifies all other stages as knockout', function (FixtureStage $stage) {
    expect($stage->isKnockout())->toBeTrue();
})->with([
    [FixtureStage::RoundOf32],
    [FixtureStage::RoundOf16],
    [FixtureStage::QuarterFinal],
    [FixtureStage::SemiFinal],
    [FixtureStage::ThirdPlace],
    [FixtureStage::Final],
]);
