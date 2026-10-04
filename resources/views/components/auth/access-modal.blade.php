@props(['gallery', 'showLogin' => false])

<flux:modal
    name="gallery-access"
    scroll="body"
    :dismissible="false"
    :escapable="false"
    :closable="false"
    class="w-full max-w-sm space-y-8"
>
    <div class="mx-auto max-w-64 space-y-2 text-center">
        <flux:heading size="xl">
            {{ $gallery->name }}
        </flux:heading>

        <flux:text>
            Entre com sua conta Google usando o e-mail autorizado para acessar esta galeria.
        </flux:text>
    </div>

    <div class="space-y-2">
        @error('email')
            <flux:text class="mt-4 text-center" role="alert">{{ $message }}</flux:text>
        @enderror

        <flux:button href="{{ route('gallery.login.google', $gallery->slug) }}" variant="primary" class="w-full">
            Continuar com Google
        </flux:button>

        <flux:text size="xs" class="text-center">
            Para baixar os originais, entre no Google Drive com o mesmo e-mail autorizado.
        </flux:text>
    </div>
</flux:modal>
