@props([
    'galleryId' => null,
    'clientGalleries' => [],
])

<flux:select
    aria-label="Galerias deste cliente"
    size="sm"
    class="w-full sm:max-w-xs"
    x-on:change="if ($event.target.value) window.location.assign($event.target.value)"
>
    @foreach ($clientGalleries as $clientGallery)
        <flux:select.option
            :value="route('gallery.show', $clientGallery->slug)"
            :selected="$clientGallery->id === $galleryId"
        >
            {{ $clientGallery->name }}
        </flux:select.option>
    @endforeach
</flux:select>
