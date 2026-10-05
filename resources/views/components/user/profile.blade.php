@props(['name', 'email', 'avatarUrl'])

<div class="shrink-0">
    <flux:dropdown position="bottom" align="end">
        <flux:profile circle :chevron="false" />

        <flux:menu class="min-w-56">
            <div class="px-2.5 py-2">
                <flux:text class="font-semibold">

                </flux:text>

                <flux:text size="sm" class="text-stone-500">
                    Visualização da galeria
                </flux:text>
            </div>
            <flux:menu.separator />

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <flux:menu.item type="submit" icon="arrow-right-start-on-rectangle">
                    Sair
                </flux:menu.item>
            </form>
        </flux:menu>
    </flux:dropdown>
</div>
