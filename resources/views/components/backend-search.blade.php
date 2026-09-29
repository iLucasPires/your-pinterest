@props([
    'placeholder' => 'Buscar...',
    'ariaLabel' => 'Buscar',
    'model',
])

<x-popover placement="bottom" :offset="12" open-expression="open && hasQuery">
    <x-slot:trigger>
        <div class="w-full rounded-full bg-white p-2 shadow-md dark:bg-zinc-900">
            <div class="flex items-center gap-2">
                <x-heroicon-o-magnifying-glass class="size-5 shrink-0 text-zinc-400" />

                <input
                    wire:model.live.debounce.300ms="{{ $model }}"
                    type="text"
                    placeholder="{{ $placeholder }}"
                    aria-label="{{ $ariaLabel }}"
                    autocomplete="off"
                    x-on:input="hasQuery = $event.target.value.trim() !== ''"
                    x-on:focus="open = true; hasQuery = $event.target.value.trim() !== ''"
                    x-on:keydown.escape="open = false"
                />

                @isset($actions)
                    {{ $actions }}
                @endisset
            </div>
        </div>
    </x-slot:trigger>

    <div
        class="w-full min-w-80 rounded-xl bg-white p-2 text-left shadow-2xl shadow-stone-300/40 dark:bg-zinc-900 dark:shadow-black/30">
        {{ $slot }}
    </div>
</x-popover>
