<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Enums\KnockoutOutcome;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;

function createKnockoutFixture(array $attributes = []): Fixture
{
    return Fixture::factory()->create([
        'stage' => FixtureStage::RoundOf32,
        'status' => FixtureStatus::Scheduled,
        ...$attributes,
    ]);
}

describe('Knockout prediction validation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
    });

    it('stores knockout_outcome for draw predictions', function () {
        $fixture = createKnockoutFixture();

        Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 1,
            'away_score' => 1,
            'knockout_outcome' => KnockoutOutcome::HomeWinAet,
        ]);

        $prediction = Prediction::where('fixture_id', $fixture->id)->first();
        expect($prediction->knockout_outcome)->toBe(KnockoutOutcome::HomeWinAet);
    });

    it('stores knockout_outcome for non-draw predictions', function () {
        $fixture = createKnockoutFixture();

        Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 2,
            'away_score' => 1,
            'knockout_outcome' => KnockoutOutcome::HomeWin,
        ]);

        $prediction = Prediction::where('fixture_id', $fixture->id)->first();
        expect($prediction->knockout_outcome)->toBe(KnockoutOutcome::HomeWin);
    });

    it('stores null knockout_outcome for group stage predictions', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::GroupStage,
            'status' => FixtureStatus::Scheduled,
        ]);

        Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 2,
            'away_score' => 1,
            'knockout_outcome' => null,
        ]);

        $prediction = Prediction::where('fixture_id', $fixture->id)->first();
        expect($prediction->knockout_outcome)->toBeNull();
    });

    it('accepts all valid knockout_outcome values', function () {
        $fixture = createKnockoutFixture();

        $validOutcomes = [
            KnockoutOutcome::HomeWin,
            KnockoutOutcome::AwayWin,
            KnockoutOutcome::HomeWinAet,
            KnockoutOutcome::AwayWinAet,
            KnockoutOutcome::HomeWinPens,
            KnockoutOutcome::AwayWinPens,
        ];

        foreach ($validOutcomes as $outcome) {
            Prediction::updateOrCreate(
                ['user_id' => $this->user->id, 'fixture_id' => $fixture->id],
                [
                    'home_score' => 1,
                    'away_score' => 1,
                    'knockout_outcome' => $outcome,
                ]
            );

            $prediction = Prediction::where('fixture_id', $fixture->id)->first();
            expect($prediction->knockout_outcome)->toBe($outcome);
        }
    });

    it('casts knockout_outcome to enum', function () {
        $fixture = createKnockoutFixture();

        Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 1,
            'away_score' => 1,
            'knockout_outcome' => 'home_win_aet',
        ]);

        $prediction = Prediction::where('fixture_id', $fixture->id)->first();
        expect($prediction->knockout_outcome)->toBeInstanceOf(KnockoutOutcome::class);
        expect($prediction->knockout_outcome->value)->toBe('home_win_aet');
    });

    it('can be updated with different knockout_outcome', function () {
        $fixture = createKnockoutFixture();

        $prediction = Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 2,
            'away_score' => 1,
            'knockout_outcome' => KnockoutOutcome::HomeWin,
        ]);

        $prediction->update(['knockout_outcome' => KnockoutOutcome::AwayWin]);

        expect($prediction->fresh()->knockout_outcome)->toBe(KnockoutOutcome::AwayWin);
    });

    it('handles null knockout_outcome properly', function () {
        $fixture = createKnockoutFixture();

        $prediction = Prediction::create([
            'user_id' => $this->user->id,
            'fixture_id' => $fixture->id,
            'home_score' => 2,
            'away_score' => 1,
            'knockout_outcome' => null,
        ]);

        expect($prediction->knockout_outcome)->toBeNull();
        expect($prediction->knockoutOutcome)->toBeNull();
    });
});
