<?php

namespace App\Livewire\League;

use App\Models\Fixture;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('League')]
class ShowLeague extends Component
{
    public League $league;

    public function mount(League $league): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User && $league->hasMember($user), 403);

        $this->league = $league;
    }

    public function render(): View
    {
        $members = LeagueMember::with(['user.stat'])
            ->where('league_id', $this->league->id)
            ->get()
            ->sort(function (LeagueMember $firstMember, LeagueMember $secondMember): int {
                $firstPoints = $firstMember->user->stat?->total_points ?? 0;
                $secondPoints = $secondMember->user->stat?->total_points ?? 0;

                if ($firstPoints !== $secondPoints) {
                    return $secondPoints <=> $firstPoints;
                }

                return $firstMember->id <=> $secondMember->id;
            })
            ->values();

        return view('livewire.league.show-league', [
            'entries' => $members->map(fn (LeagueMember $member, int $index): array => [
                'rank' => $index + 1,
                'name' => $member->user->name,
                'total_points' => $member->user->stat?->total_points ?? 0,
                'predictions_made' => $member->user->stat?->predictions_made ?? 0,
                'role' => $member->role->label(),
                'is_current_user' => $member->user_id === Auth::id(),
            ])->all(),
            'totalMatches' => Fixture::TOTAL_WORLD_CUP_MATCHES,
        ]);
    }
}
