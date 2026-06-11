<?php

namespace App\Livewire\Fixtures;

use App\Actions\Predictions\EnsureDefaultPredictions;
use App\Models\Fixture;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Fixtures')]
class IndexFixtures extends Component
{
    public function mount(EnsureDefaultPredictions $ensureDefaultPredictions): void
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $ensureDefaultPredictions->forUser($user);
        }
    }

    public function render(): View
    {
        $userId = auth()->id();

        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->when($userId !== null, function ($query) use ($userId): void {
                $query->with(['predictions' => function ($query) use ($userId): void {
                    $query->where('user_id', $userId);
                }]);
            })
            ->withCount('predictions')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Fixture $fixture): string => $fixture->stage->value);

        return view('livewire.fixtures.index-fixtures', [
            'fixturesByStage' => $fixtures,
        ]);
    }
}
