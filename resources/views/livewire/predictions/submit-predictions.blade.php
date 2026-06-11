<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
    <flux:heading size="xl">{{ __('My Predictions') }}</flux:heading>

    <form wire:submit="save" class="flex flex-col gap-8">
        @forelse ($fixturesByStage as $stageValue => $fixtures)
            @php $stage = \App\Enums\FixtureStage::from($stageValue); @endphp

            <div class="flex flex-col gap-3">
                <flux:heading size="lg">{{ $stage->label() }}</flux:heading>

                <flux:card class="p-0">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($fixtures as $fixture)
                            @php
                                $locked = $fixture->scheduled_at !== null && $fixture->scheduled_at <= $now->copy()->addHours(2);
                                $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD';
                                $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD';
                            @endphp

                            <div
                                wire:key="fixture-{{ $fixture->id }}"
                                class="flex items-center gap-3 px-4 py-3 {{ $locked ? 'opacity-50' : '' }}"
                            >
                                <span class="min-w-0 flex-1 truncate text-right text-sm font-medium">{{ $homeName }}</span>

                                <div class="flex shrink-0 items-center gap-1.5">
                                    <flux:input
                                        type="number"
                                        min="0"
                                        max="20"
                                        wire:model="scores.{{ $fixture->id }}.home"
                                        :disabled="$locked"
                                        class="w-14 text-center"
                                        placeholder="–"
                                    />
                                    <span class="text-zinc-400">:</span>
                                    <flux:input
                                        type="number"
                                        min="0"
                                        max="20"
                                        wire:model="scores.{{ $fixture->id }}.away"
                                        :disabled="$locked"
                                        class="w-14 text-center"
                                        placeholder="–"
                                    />
                                </div>

                                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $awayName }}</span>

                                @if ($fixture->scheduled_at !== null)
                                    <span class="hidden shrink-0 text-xs text-zinc-400 sm:inline">
                                        {{ $fixture->scheduled_at->format('d M H:i') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        @empty
            <flux:text class="text-zinc-500">{{ __('No fixtures available yet.') }}</flux:text>
        @endforelse

        @if ($fixturesByStage->isNotEmpty())
            <div>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Save Predictions') }}</span>
                    <span wire:loading>{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        @endif
    </form>
</div>
