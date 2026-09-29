@props(['recentGalleries'])

@if ($recentGalleries->isNotEmpty())
    <div class="mt-6 flex max-w-2xl flex-wrap items-center justify-center gap-2" aria-label="Galerias recentes">
        <flux:text>Recentes:</flux:text>
        @foreach ($recentGalleries as $gallery)
            <flux:button
                href="{{ route('gallery.show', $gallery->slug) }}"
                variant="filled"
                size="sm"
                class="rounded-full!"
            >
                {{ $gallery->name }}
            </flux:button>
        @endforeach
    </div>
@endif
