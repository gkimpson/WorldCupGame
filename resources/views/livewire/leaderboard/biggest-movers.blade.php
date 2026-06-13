<div class="space-y-6">
    <flux:heading size="xl">Biggest Movers</flux:heading>

    <div class="flex flex-wrap gap-2">
        <flux:badge as="a" href="{{ route('leaderboard.global') }}">Global</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.accuracy') }}">Accuracy</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.perfect') }}">Perfect 104</flux:badge>
        <flux:badge as="a" href="{{ route('leaderboard.movers') }}" color="amber">Biggest Movers</flux:badge>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column class="w-24">Movement</flux:table.column>
            <flux:table.column>Player</flux:table.column>
            <flux:table.column>Previous Rank</flux:table.column>
            <flux:table.column>Current Rank</flux:table.column>
            <flux:table.column>Points This Week</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($movers as $mover)
                @php $change = (int) $mover->rank_change; @endphp
                <flux:table.row>
                    <flux:table.cell>
                        @if ($change > 0)
                            <span class="font-semibold text-green-600 dark:text-green-400">▲ {{ $change }}</span>
                        @elseif ($change < 0)
                            <span class="font-semibold text-red-600 dark:text-red-400">▼ {{ abs($change) }}</span>
                        @else
                            <span class="text-zinc-400">—</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('users.show', $mover->user_id) }}" class="hover:underline">
                            {{ $mover->name }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>{{ $mover->prev_rank }}</flux:table.cell>
                    <flux:table.cell>{{ $mover->current_rank }}</flux:table.cell>
                    <flux:table.cell>{{ $mover->total_points }}</flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">
                        <flux:text class="text-center text-zinc-500">No week-on-week data available yet.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
