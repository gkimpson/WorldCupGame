<?php

namespace App\Actions\Predictions;

use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use App\Services\Scoring\FixturePredictionScorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EnsureDefaultPredictions
{
    public function __construct(private FixturePredictionScorer $scorer) {}

    public function forUser(User $user): int
    {
        $created = 0;

        Fixture::query()
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now()->addHours(2))
            ->whereIn('status', [
                FixtureStatus::Scheduled,
                FixtureStatus::InProgress,
                FixtureStatus::Completed,
            ])
            ->whereDoesntHave('predictions', function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->cursor()
            ->each(function (Fixture $fixture) use ($user, &$created): void {
                $prediction = $this->createDefaultPrediction($fixture, $user->id);

                if ($prediction->wasRecentlyCreated) {
                    $created++;
                }
            });

        return $created;
    }

    public function forFixture(Fixture $fixture): int
    {
        if (! $this->isPastPredictionDeadline($fixture)) {
            return 0;
        }

        $created = 0;

        User::query()
            ->whereDoesntHave('predictions', function (Builder $query) use ($fixture): void {
                $query->where('fixture_id', $fixture->id);
            })
            ->chunkById(200, function (Collection $users) use ($fixture, &$created): void {
                foreach ($users as $user) {
                    $prediction = $this->createDefaultPrediction($fixture, $user->id);

                    if ($prediction->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    private function createDefaultPrediction(Fixture $fixture, int $userId): Prediction
    {
        return Prediction::firstOrCreate(
            [
                'user_id' => $userId,
                'fixture_id' => $fixture->id,
            ],
            [
                'home_score' => 0,
                'away_score' => 0,
                'points' => $this->defaultPointsFor($fixture),
            ],
        );
    }

    private function defaultPointsFor(Fixture $fixture): ?int
    {
        if ($fixture->status !== FixtureStatus::Completed) {
            return null;
        }

        $score = $this->scorer->score($fixture, 0, 0);

        if (! $score->isScored()) {
            return null;
        }

        return $score->points;
    }

    private function isPastPredictionDeadline(Fixture $fixture): bool
    {
        return $fixture->scheduled_at !== null
            && $fixture->scheduled_at <= now()->addHours(2);
    }
}
