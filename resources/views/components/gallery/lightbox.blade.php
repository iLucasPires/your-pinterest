<div
    aria-modal="true"
    aria-label="Visualizador de fotos"
    class="fixed inset-0 z-50 flex flex-col bg-zinc-200/95 backdrop-blur-md lg:flex-row"
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
    {{-- Foto + navegação --}}
    <div class="relative flex min-h-0 min-w-0 flex-1 items-center justify-center p-4 sm:px-20 sm:py-6"
        x-on:click.self="closeLightbox()"
    >
        <div class="absolute left-4 top-1/2 z-10 hidden -translate-y-1/2 sm:block">
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
            class="max-h-full max-w-full rounded-xl object-contain shadow-2xl ring-1 ring-white/10"
            x-bind:src="lightboxSource()"
            x-bind:alt="currentPhoto()?.filename || ''"
            onerror="this.onerror = null; this.src = '{{ asset('images/photo-pending.svg') }}';"
        >

        <div class="absolute right-4 top-1/2 z-10 hidden -translate-y-1/2 sm:block">
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
    </div>

    {{-- Painel lateral --}}
    <flux:card class="m-5 flex flex-col overflow-y-auto p-4 lg:max-h-none lg:w-80">
        <flux:card.header>
            <div class="flex gap-2">
                <flux:heading>Foto</flux:heading>
                <flux:text x-text="allPhotos.length ? `${current + 1} / ${allPhotos.length}` : ''"></flux:text>
            </div>

            <flux:card.actions>
                <flux:button
                    aria-label="Fechar"
                    type="button"
                    square
                    size="sm"
                    class="rounded-full!"
                    icon="x-mark"
                    icon:class="size-5"
                    title="Fechar"
                    x-on:click="closeLightbox()"
                />

            </flux:card.actions>
        </flux:card.header>

        <flux:card.body class="flex-1">
            <div x-show="currentExifEntries().length" x-cloak>
                <flux:heading size="sm" class="mb-3">
                    Informações da foto
                </flux:heading>

                <table class="w-full text-sm">
                    <tbody class="divide-y divide-zinc-200">
                        <template x-for="entry in currentExifEntries()" :key="entry.label">
                            <tr>
                                <th
                                    scope="row"
                                    class="py-2.5 pr-4 text-left font-normal text-zinc-500"
                                    x-text="entry.label"
                                ></th>

                                <td
                                    class="py-2.5 text-right font-medium"
                                    x-text="entry.value"
                                    :title="entry.value"
                                ></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </flux:card.body>

        <flux:card.footer class="">
            <flux:button
                href="#"
                variant="primary"
                icon="arrow-down-tray"
                class="w-full lg:mt-auto"
                target="_blank"
                rel="noopener"
                x-bind:href="currentPhoto()?.download_url || null"
                x-bind:aria-disabled="!currentPhoto()?.download_url"
                x-on:click.stop="if (!currentPhoto()?.download_url) $event.preventDefault()"
            >
                Baixar
            </flux:button>
        </flux:card.footer>
    </flux:card>
</div>
