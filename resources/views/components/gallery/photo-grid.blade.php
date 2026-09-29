@props(['photos', 'totalPhotoCount'])

@if ($totalPhotoCount === 0)
    <div {{ $attributes }} class="flex min-h-[60vh] flex-col items-center justify-center gap-4 px-4 text-center">
        <div class="flex size-20 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-zinc-800">
            <flux:icon name="photo" class="size-10" />
        </div>
        <div>
            <flux:heading size="sm">Nenhuma foto adicionada ainda</flux:heading>
            <flux:text>As fotos sincronizadas com a pasta do Google Drive aparecerão aqui.</flux:text>
        </div>
    </div>
@elseif($photos->isEmpty())
    <div {{ $attributes }} class="flex min-h-[40vh] flex-col items-center justify-center gap-2 px-4 text-center">
        <flux:heading size="sm">Nenhuma foto encontrada</flux:heading>
        <flux:text>Altere a busca ou escolha outra categoria.</flux:text>
    </div>
@else
    <main class="p-3 transition-opacity sm:p-5 lg:p-6" {{ $attributes }}>
        <div class="columns-2 gap-3 sm:columns-3 sm:gap-4 lg:columns-4 lg:gap-5">
            {{ $slot }}
        </div>
    </main>
@endif
