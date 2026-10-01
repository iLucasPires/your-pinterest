@props([
    'totalPhotoCount' => 0,
])

<main
    class="p-3 sm:p-5 lg:p-6"
    aria-hidden="true"
>
    <div class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4 lg:gap-5">
        @for ($index = 0; $index < $this->totalPhotoCount; $index++)
            <flux:skeleton
                wire:key="locked-gallery-photo-{{ $index }}"
                class="mb-3 aspect-[4/5] break-inside-avoid rounded-2xl blur-md sm:mb-4 lg:mb-5"
            />
        @endfor
    </div>
</main>
