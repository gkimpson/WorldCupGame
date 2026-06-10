@props(['fixture'])

@php
    $scheduledAtIso8601 = $fixture->scheduledAtIso8601();
@endphp

@if($scheduledAtIso8601)
    <time
        datetime="{{ $scheduledAtIso8601 }}"
        x-data="{ scheduledAt: '' }"
        x-init="scheduledAt = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date($el.dateTime))"
        x-text="scheduledAt"
    >{{ $fixture->scheduledAtForTimezone('UTC')->format('M j, Y, H:i') }} UTC</time>
@else
    <span>{{ $slot->isEmpty() ? __('TBD') : $slot }}</span>
@endif
