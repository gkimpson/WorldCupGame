<?php

namespace App\Livewire\Fixtures;

use App\Models\Fixture;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Fixtures')]
class IndexFixtures extends Component
{
    public function render(): View
    {
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
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
