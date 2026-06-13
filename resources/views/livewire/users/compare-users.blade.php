<div class="space-y-6">

    {{-- Page heading --}}
    <flux:heading size="xl">Compare Players</flux:heading>

    {{-- Search inputs --}}
    <div class="flex items-center gap-4">

        {{-- Search A --}}
        <div class="relative flex-1">
            @if ($userA)
                <div class="flex items-center justify-between rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 dark:border-amber-700 dark:bg-amber-950/20">
                    <span class="font-medium text-amber-800 dark:text-amber-200">{{ $userA->name }}</span>
                    <flux:button wire:click="$set('userA', null)" size="sm" variant="ghost" icon="x-mark" />
                </div>
            @else
                <flux:input
                    wire:model.live.debounce.300ms="searchA"
                    placeholder="Search player A…"
                    icon="magnifying-glass"
                />
                @if ($searchResultsA->isNotEmpty())
                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($searchResultsA as $result)
                            <button
                                wire:click="selectUserA({{ $result->id }})"
                                class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            >
                                {{ $result->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <flux:text class="font-bold text-zinc-500">vs</flux:text>

        {{-- Search B --}}
        <div class="relative flex-1">
            @if ($userB)
                <div class="flex items-center justify-between rounded-lg border border-blue-300 bg-blue-50 px-4 py-2 dark:border-blue-700 dark:bg-blue-950/20">
                    <span class="font-medium text-blue-800 dark:text-blue-200">{{ $userB->name }}</span>
                    <flux:button wire:click="$set('userB', null)" size="sm" variant="ghost" icon="x-mark" />
                </div>
            @else
                <flux:input
                    wire:model.live.debounce.300ms="searchB"
                    placeholder="Search player B…"
                    icon="magnifying-glass"
                />
                @if ($searchResultsB->isNotEmpty())
                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($searchResultsB as $result)
                            <button
                                wire:click="selectUserB({{ $result->id }})"
                                class="w-full px-4 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                            >
                                {{ $result->name }}
                            </button>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

    </div>

    @if (! $userA || ! $userB)

        <flux:text class="text-center text-zinc-500">Search for two players to compare.</flux:text>

    @else

        {{-- Stat comparison table --}}
        <flux:card class="overflow-hidden p-0">
            <div class="grid grid-cols-3">

                {{-- Header row --}}
                <div class="bg-amber-50 p-4 text-center dark:bg-amber-950/20">
                    <flux:heading size="lg" class="text-amber-700 dark:text-amber-300">{{ $userA->name }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">
                        @if ($rankA > 0) Rank #{{ $rankA }} @else No rank yet @endif
                    </flux:text>
                </div>
                <div class="p-4 text-center"></div>
                <div class="bg-blue-50 p-4 text-center dark:bg-blue-950/20">
                    <flux:heading size="lg" class="text-blue-700 dark:text-blue-300">{{ $userB->name }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">
                        @if ($rankB > 0) Rank #{{ $rankB }} @else No rank yet @endif
                    </flux:text>
                </div>

                @php
                    $rows = [
                        ['label' => 'Points', 'a' => $statsA?->total_points ?? '—', 'b' => $statsB?->total_points ?? '—'],
                        ['label' => 'Accuracy', 'a' => $statsA ? $accuracyA.'%' : '—', 'b' => $statsB ? $accuracyB.'%' : '—'],
                        ['label' => 'Exact Scores', 'a' => $statsA?->exact_scores ?? '—', 'b' => $statsB?->exact_scores ?? '—'],
                        ['label' => 'Predictions', 'a' => $statsA?->predictions_made ?? '—', 'b' => $statsB?->predictions_made ?? '—'],
                    ];
                @endphp

                @foreach ($rows as $row)
                    <div class="border-t border-zinc-100 p-4 text-center text-2xl font-bold text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                        {{ $row['a'] }}
                    </div>
                    <div class="border-t border-zinc-100 p-4 text-center text-sm font-medium text-zinc-500 dark:border-zinc-800">
                        {{ $row['label'] }}
                    </div>
                    <div class="border-t border-zinc-100 p-4 text-center text-2xl font-bold text-zinc-900 dark:border-zinc-800 dark:text-zinc-100">
                        {{ $row['b'] }}
                    </div>
                @endforeach

            </div>
        </flux:card>

        {{-- Week by Week --}}
        @if ($allWeeks->isNotEmpty())
            <div>
                <flux:heading size="sm" class="mb-3">Week by Week</flux:heading>
                <div class="grid gap-3" style="grid-template-columns: repeat({{ min($allWeeks->count(), 4) }}, minmax(0, 1fr))">
                    @foreach ($allWeeks as $week)
                        @php
                            $wA = $weeklyA->firstWhere('week_number', $week);
                            $wB = $weeklyB->firstWhere('week_number', $week);
                        @endphp
                        <flux:card class="p-4 text-center">
                            <flux:text class="text-xs font-semibold uppercase text-zinc-400">Wk {{ $week }}</flux:text>
                            <div class="mt-1 text-sm">
                                <span class="font-bold text-amber-600 dark:text-amber-400">
                                    {{ $wA ? $wA->total_points : '—' }}
                                </span>
                                <span class="mx-1 text-zinc-300 dark:text-zinc-600">·</span>
                                <span class="font-bold text-blue-600 dark:text-blue-400">
                                    {{ $wB ? $wB->total_points : '—' }}
                                </span>
                            </div>
                            <flux:text class="mt-0.5 text-xs text-zinc-400">A · B</flux:text>
                        </flux:card>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Match by Match --}}
        @if (count($matches) > 0)
            <div>
                <flux:heading size="sm" class="mb-3">Match by Match</flux:heading>
                <flux:card class="p-0">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">

                        {{-- Column headers --}}
                        <div class="grid grid-cols-3 px-4 py-2 text-xs font-semibold uppercase text-zinc-400">
                            <div class="text-left text-amber-600 dark:text-amber-400">{{ $userA->name }}</div>
                            <div class="text-center">Match</div>
                            <div class="text-right text-blue-600 dark:text-blue-400">{{ $userB->name }}</div>
                        </div>

                        @foreach ($matches as $row)
                            @php
                                $fixture = $row['fixture'];
                                $predA = $row['predA'];
                                $predB = $row['predB'];

                                $badgeA = $predA ? ($predA->points >= 3 ? '★' : ($predA->points >= 1 ? '✓' : '✗')) : null;
                                $badgeB = $predB ? ($predB->points >= 3 ? '★' : ($predB->points >= 1 ? '✓' : '✗')) : null;

                                $colorA = $predA ? ($predA->points >= 3 ? 'text-green-600' : ($predA->points >= 1 ? 'text-amber-500' : 'text-red-500')) : 'text-zinc-400';
                                $colorB = $predB ? ($predB->points >= 3 ? 'text-green-600' : ($predB->points >= 1 ? 'text-amber-500' : 'text-red-500')) : 'text-zinc-400';
                            @endphp
                            <div class="grid grid-cols-3 items-center px-4 py-3 text-sm">

                                {{-- User A prediction --}}
                                <div class="flex items-center gap-1 text-zinc-700 dark:text-zinc-300">
                                    @if ($predA)
                                        <span>{{ $predA->home_score }}–{{ $predA->away_score }}</span>
                                        <span class="{{ $colorA }} font-bold">{{ $badgeA }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </div>

                                {{-- Fixture result --}}
                                <div class="text-center text-xs text-zinc-500">
                                    <div class="font-medium text-zinc-700 dark:text-zinc-200">
                                        {{ $fixture->homeTeam?->name ?? $fixture->home_team_placeholder }}
                                        {{ $fixture->home_score }}–{{ $fixture->away_score }}
                                        {{ $fixture->awayTeam?->name ?? $fixture->away_team_placeholder }}
                                    </div>
                                </div>

                                {{-- User B prediction --}}
                                <div class="flex items-center justify-end gap-1 text-zinc-700 dark:text-zinc-300">
                                    @if ($predB)
                                        <span class="{{ $colorB }} font-bold">{{ $badgeB }}</span>
                                        <span>{{ $predB->home_score }}–{{ $predB->away_score }}</span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </div>

                            </div>
                        @endforeach

                    </div>

                    {{-- Legend --}}
                    <div class="border-t border-zinc-100 px-4 py-2 text-xs text-zinc-400 dark:border-zinc-800">
                        ★ exact score (3 pts) · ✓ correct outcome (1 pt) · ✗ wrong (0 pts)
                    </div>
                </flux:card>
            </div>
        @elseif (count($matches) === 0 && $userA && $userB)
            <flux:text class="text-center text-zinc-400">No completed matches with scored predictions yet.</flux:text>
        @endif

    @endif

</div>
