<?php

namespace App\Livewire\Predictions;

use App\Actions\Predictions\EnsureDefaultPredictions;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Prediction;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Predictions')]
class SubmitPredictions extends Component
{
    /** @var array<int, array{home: string, away: string, knockout_outcome: string}> */
    public array $scores = [];

    /** @var array<int, bool> */
    public array $savedFixtures = [];

    public function mount(EnsureDefaultPredictions $ensureDefaultPredictions): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $ensureDefaultPredictions->forUser($user);
        }

        $fixtureIds = Fixture::orderBy('scheduled_at')->orderBy('id')->pluck('id');

        foreach ($fixtureIds as $id) {
            $this->scores[$id] = ['home' => '', 'away' => '', 'knockout_outcome' => ''];
        }

        if ($user instanceof User) {
            Prediction::where('user_id', $user->id)
                ->whereIn('fixture_id', $fixtureIds)
                ->get(['fixture_id', 'home_score', 'away_score', 'knockout_outcome'])
                ->each(function (Prediction $p): void {
                    $this->scores[$p->fixture_id] = [
                        'home' => (string) $p->home_score,
                        'away' => (string) $p->away_score,
                        'knockout_outcome' => $p->knockout_outcome?->value ?? '',
                    ];
                });
        }
    }

    public function saveFixture(int $fixtureId): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $fixture = Fixture::find($fixtureId);
        abort_unless(
            $fixture instanceof Fixture
            && $fixture->status === FixtureStatus::Scheduled
            && ! $fixture->isLocked(),
            403
        );

        $this->validate([
            "scores.{$fixtureId}.home" => ['required', 'integer', 'min:0', 'max:20'],
            "scores.{$fixtureId}.away" => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $home = (int) $this->scores[$fixtureId]['home'];
        $away = (int) $this->scores[$fixtureId]['away'];
        $knockoutOutcome = null;

        if ($fixture->stage->isKnockout()) {
            if ($home === $away) {
                $this->validate([
                    "scores.{$fixtureId}.knockout_outcome" => [
                        'required',
                        'in:home_win_aet,away_win_aet,home_win_pens,away_win_pens',
                    ],
                ]);
                $knockoutOutcome = $this->scores[$fixtureId]['knockout_outcome'];
            } else {
                $knockoutOutcome = $home > $away ? 'home_win' : 'away_win';
            }
        }

        Prediction::updateOrCreate(
            ['user_id' => $user->id, 'fixture_id' => $fixtureId],
            [
                'home_score' => $home,
                'away_score' => $away,
                'knockout_outcome' => $knockoutOutcome,
            ],
        );

        $this->savedFixtures[$fixtureId] = true;

        Flux::toast(variant: 'success', text: __('Prediction saved.'));
    }

    public function save(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->validate([
            'scores.*.home' => ['nullable', 'integer', 'min:0', 'max:20'],
            'scores.*.away' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $saved = 0;

        Fixture::whereIn('id', array_keys($this->scores))
            ->where('status', FixtureStatus::Scheduled)
            ->get(['id', 'stage', 'scheduled_at', 'is_locked'])
            ->reject(fn (Fixture $f): bool => $f->isLocked())
            ->each(function (Fixture $fixture) use ($user, &$saved): void {
                $fixtureId = $fixture->id;
                $home = $this->scores[$fixtureId]['home'] ?? '';
                $away = $this->scores[$fixtureId]['away'] ?? '';

                if ($home === '' || $away === '') {
                    return;
                }

                $homeScore = (int) $home;
                $awayScore = (int) $away;
                $knockoutOutcome = null;

                if ($fixture->stage->isKnockout()) {
                    if ($homeScore === $awayScore) {
                        $knockoutOutcome = $this->scores[$fixtureId]['knockout_outcome'] ?? '';
                        if (! in_array($knockoutOutcome, ['home_win_aet', 'away_win_aet', 'home_win_pens', 'away_win_pens'], true)) {
                            return;
                        }
                    } else {
                        $knockoutOutcome = $homeScore > $awayScore ? 'home_win' : 'away_win';
                    }
                }

                Prediction::updateOrCreate(
                    ['user_id' => $user->id, 'fixture_id' => $fixtureId],
                    [
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'knockout_outcome' => $knockoutOutcome,
                    ],
                );

                $saved++;
            });

        Flux::toast(variant: 'success', text: trans_choice(':count prediction saved.|:count predictions saved.', $saved, ['count' => $saved]));
    }

    public function render(): View
    {
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Fixture $f): string => $f->stage->value);

        return view('livewire.predictions.submit-predictions', [
            'fixturesByStage' => $fixtures,
        ]);
    }
}
