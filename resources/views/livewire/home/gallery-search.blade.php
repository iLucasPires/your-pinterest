<x-backend-search model="search" placeholder="Busque pelo nome da galeria, evento ou cliente..."
    aria-label="Buscar galeria"
>


    @forelse ($this->galleries as $gallery)
        <button
            type="button"
            wire:key="gallery-search-{{ $gallery->id }}"
            wire:click="chooseGallery({{ $gallery->id }})"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition hover:bg-stone-100 dark:hover:bg-zinc-800"
        >
            <span
                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-stone-100 text-stone-600 dark:bg-zinc-800 dark:text-zinc-300"
            >
                <flux:icon name="photo" variant="micro" />
            </span>
            <span class="min-w-0 flex-1">
                <span
                    class="block truncate text-sm font-medium text-stone-900 dark:text-zinc-100">{{ $gallery->name }}</span>
                <span
                    class="mt-0.5 block truncate text-xs text-stone-500">{{ $gallery->client?->name ?? 'Galeria de fotos' }}</span>
            </span>
            <flux:icon name="arrow-up-right" variant="micro" class="size-4 text-stone-400" />
        </button>
    @empty
        <div class="px-3 py-5 text-center">
            <flux:icon name="magnifying-glass" class="mx-auto size-5 text-stone-400" />
            <flux:text size="sm" class="mt-2 text-stone-500">Nenhuma galeria encontrada.</flux:text>
        </div>
    @endforelse
</x-backend-search>
