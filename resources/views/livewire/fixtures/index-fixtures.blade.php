<div class="flex w-full max-w-6xl flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Fixtures') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Browse every match and open a fixture for predictions, scores, and points.') }}</flux:text>

        <flux:button :href="route('predictions.index')" icon="clipboard-document-list" wire:navigate class="mt-4">
            {{ __('My Predictions') }}
        </flux:button>
    </div>

    @forelse($fixturesByStage as $stageValue => $fixtures)
        @php $stage = \App\Enums\FixtureStage::from($stageValue); @endphp

        <section class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ $stage->label() }}</flux:heading>
                <flux:badge>{{ $fixtures->count() }}</flux:badge>
            </div>

            <flux:card class="p-0">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach($fixtures as $fixture)
                        @php
                            $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? __('TBD');
                            $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? __('TBD');
                            $hasResult = $fixture->home_score !== null && $fixture->away_score !== null;
                            $userPrediction = $fixture->predictions->first();
                            $pointsBadgeColor = match ($userPrediction?->points) {
                                3 => 'green',
                                1 => 'amber',
                                0 => 'red',
                                default => 'zinc',
                            };
                        @endphp

                        <a
                            wire:key="fixture-{{ $fixture->id }}"
                            href="{{ route('fixtures.show', $fixture) }}"
                            wire:navigate
                            class="grid gap-3 px-4 py-4 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60 md:grid-cols-[5rem_minmax(0,1fr)_auto_minmax(0,1fr)_9rem_8rem] md:items-center"
                        >
                            <div class="flex items-center gap-2 text-xs text-zinc-500 md:block">
                                @if($fixture->match_number !== null)
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('Match :number', ['number' => $fixture->match_number]) }}</span>
                                @endif

                                @if($fixture->scheduled_at !== null)
                                    <span class="md:mt-1 md:block">{{ $fixture->scheduled_at->format('d M H:i') }}</span>
                                @else
                                    <span class="md:mt-1 md:block">{{ __('TBD') }}</span>
                                @endif
                            </div>

                            <div class="flex min-w-0 items-center gap-2 md:justify-end">
                                <span class="truncate text-sm font-medium md:text-right">{{ $homeName }}</span>
                                <x-team-flag :team="$fixture->homeTeam" />
                            </div>

                            <div class="flex h-9 min-w-20 items-center justify-center rounded-md border border-zinc-200 bg-white px-3 text-sm font-semibold dark:border-zinc-700 dark:bg-zinc-900">
                                @if($hasResult)
                                    {{ $fixture->home_score }} - {{ $fixture->away_score }}
                                @else
                                    <span class="text-zinc-400">{{ __('vs') }}</span>
                                @endif
                            </div>

                            <div class="flex min-w-0 items-center gap-2">
                                <x-team-flag :team="$fixture->awayTeam" />
                                <span class="truncate text-sm font-medium">{{ $awayName }}</span>
                            </div>

                            <div class="flex items-center gap-2 md:justify-end">
                                @if($userPrediction?->points !== null)
                                    <flux:badge size="sm" :color="$pointsBadgeColor">
                                        {{ trans_choice(':count pt|:count pts', $userPrediction->points, ['count' => $userPrediction->points]) }}
                                    </flux:badge>
                                @elseif($userPrediction !== null)
                                    <flux:badge size="sm" color="zinc">
                                        {{ __('Pick :score', ['score' => "{$userPrediction->home_score}-{$userPrediction->away_score}"]) }}
                                    </flux:badge>
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('No pick') }}</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 md:justify-end">
                                <flux:badge size="sm">{{ $fixture->status->label() }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ trans_choice(':count pick|:count picks', $fixture->predictions_count, ['count' => $fixture->predictions_count]) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </flux:card>
        </section>
    @empty
        <flux:card>
            <flux:text class="text-zinc-500">{{ __('No fixtures available yet.') }}</flux:text>
        </flux:card>
    @endforelse
</div>
