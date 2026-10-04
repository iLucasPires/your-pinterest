<x-filament-widgets::widget>
    <x-filament::section
        class="overflow-hidden"
        description="Synced photo count and sync status for each gallery's linked Google Drive folder."
        heading="Folders used by galleries"
    >
        <x-slot name="afterHeader">
            <div class="flex items-center gap-3">
                <flux:badge
                    icon="folder"
                    size="sm"
                    variant="pill"
                >
                    {{ $folders->count() }} linked {{ \Illuminate\Support\Str::plural('folder', $folders->count()) }}
                </flux:badge>
                <x-filament::button
                    color="primary"
                    href="{{ \App\Filament\Resources\GalleryResource::getUrl('create') }}"
                    icon="heroicon-o-plus"
                    size="sm"
                    tag="a"
                >
                    Link another folder
                </x-filament::button>
            </div>
        </x-slot>

        @if ($folders->isEmpty())
            <x-filament::empty-state
                :contained="false"
                description="Link a Google Drive folder to a gallery to see its synced photos here."
                heading="No folders linked yet"
                icon="heroicon-o-folder-open"
                icon-color="gray"
            >
                <x-slot name="footer">
                    <x-filament::button
                        color="primary"
                        href="{{ \App\Filament\Resources\GalleryResource::getUrl('create') }}"
                        icon="heroicon-o-plus"
                        tag="a"
                    >
                        Create a gallery
                    </x-filament::button>
                </x-slot>
            </x-filament::empty-state>
        @else
            <div class="space-y-3">
                <div
                    class="hidden grid-cols-[minmax(180px,0.9fr)_minmax(220px,1.6fr)_auto] gap-5 px-4 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-gray-500 lg:grid">
                    <span>Gallery and Drive folder</span>
                    <span>Synced photo count</span>
                    <span>Actions</span>
                </div>

                @foreach ($folders as $folder)
                    <article
                        class="grid gap-4 rounded-xl border border-gray-200 p-4 transition hover:bg-gray-50 lg:grid-cols-[minmax(180px,0.9fr)_minmax(220px,1.6fr)_auto] lg:items-center lg:gap-5 dark:border-white/10 dark:hover:bg-white/5"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <div
                                class="text-primary-600 dark:text-primary-400 grid size-10 shrink-0 place-items-center rounded-xl bg-gray-100 dark:bg-white/10">
                                <flux:icon
                                    icon="folder"
                                    variant="micro"
                                />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading
                                        class="truncate"
                                        size="sm"
                                    >
                                        {{ $folder['name'] }}
                                    </flux:heading>
                                    @if ($folder['photoCount'] > 0)
                                        <flux:badge
                                            color="green"
                                            size="sm"
                                            variant="pill"
                                        >
                                            {{ number_format($folder['photoCount']) }} synced
                                        </flux:badge>
                                    @else
                                        <flux:badge
                                            color="amber"
                                            size="sm"
                                            variant="pill"
                                        >
                                            Not synced
                                        </flux:badge>
                                    @endif
                                </div>
                                <flux:text class="truncate text-xs text-gray-500">
                                    {{ $folder['folderName'] }}
                                </flux:text>
                                <flux:text class="text-xs text-gray-500">
                                    Synced {{ $folder['lastSyncedAt'] }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3 text-xs">
                                <span class="font-medium">
                                    {{ number_format($folder['photoCount']) }}
                                    {{ \Illuminate\Support\Str::plural('photo', $folder['photoCount']) }}
                                </span>
                                <span class="text-gray-500">
                                    {{ $folder['scalePercent'] }}% of max
                                </span>
                            </div>
                            <div
                                aria-label="{{ number_format($folder['photoCount']) }} synced photos"
                                aria-valuemax="{{ $scaleMax }}"
                                aria-valuemin="0"
                                aria-valuenow="{{ $folder['photoCount'] }}"
                                class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"
                                role="progressbar"
                            >
                                <div
                                    class="bg-primary-500 h-full rounded-full transition-all"
                                    style="width: {{ $folder['scalePercent'] }}%"
                                ></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 lg:justify-end">
                            <x-filament::button
                                :href="$folder['editUrl']"
                                color="gray"
                                icon="heroicon-o-pencil-square"
                                size="sm"
                                tag="a"
                                tooltip="Manage gallery"
                            />
                            <x-filament::button
                                :href="$folder['driveUrl']"
                                color="gray"
                                icon="heroicon-o-arrow-top-right-on-square"
                                size="sm"
                                tag="a"
                                target="_blank"
                                tooltip="Open Google Drive folder"
                            />
                        </div>
                    </article>
                @endforeach
            </div>

            <div
                class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
                <flux:text class="text-xs text-gray-500">
                    {{ number_format($folders->count()) }} folders · {{ number_format($syncedPhotoCount) }} synced
                    photos
                </flux:text>
                <flux:text class="text-xs text-gray-500">
                    Bar lengths are relative to the largest gallery.
                </flux:text>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
