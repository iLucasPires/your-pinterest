@props(['photo', 'index'])

<div
    class="group relative cursor-zoom-in self-start overflow-hidden rounded-md p-0"
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

    <div class="absolute right-4 top-4">
        <flux:button
            href="{w{ $photo->download_url }}"
            class="rounded-full! opacity-0 transition group-hover:opacity-100"
            icon="arrow-down-tray"
            variant="filled"
            aria-label="Baixar foto"
            x-on:click.stop
        />
    </div>
</div>
