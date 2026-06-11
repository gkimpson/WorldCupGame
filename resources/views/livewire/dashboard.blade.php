<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Stat cards --}}
    <div class="grid gap-4 md:grid-cols-3">
        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Global Rank</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                @if ($globalRank > 0)
                    #{{ $globalRank }}
                @else
                    &mdash;
                @endif
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Points</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $totalPoints }}
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Predictions Made</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $predictionsMade }} <span class="text-lg font-normal text-zinc-400">/ {{ \App\Models\Fixture::TOTAL_WORLD_CUP_MATCHES }}</span>
            </div>
        </flux:card>
    </div>

    @if (! $hasAnyPredictions)
        {{-- Empty state --}}
        <flux:card class="flex flex-col items-center gap-4 py-12 text-center">
            <flux:heading size="lg">You haven't made any predictions yet</flux:heading>
            <flux:text class="max-w-sm text-zinc-500">The tournament is underway — get your picks in before the next kick-off!</flux:text>
            <flux:button href="{{ route('predictions.index') }}" variant="primary" class="mt-2">
                Make Predictions
            </flux:button>
        </flux:card>
    @else
        {{-- Two column layout --}}
        <div class="grid gap-6 lg:grid-cols-5">

            {{-- Left column: upcoming fixtures + league --}}
            <div class="flex flex-col gap-6 lg:col-span-3">

                {{-- Upcoming fixtures --}}
                <flux:card class="p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="sm">Upcoming Fixtures</flux:heading>
                        <flux:button href="{{ route('predictions.index') }}" size="sm" variant="ghost">
                            Predict all →
                        </flux:button>
                    </div>

                    @forelse ($upcomingFixtures as $fixture)
                        <div class="flex items-center justify-between border-b border-zinc-100 py-3 last:border-0 dark:border-zinc-800">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <x-team-flag :team="$fixture->homeTeam" />
                                <span>{{ $fixture->homeTeam?->name ?? $fixture->home_team_placeholder }}</span>
                                <span class="text-zinc-400">vs</span>
                                <span>{{ $fixture->awayTeam?->name ?? $fixture->away_team_placeholder }}</span>
                                <x-team-flag :team="$fixture->awayTeam" />
                            </div>
                            <flux:text class="text-xs text-zinc-400">
                                <x-fixture-kickoff :fixture="$fixture" />
                            </flux:text>
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">No upcoming fixtures.</flux:text>
                    @endforelse
                </flux:card>

                {{-- League widget --}}
                @if ($topLeague !== null)
                    <flux:card class="p-5">
                        <flux:heading size="sm" class="mb-3">Your League</flux:heading>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $topLeague->name }}</p>
                                <flux:text class="text-sm text-zinc-500">Rank #{{ $topLeagueRank }}</flux:text>
                            </div>
                            <flux:button href="{{ route('leagues.show', $topLeague) }}" size="sm" variant="ghost">
                                View →
                            </flux:button>
                        </div>
                    </flux:card>
                @endif
            </div>

            {{-- Right column: recent results --}}
            <div class="lg:col-span-2">
                <flux:card class="p-5">
                    <flux:heading size="sm" class="mb-4">Recent Results</flux:heading>

                    @forelse ($recentResults as $prediction)
                        <div class="border-b border-zinc-100 py-3 last:border-0 dark:border-zinc-800">
                            <div class="flex items-center justify-between text-sm">
                                <div class="flex items-center gap-1 font-medium">
                                    <x-team-flag :team="$prediction->fixture->homeTeam" />
                                    <span>{{ $prediction->fixture->homeTeam?->name ?? $prediction->fixture->home_team_placeholder }}</span>
                                    <span class="mx-1 font-bold">{{ $prediction->fixture->home_score }}–{{ $prediction->fixture->away_score }}</span>
                                    <span>{{ $prediction->fixture->awayTeam?->name ?? $prediction->fixture->away_team_placeholder }}</span>
                                    <x-team-flag :team="$prediction->fixture->awayTeam" />
                                </div>
                                <flux:badge
                                    size="sm"
                                    :color="$prediction->points >= 3 ? 'green' : ($prediction->points >= 1 ? 'amber' : 'red')"
                                >
                                    +{{ $prediction->points }}pts
                                </flux:badge>
                            </div>
                            <flux:text class="mt-0.5 text-xs text-zinc-400">
                                You predicted: {{ $prediction->home_score }}–{{ $prediction->away_score }}
                            </flux:text>
                        </div>
                    @empty
                        <flux:text class="text-zinc-500">No scored predictions yet.</flux:text>
                    @endforelse
                </flux:card>
            </div>
        </div>
    @endif

</div>
