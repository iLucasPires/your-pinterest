@props(['photo', 'index'])

<div
    {{ $attributes }}
    class="group relative mb-4 block cursor-zoom-in break-inside-avoid overflow-hidden rounded-xl shadow-sm transition hover:shadow-lg"
    aria-label="Abrir {{ $photo->filename }}"
    data-index="{{ $index }}"
    role="button"
    tabindex="0"
    x-on:click="openLightbox({{ $index }})"
    x-on:keydown.enter.prevent="openLightbox({{ $index }})"
    x-on:keydown.space.prevent="openLightbox({{ $index }})"
>
    <img
        src="{{ $photo->thumbnail_url }}"
        alt="{{ $photo->filename }}"
        loading="lazy"
        decoding="async"
        @if ($photo->width && $photo->height) width="{{ $photo->width }}"
            height="{{ $photo->height }}"
            style="aspect-ratio: {{ $photo->width }} / {{ $photo->height }};" @endif
        class="block h-auto w-full transition duration-500 ease-out group-hover:scale-[1.015]"
        onerror="this.onerror = null; this.src = '{{ asset('images/photo-pending.svg') }}';"
    >

    {{-- Hover overlay --}}
    <div
        class="pointer-events-none absolute inset-0 flex flex-col justify-between bg-gradient-to-b from-black/30 via-transparent to-black/40 p-3 opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-visible:opacity-100">
        <div class="flex items-start justify-between gap-2">
            <flux:button
                href="{{ $photo->download_url }}"
                variant="primary"
                class="pointer-events-auto"
                icon="arrow-down-tray"
                aria-label="Baixar foto"
                x-on:click.stop
            />
        </div>
    </div>
</div>
