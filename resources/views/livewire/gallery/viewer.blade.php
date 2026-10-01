<div
    x-data="window.galleryLightbox(@js($this->lightboxPhotos))"
    x-on:gallery-photos-updated.window="setPhotos($event.detail.photos)"
>
    <x-gallery.header
        :gallery="$this->gallery"
        :photo-count="$this->locked ? $this->totalPhotoCount : $this->photos->count()"
        :can-download="!$this->locked && $this->totalPhotoCount > 0"
    />



    @if ($this->locked)
        <x-gallery.grid-skeleton 
            :total-photo-count="$this->totalPhotoCount" 
        />
    @else
        <x-gallery.grid
            :photos="$this->photos"
            :total-photo-count="$this->totalPhotoCount"
        />
    @endif

    <x-gallery.lightbox wire:ignore />
</div>
