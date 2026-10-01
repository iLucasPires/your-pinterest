@props(['gallery', 'featured' => false, 'compact' => false])

<li @class(['sm:col-span-2 lg:col-span-2' => $featured])>
    <a
        href="{{ route('gallery.show', $gallery->slug) }}"
        class="group block"
    >
        <flux:card class="overflow-hidden p-0! transition group-hover:border-zinc-400">
            <div @class([
                'relative overflow-hidden bg-zinc-100 dark:bg-zinc-800',
                'aspect-[2/1]' => ! $compact,
                'aspect-square' => $compact,
            ])>
                @if ($gallery->coverPhoto)
                    <img
                        src="{{ $gallery->coverPhoto->displayThumbnailUrl() }}"
                        alt="{{ $gallery->name }}"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        loading="lazy"
                    >
                @else
                    <div class="flex h-full items-center justify-center">
                        <flux:icon name="photo" class="size-7 text-zinc-400" />
                    </div>
                @endif

                <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3">
                    <div class="flex min-w-0 flex-wrap gap-1.5">
                        @if ($gallery->client)
                            <flux:badge size="sm" class="max-w-full truncate bg-white/90 text-zinc-800">
                                {{ $gallery->client->name }}
                            </flux:badge>
                        @endif

                        @if ($featured)
                            <flux:badge size="sm" variant="primary">
                                Destaque
                            </flux:badge>
                        @endif
                    </div>

                    <flux:badge rounded size="sm" icon="photo" variant="solid" class="shrink-0">
                        {{ number_format($gallery->photos_count) }} fotos
                    </flux:badge>
                </div>
            </div>

            <div class="flex min-h-[4.5rem] items-center justify-between gap-3 px-3 py-3 sm:px-4">
                <div class="min-w-0">
                    <flux:heading size="sm" class="truncate">
                        {{ $gallery->name }}
                    </flux:heading>

                    <flux:text size="xs" class="mt-1">
                        {{ ($gallery->published_at ?? $gallery->created_at)->format('d/m/Y') }}
                    </flux:text>
                </div>

               
            </div>
        </flux:card>
    </a>
</li>
