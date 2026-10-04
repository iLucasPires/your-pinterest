@props([
    'gallery',
])

<div class="shrink-0">
          <flux:dropdown
              position="bottom"
              align="end"
          >
              <flux:profile
                  circle
                  :chevron="false"
                  avatar="https://robohash.org/{{ rawurlencode($gallery->slug) }}.png?size=64x64&set=set4"
              />

              <flux:menu class="min-w-56">
                  <div class="px-2.5 py-2">
                      <flux:text class="font-semibold">
                          {{ $gallery->client?->name ?? 'Visitante' }}
                      </flux:text>

                      <flux:text
                          size="sm"
                          class="text-stone-500"
                      >
                          Visualização da galeria
                      </flux:text>
                  </div>
              </flux:menu>
          </flux:dropdown>
      </div>
