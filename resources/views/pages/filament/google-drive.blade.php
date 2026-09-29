<x-filament-panels::page>
    @php
        $connection = $this->getConnection();
    @endphp

    <div class="max-w-xl space-y-6">

        {{-- Flash messages --}}
        @if (session('success'))
            <x-filament::section>
                <p class="text-sm text-green-600 dark:text-green-400">{{ session('success') }}</p>
            </x-filament::section>
        @endif

        @if (session('error'))
            <x-filament::section>
                <p class="text-sm text-red-600 dark:text-red-400">{{ session('error') }}</p>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Google Drive Connection</x-slot>
            <x-slot name="description">
                You Pinterest needs read-only access to your Google Drive to list and serve photos.
                Your photos are never copied to this server.
            </x-slot>

            @if ($connection)
                <div class="flex items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex size-2 rounded-full bg-green-500"></span>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">Connected</span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $connection->google_email }}
                        </p>
                        @if ($connection->token_expires_at)
                            <p class="text-xs text-gray-400">
                                Token expires {{ $connection->token_expires_at->diffForHumans() }}
                                @if ($connection->isExpired())
                                    <span class="text-amber-500">(will be refreshed automatically)</span>
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <x-filament::button
                            href="{{ route('google.redirect') }}"
                            tag="a"
                            color="gray"
                            size="sm"
                        >
                            Reconnect
                        </x-filament::button>

                        <form method="POST" action="{{ route('google.disconnect') }}">
                            @csrf
                            <x-filament::button
                                type="submit"
                                color="danger"
                                size="sm"
                                onclick="return confirm('Disconnect Google Drive? Existing galleries will still work, but syncing will stop.')"
                            >
                                Disconnect
                            </x-filament::button>
                        </form>
                    </div>
                </div>
            @else
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex size-2 rounded-full bg-gray-400"></span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Not connected</span>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Connect your Google account to allow You Pinterest to read your Drive folders.
                        Only <strong>read access</strong> to Drive files is requested — we cannot modify or delete your
                        photos.
                    </p>

                    <x-filament::button href="{{ route('google.redirect') }}" tag="a" color="primary">
                        Connect Google Drive
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
