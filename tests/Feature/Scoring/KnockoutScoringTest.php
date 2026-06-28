<?php

use App\Enums\FixtureStage;
use App\Enums\FixtureStatus;
use App\Enums\KnockoutOutcome;
use App\Models\Fixture;
use App\Services\Scoring\FixturePredictionScorer;

describe('KnockoutOutcome enum', function () {
    it('resolves HomeWin from non-draw 90-minute score', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::HomeWin);
        expect($outcome->winner())->toBe('home');
        expect($outcome->method())->toBe('normal');
    });

    it('resolves AwayWin from non-draw 90-minute score', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 2,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::AwayWin);
        expect($outcome->winner())->toBe('away');
        expect($outcome->method())->toBe('normal');
    });

    it('resolves HomeWinAet from AET result', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::QuarterFinal,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 2,
            'away_score_aet' => 1,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::HomeWinAet);
        expect($outcome->winner())->toBe('home');
        expect($outcome->method())->toBe('aet');
        expect($outcome->isDrawAt90())->toBeTrue();
    });

    it('resolves AwayWinAet from AET result', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::SemiFinal,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 1,
            'away_score_aet' => 2,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::AwayWinAet);
    });

    it('resolves HomeWinPens from penalty shootout result', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 1,
            'away_score_aet' => 1,
            'home_score_pens' => 4,
            'away_score_pens' => 2,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::HomeWinPens);
        expect($outcome->winner())->toBe('home');
        expect($outcome->method())->toBe('pens');
    });

    it('resolves AwayWinPens from penalty shootout result', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::RoundOf16,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 1,
            'away_score_aet' => 1,
            'home_score_pens' => 3,
            'away_score_pens' => 5,
        ]);

        $outcome = KnockoutOutcome::fromFixture($fixture);

        expect($outcome)->toBe(KnockoutOutcome::AwayWinPens);
    });

    it('throws when fixture has incomplete result', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => null,
            'away_score_aet' => null,
        ]);

        expect(fn () => KnockoutOutcome::fromFixture($fixture))->toThrow(RuntimeException::class);
    });

    it('throws when fixture has no 90-minute scores', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => null,
            'away_score' => 1,
        ]);

        expect(fn () => KnockoutOutcome::fromFixture($fixture))->toThrow(RuntimeException::class);
    });
});

describe('Knockout scoring with FixturePredictionScorer', function () {
    it('awards 5 points for exact 90-minute score with correct method', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 2,
            'away_score_aet' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            1,
            1,
            KnockoutOutcome::HomeWinAet,
        );

        expect($result->points)->toBe(5);
        expect($result->exactScore)->toBeTrue();
        expect($result->correctOutcome)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeTrue();
    });

    it('awards 3 points for exact 90-minute score with wrong method and wrong winner', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::QuarterFinal,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 2,
            'away_score_aet' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            1,
            1,
            KnockoutOutcome::AwayWinPens,
        );

        expect($result->points)->toBe(3);
        expect($result->exactScore)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeFalse();
    });

    it('awards 2 points for wrong score with correct winner and correct method', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::SemiFinal,
            'status' => FixtureStatus::Completed,
            'home_score' => 2,
            'away_score' => 1,
            'home_score_aet' => null,
            'away_score_aet' => null,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            3,
            0,
            KnockoutOutcome::HomeWin,
        );

        expect($result->points)->toBe(2);
        expect($result->exactScore)->toBeFalse();
        expect($result->correctOutcome)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeTrue();
    });

    it('awards 1 point for wrong score with correct winner but wrong method', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::RoundOf16,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 2,
            'away_score_aet' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            2,
            0,
            KnockoutOutcome::HomeWinPens,
        );

        expect($result->points)->toBe(1);
        expect($result->exactScore)->toBeFalse();
        expect($result->correctOutcome)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeFalse();
    });

    it('awards 0 points for wrong winner', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            1,
            2,
            KnockoutOutcome::AwayWin,
        );

        expect($result->points)->toBe(0);
        expect($result->correctOutcome)->toBeFalse();
    });

    it('does not award method point when knockout_outcome is null', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 2,
            'away_score_aet' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            1,
            1,
            null,
        );

        expect($result->points)->toBe(3);
        expect($result->exactScore)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeFalse();
    });

    it('ignores knockout_outcome for group stage matches', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::GroupStage,
            'status' => FixtureStatus::Completed,
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            2,
            1,
            null,
        );

        expect($result->points)->toBe(3);
        expect($result->knockoutMethodCorrect)->toBeFalse();
    });

    it('handles knockout with non-draw score (normal time win)', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::RoundOf32,
            'status' => FixtureStatus::Completed,
            'home_score' => 3,
            'away_score' => 2,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            3,
            2,
            KnockoutOutcome::HomeWin,
        );

        expect($result->points)->toBe(5);
        expect($result->exactScore)->toBeTrue();
        expect($result->knockoutMethodCorrect)->toBeTrue();
    });

    it('handles penalty shootout with correct method prediction', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Completed,
            'home_score' => 1,
            'away_score' => 1,
            'home_score_aet' => 1,
            'away_score_aet' => 1,
            'home_score_pens' => 5,
            'away_score_pens' => 4,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score(
            $fixture,
            1,
            1,
            KnockoutOutcome::HomeWinPens,
        );

        expect($result->points)->toBe(5);
        expect($result->knockoutMethodCorrect)->toBeTrue();
    });

    it('returns 0 points when fixture is not completed', function () {
        $fixture = Fixture::factory()->create([
            'stage' => FixtureStage::Final,
            'status' => FixtureStatus::Scheduled,
            'home_score' => null,
            'away_score' => null,
        ]);

        $scorer = app(FixturePredictionScorer::class);
        $result = $scorer->score($fixture, 1, 1, null);

        expect($result->points)->toBe(0);
        expect($result->isScored())->toBeFalse();
    });
});
