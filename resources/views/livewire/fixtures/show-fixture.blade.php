@php
    $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? __('TBD');
    $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? __('TBD');
    $hasResult = $fixture->home_score !== null && $fixture->away_score !== null;
    $statusColor = match ($fixture->status) {
        \App\Enums\FixtureStatus::Completed => 'green',
        \App\Enums\FixtureStatus::InProgress => 'amber',
        \App\Enums\FixtureStatus::Postponed => 'red',
        default => 'zinc',
    };
@endphp

<div class="flex w-full max-w-5xl flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <flux:link :href="route('fixtures.index')" wire:navigate class="text-sm">
            {{ __('Back to fixtures') }}
        </flux:link>

        <flux:badge :color="$statusColor">{{ $fixture->status->label() }}</flux:badge>
    </div>

    <flux:card class="space-y-6">
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] md:items-center">
            <div class="flex min-w-0 items-center gap-3 md:justify-end">
                <span class="truncate text-lg font-semibold md:text-right">{{ $homeName }}</span>
                <x-team-flag :team="$fixture->homeTeam" class="h-7 w-9" icon-class="h-full w-full rounded" />
            </div>

            <div class="flex min-h-16 min-w-28 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 px-4 text-2xl font-semibold dark:border-zinc-700 dark:bg-zinc-900">
                @if($hasResult)
                    {{ $fixture->home_score }} - {{ $fixture->away_score }}
                @else
                    <span class="text-base text-zinc-400">{{ __('vs') }}</span>
                @endif
            </div>

            <div class="flex min-w-0 items-center gap-3">
                <x-team-flag :team="$fixture->awayTeam" class="h-7 w-9" icon-class="h-full w-full rounded" />
                <span class="truncate text-lg font-semibold">{{ $awayName }}</span>
            </div>
        </div>

        <div class="grid gap-4 border-t border-zinc-100 pt-5 text-sm dark:border-zinc-700 sm:grid-cols-3">
            <div>
                <flux:text class="text-zinc-500">{{ __('Kickoff') }}</flux:text>
                <div class="mt-1 font-medium">
                    {{ $fixture->scheduled_at?->format('D d M Y, H:i') ?? __('TBD') }}
                </div>
            </div>

            <div>
                <flux:text class="text-zinc-500">{{ __('Venue') }}</flux:text>
                <div class="mt-1 font-medium">{{ $fixture->venue ?? __('TBD') }}</div>
            </div>

            <div>
                <flux:text class="text-zinc-500">{{ __('Location') }}</flux:text>
                <div class="mt-1 font-medium">{{ $fixture->city ?? __('TBD') }}</div>
            </div>
        </div>
    </flux:card>

    <div class="grid gap-4 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading size="lg">{{ __('Your Prediction') }}</flux:heading>

                <div class="flex flex-wrap items-center gap-2">
                    @if($outcome !== null && $outcome !== \App\Enums\PredictionOutcome::Pending)
                        @php
                            $outcomeBadgeColor = match($outcome) {
                                \App\Enums\PredictionOutcome::Exact => 'green',
                                \App\Enums\PredictionOutcome::Correct => 'amber',
                                \App\Enums\PredictionOutcome::Incorrect => 'red',
                                default => 'zinc',
                            };
                            $outcomeLabel = match($outcome) {
                                \App\Enums\PredictionOutcome::Exact => __('Exact Score'),
                                \App\Enums\PredictionOutcome::Correct => __('Correct Outcome'),
                                \App\Enums\PredictionOutcome::Incorrect => __('Incorrect'),
                                default => '',
                            };
                        @endphp
                        <flux:badge :color="$outcomeBadgeColor">{{ $outcomeLabel }}</flux:badge>
                    @endif

                    @if($userPrediction?->points !== null)
                        <flux:badge color="green">{{ trans_choice(':count point|:count points', $userPrediction->points, ['count' => $userPrediction->points]) }}</flux:badge>
                        <x-share-button
                            :title="$homeName . ' ' . $fixture->home_score . '–' . $fixture->away_score . ' ' . $awayName . ' · World Cup 104'"
                            :text="'I predicted ' . $userPrediction->home_score . '–' . $userPrediction->away_score . ' and scored ' . $userPrediction->points . ' pts on ' . $homeName . ' vs ' . $awayName . ' at World Cup 104 ⚽'"
                            :url="route('fixtures.show', $fixture)"
                        />
                    @endif
                </div>
            </div>

            @if($userPrediction !== null)
                <div class="flex items-center justify-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <span class="min-w-0 flex-1 truncate text-right font-medium">{{ $homeName }}</span>
                    <span class="shrink-0 text-xl font-semibold">{{ $userPrediction->home_score }} - {{ $userPrediction->away_score }}</span>
                    <span class="min-w-0 flex-1 truncate font-medium">{{ $awayName }}</span>
                </div>

                @if($outcome === \App\Enums\PredictionOutcome::Pending)
                    <div class="text-center">
                        <flux:badge color="zinc">{{ __('Locked · Awaiting result') }}</flux:badge>
                    </div>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-zinc-300 p-5 text-center dark:border-zinc-700">
                    <flux:text class="text-zinc-500">{{ __('You have not predicted this fixture yet.') }}</flux:text>
                    @if(!$fixture->isLocked())
                        <flux:button :href="route('predictions.index')" icon="pencil-square" wire:navigate class="mt-4">
                            {{ __('Make Prediction') }}
                        </flux:button>
                    @endif
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Prediction Split') }}</flux:heading>
                <flux:badge>{{ trans_choice(':count pick|:count picks', $predictionSummary['total'], ['count' => $predictionSummary['total']]) }}</flux:badge>
            </div>

            @if($predictionSummary['total'] > 0)
                @foreach([
                    $homeName => $predictionSummary['home_wins'],
                    __('Draw') => $predictionSummary['draws'],
                    $awayName => $predictionSummary['away_wins'],
                ] as $label => $count)
                    @php $percentage = (int) round(($count / $predictionSummary['total']) * 100); @endphp

                    <div wire:key="prediction-split-{{ $label }}" class="space-y-1.5">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0 truncate font-medium">{{ $label }}</span>
                            <span class="shrink-0 text-zinc-500">{{ $percentage }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-zinc-900 dark:bg-zinc-100" style="width: {{ $percentage }}%"></div>
                        </div>
                    </div>
                @endforeach
            @else
                <flux:text class="text-zinc-500">{{ __('No predictions have been submitted for this fixture yet.') }}</flux:text>
            @endif
        </flux:card>
    </div>
</div>
