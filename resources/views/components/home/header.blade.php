<header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8 lg:px-12">
    <flux:brand href="{{ route('home') }}" name="Sua Galeria">
        <x-slot name="logo" class="bg-accent text-accent-foreground">
            <flux:icon name="camera" variant="micro" />
        </x-slot>
    </flux:brand>

    <flux:button
        href="{{ url('/admin') }}"
        variant="ghost"
        size="sm"
        icon="lock-open"
    >
        Área do fotógrafo
    </flux:button>
</header>
