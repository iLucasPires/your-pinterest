@props([
    'placement' => 'bottom',
    'offset' => 12,
    'openExpression' => 'open',
])

@php
    $placementClasses = match ($placement) {
        'bottom' => 'top-[calc(100%+var(--popover-offset))] left-0',
        'bottom-end' => 'top-[calc(100%+var(--popover-offset))] right-0',
        'top' => 'bottom-[calc(100%+var(--popover-offset))] left-0',
        'top-end' => 'bottom-[calc(100%+var(--popover-offset))] right-0',
        default => 'top-[calc(100%+var(--popover-offset))] left-0',
    };
@endphp

<div
    x-data="{ open: false, hasQuery: false }"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    class="relative w-full"
>
    <div x-on:click="open = true">
        {{ $trigger }}
    </div>

    <div
        x-show="{{ $openExpression }}"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="--popover-offset: {{ $offset }}px"
        class="{{ $placementClasses }} absolute z-20 w-full overflow-hidden rounded-2xl border p-2 shadow-xl"
    >
        {{ $slot }}
    </div>
</div>
