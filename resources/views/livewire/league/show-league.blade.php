<div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <flux:heading size="xl">{{ $league->name }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge>{{ __('Private') }}</flux:badge>
                <flux:badge>{{ __('Code: :code', ['code' => $league->join_code]) }}</flux:badge>
            </div>
        </div>

        <flux:button :href="route('leagues.index')" icon="arrow-left" variant="subtle" wire:navigate>
            {{ __('Leagues') }}
        </flux:button>
    </div>

    <flux:card>
        <div class="mb-4 flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('League Leaderboard') }}</flux:heading>
            <flux:badge>{{ trans_choice(':count member|:count members', count($entries), ['count' => count($entries)]) }}</flux:badge>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column class="w-16">#</flux:table.column>
                <flux:table.column>{{ __('Player') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Points') }}</flux:table.column>
                <flux:table.column>{{ __('Predictions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($entries as $entry)
                    <flux:table.row wire:key="league-member-{{ $entry['rank'] }}" :class="$entry['is_current_user'] ? 'bg-amber-50 font-medium dark:bg-amber-950/20' : ''">
                        <flux:table.cell>{{ $entry['rank'] }}</flux:table.cell>
                        <flux:table.cell>{{ $entry['name'] }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $entry['role'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $entry['total_points'] }}</flux:table.cell>
                        <flux:table.cell>{{ $entry['predictions_made'] }} / {{ $totalMatches }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
