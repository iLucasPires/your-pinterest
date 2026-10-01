@props(['gallery', 'photoCount', 'canDownload' => false])

<header class="flex flex-col">
    {{-- Top bar --}}
    <div
        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-3 border-b px-4 py-3 sm:px-6 md:gap-4 md:px-8 lg:px-64 lg:py-4">
        {{-- Brand --}}
        <flux:brand
            href="#"
            name="Sua Galeria"
        >
            <x-slot
                name="logo"
                class="bg-accent text-accent-foreground"
            >
                <flux:icon
                    name="camera"
                    variant="micro"
                />
            </x-slot>
        </flux:brand>



        {{-- Profile --}}
        <x-gallery.profile :gallery="$gallery" />
    </div>

    <div
        class="flex flex-col items-start gap-3 border-b px-4 py-4 sm:px-6 md:flex-row md:items-center md:justify-between md:px-8 lg:px-64">
        @if ($this->clientGalleries->count() > 1)
            <x-gallery.select
                :gallery-id="$this->gallery->id"
                :client-galleries="$this->clientGalleries"
            />
        @endif

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
