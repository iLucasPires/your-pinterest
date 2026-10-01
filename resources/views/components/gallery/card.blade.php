@props(['photo', 'index'])

<flux:card
    class="group relative mb-4 cursor-zoom-in break-inside-avoid overflow-hidden"
    role="button"
    tabindex="0"
    aria-label="Abrir {{ $photo->filename }}"
    x-on:click="openLightbox({{ $index }})"
    x-on:keydown.enter.prevent="openLightbox({{ $index }})"
    x-on:keydown.space.prevent="openLightbox({{ $index }})"
>
    <img
        src="{{ $photo->thumbnail_url }}"
        alt="{{ $photo->filename }}"
        loading="lazy"
        class="group-hover:contrast-105 block h-auto w-full transition duration-300 group-hover:scale-[1.015] group-hover:brightness-95"
        onerror="this.onerror = null; this.src = '{{ asset('images/photo-pending.svg') }}'"
    >

    <div class="absolute left-4 top-4">
        <flux:button
            href="{w{ $photo->download_url }}"
            class="opacity-0 transition group-hover:opacity-100"
            icon="arrow-down-tray"
            variant="primary"
            aria-label="Baixar foto"
            x-on:click.stop
        />
    </div>
</flux:card>
