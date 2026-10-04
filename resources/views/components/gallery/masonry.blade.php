@props([
    'photos' => [],
    'totalPhotoCount' => 0,
    'canDownload' => false,
    'locked' => false,
])

@if ($locked)
    <div class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4 lg:gap-5">
        @for ($index = 0; $index < $totalPhotoCount; $index++)
            <flux:skeleton
                wire:key="locked-gallery-photo-{{ $index }}"
                animate="pulse"
                class="mb-3 aspect-[4/5] break-inside-avoid rounded-2xl blur-md sm:mb-4 lg:mb-5"
            />
        @endfor
    </div>
@elseif ($totalPhotoCount === 0)
    <x-ui.empty
        title="Nenhuma foto adicionada ainda"
        description="As fotos sincronizadas com a pasta do Google Drive aparecerão aqui."
        icon="photo"
    />
@elseif ($photos->isEmpty())
    <x-ui.empty
        title="Carregando fotos..."
        description="Aguarde enquanto as fotos da galeria são carregadas."
        icon="photo"
    />
@else
    <main class="transition-opacity">
        <div x-data="galleryMasonry()"
            class="gallery-masonry grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4 lg:gap-3 xl:grid-cols-5 2xl:grid-cols-6"
        >
            @foreach ($photos as $index => $photo)
                <x-gallery.card
                    :photo="$photo"
                    :index="$index"
                    :can-download="$canDownload"
                    wire:key="gallery-photo-{{ $photo->id }}"
                />
            @endforeach
        </div>
    </main>
@endif
