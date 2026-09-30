<?php

namespace Tests\Feature\Home;

use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_published_galleries_only(): void
    {
        $user = User::factory()->create();
        $published = $this->createGallery($user->id, 'Ensaio de Inverno', true);
        $this->createGallery($user->id, 'Rascunho');

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee('Rascunho');
    }

    public function test_home_can_search_by_client_name(): void
    {
        $client = Client::factory()->create(['name' => 'Marina Souza']);
        $user = User::factory()->create();
        $gallery = $this->createGallery($user->id, 'Casamento na Praia', true, $client->id);
        $this->createGallery($user->id, 'Outro Ensaio', true);

        $response = Livewire::test('home.gallery-search')
            ->set('search', 'Marina');

        $response
            ->assertSee($gallery->name)
            ->assertDontSee('Outro Ensaio')
            ->call('chooseGallery', $gallery->id)
            ->assertRedirect(route('gallery.show', $gallery->slug));
    }

    public function test_home_hides_galleries_that_do_not_match_the_search(): void
    {
        $user = User::factory()->create();
        $gallery = $this->createGallery($user->id, 'Casamento na Praia', true);

        Livewire::test('home.gallery-search')
            ->set('search', 'termo-sem-correspondencia')
            ->assertDontSee($gallery->name)
            ->assertSee('Nenhuma galeria encontrada.');
    }

    public function test_home_does_not_load_galleries_before_searching(): void
    {
        $user = User::factory()->create();
        $gallery = $this->createGallery($user->id, 'Galeria Pública', true);

        Livewire::test('home.gallery-search')
            ->assertDontSee($gallery->name);
    }

    public function test_home_masonry_uses_photos_from_public_galleries_only(): void
    {
        $user = User::factory()->create();
        $publicGallery = $this->createGallery($user->id, 'Galeria Pública', true);
        $privateGallery = $this->createGallery($user->id, 'Galeria Privada', true);
        $privateGallery->update(['access_type' => Gallery::ACCESS_CODE]);

        $publicPhoto = Photo::create([
            'gallery_id' => $publicGallery->id,
            'drive_file_id' => 'public-file',
            'filename' => 'public.jpg',
            'thumbnail_path' => 'public/thumbnail.webp',
        ]);
        Photo::create([
            'gallery_id' => $privateGallery->id,
            'drive_file_id' => 'private-file',
            'filename' => 'private.jpg',
            'thumbnail_path' => 'private/thumbnail.webp',
        ]);

        $response = $this->get(route('home'));

        $response->assertViewHas('publicPhotos', function ($photos) use ($publicPhoto): bool {
            return $photos->contains('id', $publicPhoto->id)
                && $photos->doesntContain('filename', 'private.jpg');
        });
    }

    private function createGallery(int $userId, string $name, bool $published = false, ?int $clientId = null): Gallery
    {
        return Gallery::create([
            'user_id' => $userId,
            'client_id' => $clientId,
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_published' => $published,
            'published_at' => $published ? now() : null,
        ]);
    }
}
