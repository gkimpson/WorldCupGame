<div class="flex h-full w-full flex-1 flex-col gap-6">

    {{-- Header --}}
    <div class="flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $user->name }}</flux:heading>
            <flux:text class="text-zinc-500">
                @if ($globalRank > 0)
                    Global Rank #{{ $globalRank }}
                @else
                    No ranking yet
                @endif
            </flux:text>
        </div>

        @if (auth()->id() === $user->id)
            @php
                $shareText = $globalRank > 0
                    ? "I'm ranked #{$globalRank} with {$totalPoints} pts & {$accuracyPct}% accuracy at World Cup 104 ⚽"
                    : "I've scored {$totalPoints} pts at World Cup 104 ⚽";
            @endphp
            <x-share-button
                title="My World Cup 104 Stats"
                :text="$shareText"
                :url="route('users.show', $user)"
                label="Share my stats"
            />
        @endif
    </div>

    {{-- Stat tiles --}}
    <div class="grid gap-4 md:grid-cols-4">
        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Total Points</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $totalPoints }}
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Accuracy</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $accuracyPct }}%
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Exact Scores</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $exactScores }}
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-1 p-5">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Predictions Made</flux:text>
            <div class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">
                {{ $predictionsMade }} <span class="text-lg font-normal text-zinc-400">/ {{ $totalMatches }}</span>
            </div>
        </flux:card>
    </div>

    {{-- Recent predictions --}}
    <flux:card class="p-5">
        <flux:heading size="sm" class="mb-4">Recent Predictions</flux:heading>

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
                    Predicted: {{ $prediction->home_score }}–{{ $prediction->away_score }}
                </flux:text>
            </div>
        @empty
            <flux:text class="text-zinc-500">No scored predictions yet.</flux:text>
        @endforelse
    </flux:card>

</div>
