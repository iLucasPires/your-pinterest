@props(['gallery'])

<flux:modal
    name="gallery-access"
    scroll="body"
    :dismissible="false"
    :escapable="false"
    :closable="false"
    class="w-full max-w-sm"
>
    <div class="mx-auto max-w-64 space-y-2 text-center">
        <flux:heading id="access-title" size="xl">
            {{ $gallery->name }}
        </flux:heading>

        <flux:text id="access-description">
            Enter the access code to view these photographs.
        </flux:text>
    </div>

    <form
        method="POST"
        action="{{ route('gallery.access.store', $gallery->slug) }}"
        @class(['mt-6', 'space-y-4'])
    >
        @csrf

        <flux:otp
            id="code"
            name="code"
            wire:model="code"
            length="6"
            label="OTP Code"
            label:sr-only
            :error:icon="false"
            error:class="text-center"
            class="mx-auto"
        />

        @error('code')
            <flux:text id="code-error">
                {{ $message }}
            </flux:text>
        @enderror

        <flux:button type="submit" class="w-full" variant="primary">
            Continue
        </flux:button>
    </form>
</flux:modal>