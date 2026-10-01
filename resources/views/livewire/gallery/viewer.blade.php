<div
    x-data="window.galleryLightbox(@js($this->lightboxPhotos))"
    x-on:gallery-photos-updated.window="setPhotos($event.detail.photos)"
>
    <x-gallery.header
        :gallery="$this->gallery"
        :photo-count="$this->locked ? $this->totalPhotoCount : $this->photos->count()"
        :can-download="!$this->locked && $this->totalPhotoCount > 0"
    />

    @if ($this->clientGalleries->count() > 1)
        <nav
            class="overflow-x-auto border-b px-4 sm:px-8"
            aria-label="Galerias deste cliente"
        >
            <div class="flex min-w-max gap-2 py-3">
                @foreach ($this->clientGalleries as $clientGallery)
                    <flux:button
                        :href="route('gallery.show', $clientGallery->slug)"
                        :variant="$clientGallery->id === $this->galleryId ? 'primary' : 'outline'"
                        :aria-current="$clientGallery->id === $this->galleryId ? 'page' : null"
                        size="sm"
                        class="rounded-full!"
                        wire:key="client-gallery-{{ $clientGallery->id }}"
                    >
                        {{ $clientGallery->name }}
                    </flux:button>
                @endforeach
            </div>
        </nav>
    @endif

    @if ($this->locked)
        <x-gallery.grid-skeleton :total-photo-count="$this->totalPhotoCount" />
    @else
        <x-gallery.grid
            :photos="$this->photos"
            :total-photo-count="$this->totalPhotoCount"
        />
    @endif

    <x-gallery.lightbox wire:ignore />
</div>
