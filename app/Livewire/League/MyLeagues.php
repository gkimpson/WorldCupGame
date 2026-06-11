<?php

namespace App\Livewire\League;

use App\Enums\LeagueMemberRole;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Private Leagues')]
class MyLeagues extends Component
{
    public string $name = '';

    public string $joinCode = '';

    public function createLeague(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $league = DB::transaction(function () use ($user, $validated): League {
            $league = League::create([
                'owner_user_id' => $user->id,
                'name' => $validated['name'],
                'join_code' => League::generateJoinCode(),
            ]);

            LeagueMember::create([
                'league_id' => $league->id,
                'user_id' => $user->id,
                'role' => LeagueMemberRole::Owner,
                'joined_at' => now(),
            ]);

            return $league;
        });

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('League created.'));

        $this->redirectRoute('leagues.show', ['league' => $league], navigate: true);
    }

    public function joinLeague(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $validated = $this->validate([
            'joinCode' => ['required', 'string', 'max:12'],
        ]);

        $joinCode = Str::upper($validated['joinCode']);
        $league = League::where('join_code', $joinCode)->first();

        if ($league === null) {
            $this->addError('joinCode', __('No league uses that code.'));

            return;
        }

        LeagueMember::firstOrCreate(
            [
                'league_id' => $league->id,
                'user_id' => $user->id,
            ],
            [
                'role' => LeagueMemberRole::Member,
                'joined_at' => now(),
            ],
        );

        $this->reset('joinCode');

        Flux::toast(variant: 'success', text: __('League joined.'));

        $this->redirectRoute('leagues.show', ['league' => $league], navigate: true);
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        /** @var Collection<int, LeagueMember> $leagues */
        $leagues = LeagueMember::with(['league.owner'])
            ->where('user_id', $user->id)
            ->latest('joined_at')
            ->get();

        return view('livewire.league.my-leagues', [
            'leagues' => $leagues,
        ]);
    }
}
