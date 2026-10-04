<div
    aria-modal="true"
    aria-label="Visualizador de fotos"
    class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-stone-950/95 p-4 backdrop-blur-md sm:p-6"
    role="dialog"
    tabindex="-1"
    x-show="lightboxOpen"
    x-cloak
    x-ref="lightbox"
    x-on:click.self="closeLightbox()"
    x-on:touchstart="handleTouchStart($event)"
    x-on:touchend="handleTouchEnd($event)"
    x-on:keydown="handleKeydown($event)"
>
    <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
        <flux:button
            aria-label="Fechar"
            type="button"
            square
            class="rounded-full!"
            icon="x-mark"
            icon:class="size-6"
            title="Fechar"
            x-on:click="closeLightbox()"
        />
    </div>

    <div class="absolute left-3 top-1/2 z-10 hidden -translate-y-1/2 sm:left-6 sm:block">
        <flux:button
            aria-label="Foto anterior"
            type="button"
            square
            class="rounded-full!"
            icon="chevron-left"
            icon:class="size-6"
            title="Foto anterior"
            x-on:click="navigateLightbox(-1)"
        />
    </div>

    <img
        class="max-h-[82vh] max-w-[calc(100vw-6rem)] rounded-xl object-contain sm:max-w-[min(92vw,1400px)]"
        x-bind:src="lightboxSource()"
        x-bind:alt="currentPhoto()?.filename || ''"
        onerror="this.onerror = null; this.src = '{{ asset('images/photo-pending.svg') }}';"
    >

    <div class="absolute right-3 top-1/2 z-10 hidden -translate-y-1/2 sm:right-6 sm:block">
        <flux:button
            aria-label="Próxima foto"
            type="button"
            square
            class="rounded-full!"
            icon="chevron-right"
            icon:class="size-6"
            title="Próxima foto"
            x-on:click="navigateLightbox(1)"
        />
    </div>

    <div class="absolute bottom-4 left-1/2 z-10 -translate-x-1/2">
        <flux:card class="flex items-center gap-4 p-2">
            <div class="flex items-center gap-1">
                <flux:heading>foto: </flux:heading>
                <flux:text x-text="allPhotos.length ? `${current + 1} / ${allPhotos.length}` : ''" />
            </div>

            <flux:button
                href="#"
                variant="primary"
                size="sm"
                icon="arrow-down-tray"
                class="text-white!"
                target="_blank"
                rel="noopener"
                x-bind:href="currentPhoto()?.download_url || null"
                x-bind:aria-disabled="!currentPhoto()?.download_url"
                x-on:click.stop="if (!currentPhoto()?.download_url) $event.preventDefault()"
            >
                Baixar
            </flux:button>
        </flux:card>
    </div>
</div>
