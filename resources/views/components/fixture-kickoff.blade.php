@props([
    'fixture',
    'weekday' => false,
    'year' => true,
    'split' => false,
])

@php
    $scheduledAtIso8601 = $fixture->scheduledAtIso8601();

    $dateTimeOptions = array_filter([
        'weekday' => $weekday ? 'short' : null,
        'day' => '2-digit',
        'month' => 'short',
        'year' => $year ? 'numeric' : null,
        'hour' => '2-digit',
        'minute' => '2-digit',
        'hour12' => false,
    ], fn ($value) => $value !== null);

    $fallbackFormat = ($weekday ? 'D ' : '').'d M'.($year ? ' Y' : '').', H:i';
@endphp

@if($scheduledAtIso8601)
    @if($split)
        <span
            {{ $attributes }}
            x-data="{ date: '', time: '' }"
            x-init="
                date = new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short' }).format(new Date('{{ $scheduledAtIso8601 }}'));
                time = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date('{{ $scheduledAtIso8601 }}'));
            "
        ><span x-text="date">{{ $fixture->scheduledAtForTimezone('UTC')->format('d M') }}</span><br><span x-text="time">{{ $fixture->scheduledAtForTimezone('UTC')->format('H:i') }}</span></span>
    @else
        <time
            {{ $attributes }}
            datetime="{{ $scheduledAtIso8601 }}"
            x-data="{ scheduledAt: '' }"
            x-init="scheduledAt = new Intl.DateTimeFormat(undefined, @js($dateTimeOptions)).format(new Date($el.dateTime))"
            x-text="scheduledAt"
        >{{ $fixture->scheduledAtForTimezone('UTC')->format($fallbackFormat) }} UTC</time>
    @endif
@else
    <span {{ $attributes }}>{{ $slot->isEmpty() ? __('TBD') : $slot }}</span>
@endif
