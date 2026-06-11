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
    /** @var array<int, array{home: string, away: string}> */
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
            $this->scores[$id] = ['home' => '', 'away' => ''];
        }

        if ($user instanceof User) {
            Prediction::where('user_id', $user->id)
                ->whereIn('fixture_id', $fixtureIds)
                ->get(['fixture_id', 'home_score', 'away_score'])
                ->each(function (Prediction $p): void {
                    $this->scores[$p->fixture_id] = [
                        'home' => (string) $p->home_score,
                        'away' => (string) $p->away_score,
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

        Prediction::updateOrCreate(
            ['user_id' => $user->id, 'fixture_id' => $fixtureId],
            [
                'home_score' => (int) $this->scores[$fixtureId]['home'],
                'away_score' => (int) $this->scores[$fixtureId]['away'],
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
            ->get(['id', 'scheduled_at', 'is_locked'])
            ->reject(fn (Fixture $f): bool => $f->isLocked())
            ->pluck('id')
            ->each(function (int $fixtureId) use ($user, &$saved): void {
                $home = $this->scores[$fixtureId]['home'] ?? '';
                $away = $this->scores[$fixtureId]['away'] ?? '';

                if ($home === '' || $away === '') {
                    return;
                }

                Prediction::updateOrCreate(
                    ['user_id' => $user->id, 'fixture_id' => $fixtureId],
                    ['home_score' => (int) $home, 'away_score' => (int) $away],
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
