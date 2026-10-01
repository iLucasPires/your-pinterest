@props(['gallery', 'photoCount', 'canDownload' => false])

<header class="flex flex-col">
    {{-- Top bar --}}
    <div
        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-3 border-b px-4 py-3 sm:px-6 md:gap-4 md:px-8 lg:px-64 lg:py-4">
        {{-- Brand --}}
        <flux:brand href="#" name="Sua Galeria">
            <x-slot name="logo" class="bg-accent text-accent-foreground">
                <flux:icon name="camera" variant="micro" />
            </x-slot>
        </flux:brand>


        {{-- Search --}}
        <div class="order-3 w-full md:order-none md:flex md:max-w-xl">
            <flux:input
                id="photo-search-input"
                type="search"
                icon="magnifying-glass"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por foto, tag ou nota..."
                aria-label="Buscar fotos"
                input:class="rounded-full!"
            />
        </div>

        {{-- Visitor menu --}}
        <div class="shrink-0">
            <flux:dropdown position="bottom" align="end">
                <flux:profile circle :chevron="false"
                    avatar="https://robohash.org/{{ rawurlencode($gallery->slug) }}.png?size=64x64&set=set4"
                />

                <flux:menu class="min-w-56">
                    <div class="px-2.5 py-2">
                        <flux:text class="font-semibold">
                            {{ $gallery->client?->name ?? 'Visitante' }}
                        </flux:text>

                        <flux:text size="sm" class="text-stone-500">
                            Visualização da galeria
                        </flux:text>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.item
                        as="button"
                        icon="sun"
                        x-data
                        x-on:click="$flux.dark = ! $flux.dark"
                    >
                        <span class="flex-1">Alterar tema</span>

                        <span class="text-xs text-stone-500"
                            x-text="$flux.appearance == 'light' ? 'Modo claro' : 'Modo escuro'"
                        ></span>
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div
        class="flex flex-col items-start gap-3 border-b px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between md:px-8 lg:px-64">
        <div class="min-w-0">
            <flux:heading size="xl">
                {{ $gallery->name }}
            </flux:heading>

            @if ($gallery->description)
                <flux:text>
                    {{ $gallery->description }}
                </flux:text>
            @endif
        </div>

        <div
            class="flex w-full flex-wrap items-center justify-between gap-2 pb-0 md:w-auto md:flex-nowrap md:justify-end md:pb-3">
            <flux:badge
                rounded
                size="lg"
                icon="photo"
                variant="primary"
            >
                {{ $photoCount }} {{ Str::plural('foto', $photoCount) }}
            </flux:badge>

            @if ($canDownload)
                <flux:button
                    :href="route('gallery.download', $gallery->slug)"
                    icon="arrow-down-tray"
                    size="sm"
                    variant="outline"
                    class="rounded-full!"
                >
                    Baixar galeria
                </flux:button>
            @endif

            <flux:button
                type="button"
                icon="share"
                size="sm"
                variant="primary"
                class="rounded-full!"
                x-on:click="
                    navigator.clipboard.writeText(window.location.href);
                    $flux.toast('Link da galeria copiado!');
                "
            >
                <span class="hidden sm:inline">
                    Compartilhar
                </span>
            </flux:button>

            {{ $actions ?? '' }}
        </div>
    </div>
</header>
