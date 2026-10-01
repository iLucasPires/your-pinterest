@props(['recentGalleries'])

@if ($recentGalleries->isNotEmpty())
    <section class="mx-auto mt-8 max-w-7xl pb-16" aria-labelledby="recent-galleries-title">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <flux:heading id="recent-galleries-title" size="lg" class="mt-1">
                    Galerias em Destaque
                </flux:heading>
            </div>
        </div>

        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            @foreach ($recentGalleries as $gallery)
                <x-home.recent-galley-card
                    :gallery="$gallery"
                    :featured="$loop->first"
                    :compact="$loop->iteration === 2"
                />
            @endforeach
        </ul>
    </section>
@endif