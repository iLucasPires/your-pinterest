<x-filament-panels::page>
    @php
        $connection = $this->getConnection();
    @endphp

    <div class="space-y-6">
        @if (session('success'))
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <flux:badge
                        color="green"
                        icon="check"
                        size="sm"
                        variant="pill"
                    >
                        Success
                    </flux:badge>
                    <flux:text class="text-green-600 dark:text-green-400">{{ session('success') }}</flux:text>
                </div>
            </x-filament::section>
        @endif

        @if (session('error'))
            <x-filament::section>
                <div class="flex items-center gap-3">
                    <flux:badge
                        color="red"
                        icon="exclamation-triangle"
                        size="sm"
                        variant="pill"
                    >
                        Error
                    </flux:badge>
                    <flux:text class="text-red-600 dark:text-red-400">{{ session('error') }}</flux:text>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section
            description="Read-only access to list and serve photos. Your photos are never copied to this server."
            heading="Google Drive connection"
        >
            <x-slot name="afterHeader">
                @if ($connection)
                    <flux:badge
                        color="green"
                        icon="check-circle"
                        size="sm"
                        variant="pill"
                    >
                        Connected
                    </flux:badge>
                @else
                    <flux:badge
                        icon="minus-circle"
                        size="sm"
                        variant="pill"
                    >
                        Not connected
                    </flux:badge>
                @endif
            </x-slot>

            @if ($connection)
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:icon icon="envelope" variant="micro" />
                            <flux:text class="font-medium">{{ $connection->google_email }}</flux:text>
                        </div>

                        @if ($connection->token_expires_at)
                            <flux:badge
                                color="{{ $connection->isExpired() ? 'amber' : null }}"
                                icon="key"
                                size="sm"
                                variant="pill"
                            >
                                Token expires {{ $connection->token_expires_at->diffForHumans() }}
                                @if ($connection->isExpired())
                                    · refreshes automatically
                                @endif
                            </flux:badge>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-filament::button
                            color="gray"
                            href="{{ route('google.redirect') }}"
                            icon="heroicon-o-arrow-path"
                            size="sm"
                            tag="a"
                        >
                            Reconnect
                        </x-filament::button>

                        <form method="POST" action="{{ route('google.disconnect') }}">
                            @csrf
                            <x-filament::button
                                color="danger"
                                icon="heroicon-o-x-circle"
                                onclick="return confirm('Disconnect Google Drive? Existing galleries will still work, but syncing will stop.')"
                                size="sm"
                                type="submit"
                            >
                                Disconnect
                            </x-filament::button>
                        </form>
                    </div>
                </div>
            @else
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                    <div class="max-w-2xl space-y-2">
                        <flux:heading size="sm">Connect a Google account</flux:heading>
                        <flux:text class="text-sm text-gray-500">
                            Only read access to Drive files is requested. The application cannot modify or delete your
                            photos.
                        </flux:text>
                    </div>

                    <x-filament::button
                        color="primary"
                        href="{{ route('google.redirect') }}"
                        icon="heroicon-o-link"
                        tag="a"
                    >
                        Connect Google Drive
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
