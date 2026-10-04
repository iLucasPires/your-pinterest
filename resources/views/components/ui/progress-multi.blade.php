@props([
    'segments' => [],
    'max' => 100,
])

@php
    $total = collect($segments)->sum('value');
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        @foreach ($segments as $segment)
            @php
                $value = min(max((float) ($segment['value'] ?? 0), 0), $max);
                $width = $max > 0 ? ($value / $max) * 100 : 0;
            @endphp

            <div
                class="{{ $segment['color'] ?? 'bg-zinc-500' }} transition-all duration-300"
                style="width: {{ $width }}%"
                title="{{ $segment['label'] ?? '' }}: {{ $value }}%"
            ></div>
        @endforeach
    </div>

    @if ($slot->isNotEmpty())
        {{ $slot }}
    @endif
</div>
