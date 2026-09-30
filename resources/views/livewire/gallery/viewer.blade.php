<div
    x-data="window.galleryLightbox(@js($this->lightboxPhotos))"
    x-on:gallery-photos-updated.window="setPhotos($event.detail.photos)"
>
    <x-gallery.header
        :gallery="$this->gallery"
        :tags="$this->tags"
        :selected-tag-id="$selectedTagId"
        :photo-count="$this->locked ? $this->totalPhotoCount : $this->photos->count()"
    >
        <x-slot:search>
            <flux:input
                id="photo-search-input"
                type="search"
                icon="magnifying-glass"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por foto, tag ou nota..."
                aria-label="Buscar fotos"
                input:class="rounded-full!"
            />
        </x-slot:search>


    </x-gallery.header>

     @if ($this->tags->isNotEmpty())
        <x-gallery.phototag-filter
            :tags="$this->tags"
            :selected-tag-id="$selectedTagId"
        />
    @endif

    @if($this->locked)
        <main class="p-3 sm:p-5 lg:p-6" aria-hidden="true">
            <div class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4 lg:gap-5">
                @for($index = 0; $index < $this->totalPhotoCount; $index++)
                    <flux:skeleton
                        wire:key="locked-gallery-photo-{{ $index }}"
                        class="mb-3 aspect-[4/5] break-inside-avoid rounded-2xl blur-md sm:mb-4 lg:mb-5"
                    />
                @endfor
            </div>
        </main>
    @else
        <x-gallery.photo-grid
            :photos="$this->photos"
            :total-photo-count="$this->totalPhotoCount"
        >
            @foreach($this->photos as $index => $photo)
                <x-gallery.photo-card
                    wire:key="gallery-photo-{{ $photo->id }}"
                    :photo="$photo"
                    :index="$index"
                />
            @endforeach
        </x-gallery.photo-grid>
    @endif

    <x-gallery.lightbox wire:ignore />
</div>
