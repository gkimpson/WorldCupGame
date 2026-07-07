<div class="flex w-full max-w-5xl flex-col gap-6">
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
                                $locked = $fixture->isLocked();
                                $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? 'TBD';
                                $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? 'TBD';
                                $isKnockout = $fixture->stage->isKnockout();
                            @endphp

                            <div wire:key="fixture-{{ $fixture->id }}" class="flex flex-col gap-0 {{ $locked ? 'opacity-50' : '' }}">
                                <div class="flex flex-col gap-2 px-4 py-3 sm:grid sm:grid-cols-[4rem_minmax(0,1fr)_1.25rem_auto_1.25rem_minmax(0,1fr)_5rem] sm:items-center sm:gap-3">
                                {{-- Date (desktop only) --}}
                                <div class="hidden sm:flex sm:flex-col sm:items-start">
                                    @if ($fixture->scheduled_at !== null)
                                        <flux:link :href="route('fixtures.show', $fixture)" wire:navigate class="text-xs leading-tight text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                            <x-fixture-kickoff :fixture="$fixture" split />
                                        </flux:link>
                                    @endif
                                </div>

                                {{-- Mobile: teams row --}}
                                <div class="flex items-center justify-between gap-2 sm:hidden">
                                    <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                                        <span class="min-w-0 text-right text-sm font-medium">{{ $homeName }}</span>
                                        <x-team-flag :team="$fixture->homeTeam" />
                                    </div>
                                    <span class="shrink-0 text-xs text-zinc-400">vs</span>
                                    <div class="flex min-w-0 flex-1 items-center gap-1.5">
                                        <x-team-flag :team="$fixture->awayTeam" />
                                        <span class="min-w-0 text-sm font-medium">{{ $awayName }}</span>
                                    </div>
                                </div>

                                {{-- Mobile: scores row --}}
                                <div class="flex items-center justify-center gap-1.5 sm:hidden">
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

                                {{-- Desktop: home name, home flag, scores, away flag, away name --}}
                                <span class="hidden min-w-0 truncate text-right text-sm font-medium sm:block">{{ $homeName }}</span>
                                <div class="hidden sm:flex sm:items-center sm:justify-end">
                                    <x-team-flag :team="$fixture->homeTeam" />
                                </div>

                                <div class="hidden shrink-0 items-center gap-1.5 sm:flex">
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

                                <div class="hidden sm:flex sm:items-center">
                                    <x-team-flag :team="$fixture->awayTeam" />
                                </div>
                                <span class="hidden min-w-0 truncate text-sm font-medium sm:block">{{ $awayName }}</span>

                                {{-- Action --}}
                                <div class="flex shrink-0 items-center justify-center gap-2 sm:justify-self-end">
                                    @if ($locked)
                                        <flux:icon.lock-closed class="size-4 text-zinc-400" />
                                    @else
                                        @if (!empty($savedFixtures[$fixture->id]))
                                            <flux:badge size="sm" color="green">Saved</flux:badge>
                                        @else
                                            <flux:button
                                                size="sm"
                                                variant="primary"
                                                wire:click="saveFixture({{ $fixture->id }})"
                                                wire:loading.attr="disabled"
                                                wire:target="saveFixture({{ $fixture->id }})"
                                            >Confirm</flux:button>
                                        @endif
                                    @endif
                                </div>
                                </div>

                                {{-- Knockout outcome selector --}}
                                @if ($isKnockout && !$locked)
                                    <div x-data="{
                                        home: $wire.entangle('scores.{{ $fixture->id }}.home'),
                                        away: $wire.entangle('scores.{{ $fixture->id }}.away'),
                                        outcome: $wire.entangle('scores.{{ $fixture->id }}.knockout_outcome')
                                    }" class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                        {{-- Non-draw: show inferred outcome label --}}
                                        <template x-if="home !== '' && away !== '' && home !== away">
                                            <div class="text-center text-sm text-zinc-500">
                                                <span x-text="Number(home) > Number(away) ? '{{ $homeName }} win in normal time' : '{{ $awayName }} win in normal time'"></span>
                                            </div>
                                        </template>

                                        {{-- Draw: show resolution selector --}}
                                        <template x-if="home !== '' && away !== '' && home === away">
                                            <div class="flex flex-col items-center gap-3">
                                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Match tied at 90 mins. Who wins?</div>
                                                <div class="flex flex-wrap justify-center gap-3 sm:gap-2">
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" x-model="outcome" value="home_win_aet" name="knockout_outcome_{{ $fixture->id }}" class="w-4 h-4" />
                                                        <span class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300">{{ $homeName }} (AET)</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" x-model="outcome" value="away_win_aet" name="knockout_outcome_{{ $fixture->id }}" class="w-4 h-4" />
                                                        <span class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300">{{ $awayName }} (AET)</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" x-model="outcome" value="home_win_pens" name="knockout_outcome_{{ $fixture->id }}" class="w-4 h-4" />
                                                        <span class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300">{{ $homeName }} (Pens)</span>
                                                    </label>
                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                        <input type="radio" x-model="outcome" value="away_win_pens" name="knockout_outcome_{{ $fixture->id }}" class="w-4 h-4" />
                                                        <span class="text-xs sm:text-sm text-zinc-600 dark:text-zinc-300">{{ $awayName }} (Pens)</span>
                                                    </label>
                                                </div>
                                                @error("scores.{$fixture->id}.knockout_outcome")
                                                    <flux:error class="text-xs">{{ $message }}</flux:error>
                                                @enderror
                                            </div>
                                        </template>
                                    </div>
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
