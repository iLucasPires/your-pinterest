<?php

namespace Tests\Feature\Gallery;

use App\Livewire\Home\GallerySearch;
use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Tests\TestCase;

class GalleryAccessLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_preview_uses_local_copy_without_calling_google(): void
    {
        Storage::fake('photos');

        $owner = User::factory()->create();
        $gallery = Gallery::factory()->for($owner)->published()->public()->create();
        $path = 'galleries/'.$gallery->id.'/photos/1/local.jpg';
        Storage::disk('photos')->put($path, 'local-photo-bytes');

        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive-file-1',
            'filename' => 'photo.jpg',
            'preview_path' => $path,
            'sort_order' => 0,
        ]);

        $factory = \Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldNotReceive('make');
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);

        $this->get(route('gallery.photo.preview', [$gallery->slug, $photo->id]))
            ->assertOk()
            ->assertStreamedContent('local-photo-bytes');

        $this->get('/storage/'.$path)->assertForbidden();
    }

    public function test_link_gallery_is_directly_accessible_but_not_discoverable(): void
    {
        $owner = User::factory()->create();
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'name' => 'Link only album',
            'access_type' => Gallery::ACCESS_LINK,
        ]);

        $this->get(route('gallery.show', $gallery->slug))
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false)
            ->assertDontSee('gallery-access');

        $this->get(route('home'))->assertDontSee($gallery->name);

        Livewire::test(GallerySearch::class)
            ->set('search', 'Link only album')
            ->assertDontSee($gallery->name);
    }

    public function test_private_gallery_requires_matching_authenticated_email(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'name' => 'Private album',
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => $client->id,
        ]);
        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive_private',
            'filename' => 'private-photo.jpg',
            'sort_order' => 1,
        ]);

        $this->get(route('gallery.show', $gallery->slug))
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false)
            ->assertSee('Entre com sua conta Google usando o e-mail autorizado para acessar esta galeria.')
            ->assertDontSee($photo->filename);

        $this->get(route('gallery.photo.preview', [$gallery->slug, $photo->id]))
            ->assertForbidden();
        $this->get(route('gallery.photo.download', [$gallery->slug, $photo->id]))
            ->assertForbidden();

        $unauthorized = User::factory()->create(['email' => 'other@example.test']);
        $this->actingAs($unauthorized)
            ->get(route('gallery.show', $gallery->slug))
            ->assertDontSee($photo->filename)
            ->assertSee('gallery-access');
        $this->actingAs($unauthorized)
            ->get(route('gallery.photo.preview', [$gallery->slug, $photo->id]))
            ->assertForbidden();

        $authorized = User::factory()->create(['email' => 'client@example.test']);
        $this->actingAs($authorized)
            ->get(route('gallery.show', $gallery->slug))
            ->assertOk()
            ->assertDontSee('gallery-access')
            ->assertSee($photo->filename);
    }

    public function test_gallery_login_only_offers_google_and_rejects_password_sign_in(): void
    {
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => $client->id,
        ]);
        User::factory()->create(['email' => $client->email]);

        $this->get(route('gallery.login', $gallery->slug))
            ->assertRedirect(route('gallery.show', ['slug' => $gallery->slug, 'mode' => 'login']));

        $this->get(route('gallery.show', ['slug' => $gallery->slug, 'mode' => 'login']))
            ->assertOk()
            ->assertSee('Continuar com Google')
            ->assertSee(route('gallery.login.google', $gallery->slug), false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="email"', false);

        $this->post('/g/'.$gallery->slug.'/login', [
            'email' => $client->email,
            'password' => 'password',
        ])->assertStatus(405);

        $this->assertGuest();
    }

    public function test_google_sign_in_creates_a_client_account_only_for_verified_authorized_email(): void
    {
        $owner = User::factory()->create();
        $clientRecord = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => $clientRecord->id,
        ]);
        $socialiteUser = SocialiteUser::fake([
            'name' => 'Gallery Client',
            'email' => 'client@example.test',
            'email_verified' => true,
        ]);
        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->withSession(['gallery_google_login' => [
            'gallery_id' => $gallery->id,
            'slug' => $gallery->slug,
        ]])
            ->get(route('google.callback'))
            ->assertRedirect(route('gallery.show', $gallery->slug));

        $client = User::query()->where('email', 'client@example.test')->firstOrFail();
        $this->assertTrue($client->is_client_account);
        $this->assertAuthenticatedAs($client);
    }

    public function test_google_sign_in_rejects_unverified_or_unmatched_email(): void
    {
        $owner = User::factory()->create();
        $clientRecord = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => $clientRecord->id,
        ]);
        $socialiteUser = SocialiteUser::fake([
            'email' => 'other@example.test',
            'email_verified' => true,
        ]);
        $provider = \Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialiteUser);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->withSession(['gallery_google_login' => [
            'gallery_id' => $gallery->id,
            'slug' => $gallery->slug,
        ]])
            ->get(route('google.callback'))
            ->assertRedirect(route('gallery.login', $gallery->slug))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'other@example.test']);
        $this->assertGuest();
    }

    public function test_private_galleries_are_excluded_from_search_and_recent_home_list(): void
    {
        $owner = User::factory()->create();
        $public = Gallery::factory()->for($owner)->published()->public()->create([
            'name' => 'Visible public album',
        ]);
        $client = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $private = Gallery::factory()->for($owner)->published()->create([
            'name' => 'Hidden private album',
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => $client->id,
        ]);

        $this->get(route('home'))
            ->assertSee($public->name)
            ->assertDontSee($private->name);

        Livewire::test(GallerySearch::class)
            ->set('search', 'album')
            ->assertSee($public->name)
            ->assertDontSee($private->name);
    }
}
