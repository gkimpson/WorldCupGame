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
                            @endphp

                            <div wire:key="fixture-{{ $fixture->id }}" class="flex flex-col gap-2 px-4 py-3 sm:grid sm:grid-cols-[4rem_minmax(0,1fr)_1.25rem_auto_1.25rem_minmax(0,1fr)_5rem] sm:items-center sm:gap-3 {{ $locked ? 'opacity-50' : '' }}">
                                {{-- Date (desktop only) --}}
                                <div class="hidden sm:flex sm:flex-col sm:items-start">
                                    @if ($fixture->scheduled_at !== null)
                                        <flux:link :href="route('fixtures.show', $fixture)" wire:navigate class="text-xs leading-tight text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                                            {{ $fixture->scheduled_at->format('d M') }}<br>{{ $fixture->scheduled_at->format('H:i') }}
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
                                <x-team-flag :team="$fixture->homeTeam" class="hidden justify-self-end sm:block" />

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

                                <x-team-flag :team="$fixture->awayTeam" class="hidden justify-self-start sm:block" />
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
