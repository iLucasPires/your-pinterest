<header class="fi-header fi-header-has-subheading">
    <div>
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="fi-header-heading">Google Drive</h1>
        </div>
        <p class="fi-header-subheading">
            Storage overview and gallery folders linked to your Google Drive.
        </p>
    </div>

    @if ($isConnected)
        <div class="fi-header-actions-ctn">
            <x-filament::button
                color="gray"
                :href="$refreshUrl"
                icon="heroicon-o-arrow-path"
                size="sm"
                tag="a"
            >
                Refresh space
            </x-filament::button>
        </div>
    @endif
</header>
