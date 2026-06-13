<div class="flex flex-col gap-6">

    {{-- Match Outcome Grid --}}
    <flux:card class="p-5">
        <flux:heading size="sm" class="mb-4">Match Results</flux:heading>

        <div class="grid grid-cols-[repeat(13,minmax(0,1fr))] gap-1">
            @foreach ($outcomeGrid as $row)
                @php
                    $result = $row['result'];
                    $fixture = $row['fixture'];
                    $prediction = $row['prediction'];

                    $bg = match ($result) {
                        'exact'   => 'bg-green-500',
                        'correct' => 'bg-amber-400',
                        'wrong'   => 'bg-red-400',
                        default   => 'bg-zinc-200 dark:bg-zinc-700',
                    };

                    $homeName = $fixture->homeTeam?->name ?? $fixture->home_team_placeholder ?? '?';
                    $awayName = $fixture->awayTeam?->name ?? $fixture->away_team_placeholder ?? '?';
                    $score = $fixture->home_score !== null ? "{$fixture->home_score}–{$fixture->away_score}" : 'TBD';
                    $predicted = $prediction ? "{$prediction->home_score}–{$prediction->away_score}" : 'None';
                    $pts = $prediction ? "+{$prediction->points}pts" : '—';
                    $tooltipText = "{$homeName} {$score} {$awayName} · Predicted: {$predicted} · {$pts}";
                @endphp

                <div
                    x-data="{ show: false }"
                    @mouseenter="show = true"
                    @mouseleave="show = false"
                    class="relative"
                >
                    <div class="{{ $bg }} {{ $compact ? 'h-5 w-5' : 'h-7 w-7' }} rounded-sm"></div>
                    <div
                        x-show="show"
                        x-transition.opacity
                        class="absolute bottom-full left-1/2 z-10 mb-1 -translate-x-1/2 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-xs text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900"
                    >
                        {{ $tooltipText }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="mt-3 flex flex-wrap gap-3 text-xs text-zinc-500">
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-green-500"></span> Exact
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-amber-400"></span> Correct
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-red-400"></span> Wrong
            </span>
            <span class="flex items-center gap-1">
                <span class="inline-block h-3 w-3 rounded-sm bg-zinc-200 dark:bg-zinc-700"></span> Pending
            </span>
        </div>
    </flux:card>

    {{-- Score Distribution Grid (full mode only) --}}
    @if ($scoreGrid !== null)
        <flux:card class="p-5">
            <flux:heading size="sm" class="mb-4">Score Distribution</flux:heading>

            <div class="overflow-x-auto">
                <table class="text-xs">
                    <thead>
                        <tr>
                            <th class="pr-2 text-right text-zinc-400">Home ↓ / Away →</th>
                            @for ($a = 0; $a <= $scoreGrid['maxAway']; $a++)
                                <th class="w-10 text-center font-semibold text-zinc-500">{{ $a }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @for ($h = 0; $h <= $scoreGrid['maxHome']; $h++)
                            <tr>
                                <td class="pr-2 text-right font-semibold text-zinc-500">{{ $h }}</td>
                                @for ($a = 0; $a <= $scoreGrid['maxAway']; $a++)
                                    @php
                                        $cell = $scoreGrid['cells'][$h][$a];
                                        $count = $cell['count'];
                                        $correct = $cell['correct'];
                                        $pct = $count > 0 ? round($correct / $count * 100) : 0;

                                        $cellBg = match (true) {
                                            $count === 0 => '',
                                            $pct >= 67   => 'bg-green-600 text-white',
                                            $pct >= 34   => 'bg-green-300 text-zinc-800',
                                            $pct >= 1    => 'bg-zinc-300 text-zinc-800 dark:bg-zinc-600 dark:text-zinc-100',
                                            default      => 'bg-red-200 text-zinc-800',
                                        };

                                        $tooltipText = $count > 0
                                            ? "Predicted {$count}× · {$correct} correct ({$pct}%)"
                                            : '';
                                    @endphp
                                    <td class="w-10 p-0.5">
                                        @if ($count > 0)
                                            <div
                                                x-data="{ show: false }"
                                                @mouseenter="show = true"
                                                @mouseleave="show = false"
                                                class="relative"
                                            >
                                                <div class="{{ $cellBg }} flex h-9 w-9 items-center justify-center rounded font-medium">
                                                    {{ $count }}
                                                </div>
                                                <div
                                                    x-show="show"
                                                    x-transition.opacity
                                                    class="absolute bottom-full left-1/2 z-10 mb-1 -translate-x-1/2 whitespace-nowrap rounded bg-zinc-900 px-2 py-1 text-xs text-white shadow-lg dark:bg-zinc-100 dark:text-zinc-900"
                                                >
                                                    {{ $tooltipText }}
                                                </div>
                                            </div>
                                        @else
                                            <div class="h-9 w-9 rounded"></div>
                                        @endif
                                    </td>
                                @endfor
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </flux:card>
    @elseif (! $compact)
        <flux:card class="p-5">
            <flux:heading size="sm" class="mb-4">Score Distribution</flux:heading>
            <flux:text class="text-zinc-500">No scored predictions yet.</flux:text>
        </flux:card>
    @endif

</div>
