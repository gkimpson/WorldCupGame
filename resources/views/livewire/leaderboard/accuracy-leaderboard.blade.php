<div class="space-y-6">
    <flux:heading size="xl">Accuracy Leaderboard</flux:heading>

    <div class="flex flex-wrap gap-2">
        <flux:badge as="a" href="{{ route('leaderboard.global') }}">Global</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.accuracy') }}" color="amber">Accuracy</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.perfect') }}">Perfect 104</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.movers') }}">Biggest Movers</flux:badge>
    </div>

    @if ($availableWeeks->isNotEmpty())
        <div class="flex items-center gap-3">
            <flux:button wire:click="showAllTime" size="sm" :variant="$week === null ? 'filled' : 'ghost'">All time</flux:button>

            <div class="flex items-center gap-1">
                <flux:button wire:click="previousWeek" icon="chevron-left" size="sm" variant="ghost" :disabled="$week === null || $isFirstWeek" />
                <span class="min-w-20 text-center text-sm font-medium">
                    {{ $week !== null ? 'Week ' . $week : '' }}
                </span>
                <flux:button wire:click="nextWeek" icon="chevron-right" size="sm" variant="ghost" :disabled="$week === null || $isLastWeek" />
            </div>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column class="w-16">#</flux:table.column>
            <flux:table.column>Player</flux:table.column>
            <flux:table.column>Correct Outcomes</flux:table.column>
            <flux:table.column>Predictions Made</flux:table.column>
            <flux:table.column>Accuracy %</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($topEntries as $entry)
                <flux:table.row :class="$entry['is_current_user'] ? 'bg-amber-50 font-medium dark:bg-amber-950/20' : ''">
                    <flux:table.cell>{{ $entry['rank'] }}</flux:table.cell>
                    <flux:table.cell>{{ $entry['name'] }}</flux:table.cell>
                    <flux:table.cell>{{ $entry['correct_outcomes'] }}</flux:table.cell>
                    <flux:table.cell>{{ $entry['predictions_made'] }}</flux:table.cell>
                    <flux:table.cell>{{ $entry['accuracy_pct'] }}%</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="text-center text-zinc-500">No predictions have been scored yet.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($pinnedEntry !== null)
        <div class="border-t-2 border-dashed border-zinc-300 pt-2 dark:border-zinc-600">
            <flux:table>
                <flux:table.rows>
                    <flux:table.row class="bg-amber-50 font-medium dark:bg-amber-950/20">
                        <flux:table.cell class="w-16">{{ $pinnedEntry['rank'] }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $pinnedEntry['name'] }}
                            <flux:badge class="ml-2" size="sm" color="amber">You</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $pinnedEntry['correct_outcomes'] }}</flux:table.cell>
                        <flux:table.cell>{{ $pinnedEntry['predictions_made'] }}</flux:table.cell>
                        <flux:table.cell>{{ $pinnedEntry['accuracy_pct'] }}%</flux:table.cell>
                    </flux:table.row>
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
