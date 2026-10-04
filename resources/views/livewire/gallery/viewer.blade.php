<div x-data="window.galleryLightbox(@js($this->lightboxPhotos))" x-on:gallery-photos-updated.window="setPhotos($event.detail.photos)">
    <x-gallery.header
        :gallery="$this->gallery"
        :photo-count="$this->locked ? $this->totalPhotoCount : $this->photos->count()"
        :can-download="!$this->locked && $this->totalPhotoCount > 0"
    />

    <div class="flex flex-col gap-8 p-3 sm:p-5 lg:p-6">
        @if ($this->clientGalleries->count() > 1)
            <x-gallery.select :gallery-id="$this->gallery->id" :client-galleries="$this->clientGalleries" />
        @endif

        <x-gallery.masonry
            :photos="$this->photos"
            :total-photo-count="$this->totalPhotoCount"
            :can-download="!$this->locked && $this->totalPhotoCount > 0"
            :locked="$this->locked"
        />
    </div>
    <x-gallery.lightbox wire:ignore />
</div>
