@props([
    'galleryId' => null,
    'clientGalleries' => [],
])

<ul class="flex flex-wrap gap-2">
    @foreach ($clientGalleries as $clientGallery)
        <flux:button
            class="rounded-full!"
            size="sm"
            :value="route('gallery.show', $clientGallery->slug)"
            :selected="$clientGallery->id === $galleryId"
            :variant="$clientGallery->id === $galleryId ? 'primary' : null"
            x-on:click="window.location.assign('{{ route('gallery.show', $clientGallery->slug) }}')"
        >
            {{ $clientGallery->name }}
        </flux:button>
    @endforeach
</ul>
