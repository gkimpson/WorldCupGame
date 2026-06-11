@props([
    'team' => null,
    'code' => null,
    'label' => null,
    'iconClass' => 'h-full w-full rounded-[2px]',
])

@php
    $flagCode = $code ?? $team?->flag_code;
    $flagLabel = $label ?? $team?->name;
@endphp

<span
    {{ $attributes->class('inline-flex h-4 w-5 shrink-0 items-center justify-center overflow-hidden') }}
    @if ($flagLabel) title="{{ $flagLabel }}" @endif
>
    @if ($flagCode)
        @svg("flag-country-{$flagCode}", $iconClass)

        @if ($flagLabel)
            <span class="sr-only">{{ $flagLabel }}</span>
        @endif
    @endif
</span>
