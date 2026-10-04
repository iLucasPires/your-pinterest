@props([
    'title' => '',
    'description' => '',
    'icon' => '',
])

<div class="flex flex-col items-center justify-center gap-2 px-4 text-center">
    <div class="flex flex-col items-center justify-center">
        <flux:icon name="{{ $icon }}" />
        <flux:heading size="sm">{{ $title }}</flux:heading>
        <flux:text>{{ $description }}</flux:text>
    </div>

    @isset($actions)
        <div class="flex gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
