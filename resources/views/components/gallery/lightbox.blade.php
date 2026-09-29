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
    <div class="hidden absolute right-4 top-4 z-10 sm:right-6 sm:top-6 sm:block">
        <flux:button
            aria-label="Fechar"
            type="button"
            variant="primary"
            square
            class="rounded-full!"
            icon="x-mark"
            icon:class="size-6"
            title="Fechar"
            x-on:click="closeLightbox()"
        />
    </div>

    <div class="hidden absolute left-3 top-1/2 z-10 -translate-y-1/2 sm:left-6 sm:block">
        <flux:button
            aria-label="Foto anterior"
            type="button"
            square
            variant="primary"
            class="rounded-full!"
            icon="chevron-left"
            icon:class="size-6"
            title="Foto anterior"
            x-on:click="navigateLightbox(-1)"
        />
    </div>

    <img
        class="max-h-[82vh] max-w-[calc(100vw-6rem)] rounded-xl object-contain  sm:max-w-[min(92vw,1400px)]"
        x-bind:src="lightboxSource()"
        x-bind:alt="currentPhoto()?.filename || ''"
        x-on:error="handleImageError($event)"
    >

    <div class="hidden absolute right-3 top-1/2 z-10 -translate-y-1/2 sm:right-6 sm:block">
        <flux:button
            aria-label="Próxima foto"
            type="button"
            variant="primary"
            square
            class="rounded-full!"
            icon="chevron-right"
            icon:class="size-6"
            title="Próxima foto"
            x-on:click="navigateLightbox(1)"
        />
    </div>

    <div
        class="absolute bottom-4 left-1/2 z-10 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 flex-col items-center gap-2 rounded-xl border border-white/15 bg-zinc-900/80 px-3.5 py-2.5 text-center text-white shadow-2xl backdrop-blur-md sm:bottom-6">
        <div class="flex items-center gap-2.5 text-xs font-medium">
            <span id="lb-counter" class="text-white/70"
                x-text="allPhotos.length ? `${current + 1} / ${allPhotos.length}` : ''"
            ></span>
            <span class="text-white/30">|</span>
            <flux:button
                id="lb-download"
                as="a"
                href="#"
                variant="ghost"
                size="xs"
                icon="arrow-down-tray"
                icon:class="size-4"
                class="h-auto! p-0! text-white! hover:bg-transparent! hover:text-white/75! gap-1.5 font-semibold transition"
                title="Baixar foto"
                download
                x-bind:href="currentPhoto()?.download_url || '#'"
                x-on:click.stop
            >
                Baixar
            </flux:button>
        </div>
    </div>
</div>
