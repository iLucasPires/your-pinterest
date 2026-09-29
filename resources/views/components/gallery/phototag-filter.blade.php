@props([
    'tags' => [],
    'selectedTagId' => 0,
])

<div class="flex gap-2 overflow-x-auto px-4 py-3 sm:px-8 sm:py-4" role="group" aria-label="Filtrar por tag">
    <flux:button
        type="button"
        size="sm"
        :variant="$selectedTagId === 0 ? 'primary' : 'ghost'"
        wire:click="selectTag(0)"
        wire:key="gallery-tag-all"
        class="rounded-full! shrink-0"
    >
        Todas
    </flux:button>

    @foreach ($tags as $tag)
        <flux:button
            type="button"
            size="sm"
            :variant="$selectedTagId === $tag->id ? 'primary' : 'filled'"
            wire:click="selectTag({{ $tag->id }})"
            wire:key="gallery-tag-{{ $tag->id }}"
            class="rounded-full! shrink-0"
        >
            {{ $tag->name }}
        </flux:button>
    @endforeach
</div>
