@props(['photos'])

@if ($photos->isNotEmpty())
    <div
        aria-hidden="true"
        class="pointer-events-none absolute left-1/2 top-[54%] z-0 h-[42%] w-screen -translate-x-1/2 -translate-y-1/2 -rotate-6 scale-105 overflow-hidden opacity-25 blur-[1px]"
    >
        <div class="home-marquee flex w-max gap-3 sm:gap-4">
            @for ($copy = 0; $copy < 2; $copy++)
                @foreach ($photos as $photo)
                    <div class="h-32 w-40 shrink-0 overflow-hidden rounded-2xl sm:h-40 sm:w-52">
                        <img
                            src="{{ $photo->thumbnail_url }}"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover"
                        >
                    </div>
                @endforeach
            @endfor
        </div>
    </div>
@endif