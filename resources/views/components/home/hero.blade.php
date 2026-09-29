@props(['recentGalleries', 'publicPhotos'])

<section class="relative isolate mx-auto flex min-h-[calc(100vh-88px)] max-w-6xl flex-col items-center overflow-hidden px-5 pb-20 pt-16 text-center sm:px-8 sm:pt-24">
    <x-home.photo-masonry :photos="$publicPhotos" />

    <div class="relative z-10 flex w-full flex-col items-center">
    <flux:heading size="3xl" class="text-5xl tracking-tight text-stone-950 sm:text-6xl dark:text-white">
        Tirou foto comigo?
    </flux:heading>

    <flux:text class="mt-5 max-w-2xl text-base leading-6 text-stone-600 sm:text-lg sm:leading-7 dark:text-zinc-400">
        Encontre sua galeria privada ou pública e reviva com riqueza de detalhes<br class="hidden sm:block">
        os momentos que eternizamos juntos.
    </flux:text>

    <div class="mt-10 w-full max-w-2xl">
        <livewire:home.gallery-search />
    </div>

    <x-home.recent-gallery :recent-galleries="$recentGalleries" />
    </div>
</section>
