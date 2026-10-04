<x-filament-widgets::widget>
    <x-filament::section
        class="overflow-hidden"
        description="Storage allocation across Google Drive and your other Google services."
        heading="Google Drive storage breakdown"
    >
        <x-slot name="afterHeader">
            <flux:badge
                icon="chart-pie"
                size="sm"
                variant="pill"
            >
                STORAGE
            </flux:badge>
        </x-slot>

        @if ($storage)
            <div class="">
                <div class="space-y-5">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <flux:text class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
                                Overall allocation
                            </flux:text>
                            <flux:heading class="mt-1" size="sm">
                                {{ $storage['usedPercent'] === null ? 'Unlimited storage' : $storage['total'] . ' of ' . $storage['limit'] . ' allocated' }}
                            </flux:heading>
                        </div>
                        @if ($storage['usedPercent'] !== null)
                            <flux:badge size="sm" variant="pill">
                                {{ $storage['available'] }} free
                            </flux:badge>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>
                                Google Drive
                                <x-slot name="trailing">
                                    <span
                                        class="tabular-nums">{{ number_format($storage['driveAllocationPercent'], 1) }}%</span>
                                </x-slot>
                            </flux:label>
                            <x-ui.progress-multi :value="$storage['driveAllocationPercent']" color="amber" />
                            <flux:description>
                                {{ $storage['drive'] }}{{ $storage['usedPercent'] === null ? ' of current usage' : ' of plan storage' }}
                            </flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>
                                Other services
                                <x-slot name="trailing">
                                    <span
                                        class="tabular-nums">{{ number_format($storage['otherAllocationPercent'], 1) }}%</span>
                                </x-slot>
                            </flux:label>
                            <x-ui.progress-multi :value="$storage['otherAllocationPercent']" color="blue" />
                            <flux:description>
                                {{ $storage['other'] }}{{ $storage['usedPercent'] === null ? ' of current usage' : ' of plan storage' }}
                            </flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>
                                Available
                                <x-slot name="trailing">
                                    <span class="tabular-nums">
                                        {{ $storage['usedPercent'] === null ? 'Unlimited' : number_format($storage['availablePercent'], 1) . '%' }}
                                    </span>
                                </x-slot>
                            </flux:label>
                            <x-ui.progress-multi :value="$storage['availablePercent']" color="emerald" />
                            <flux:description>
                                {{ $storage['usedPercent'] === null ? 'No fixed storage limit' : $storage['available'] . ' free' }}
                            </flux:description>
                        </flux:field>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:card variant="soft">
                            <flux:text class="mb-2">Google Drive</flux:text>
                            <flux:heading>{{ $storage['drive'] }}</flux:heading>
                            <flux:text>Photos and files</flux:text>
                        </flux:card>

                        <flux:card variant="soft">
                            <flux:text class="mb-2">Other services</flux:text>
                            <flux:heading>{{ $storage['other'] }}</flux:heading>
                            <flux:text>Gmail and Google Photos</flux:text>
                        </flux:card>

                        <flux:card variant="soft">
                            <flux:text class="mb-2">Available</flux:text>
                            <flux:heading>{{ $storage['available'] }}</flux:heading>
                            <flux:text>
                                {{ $storage['usedPercent'] === null ? 'No fixed storage limit' : 'Free across your plan' }}
                            </flux:text>
                        </flux:card>
                    </div>

                    <flux:text class="text-xs text-gray-500">
                        Usage is reported by Google Drive and can include storage shared with your Google account.
                    </flux:text>
                </div>
            </div>
        @else
            <x-filament::empty-state
                :contained="false"
                description="Google Drive did not return storage quota information."
                heading="Storage information unavailable"
                icon="heroicon-o-chart-pie"
                icon-color="gray"
            />
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
