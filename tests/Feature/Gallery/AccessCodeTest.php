<?php

namespace Tests\Feature\Gallery;

use App\Actions\Gallery\SetGalleryAccessCode;
use App\Models\Gallery\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_code_is_stored_as_hash(): void
    {
        $user    = User::factory()->create();
        $gallery = Gallery::factory()->for($user)->create();

        app(SetGalleryAccessCode::class)->handle($gallery, '482931');

        $gallery->refresh();

        $this->assertNotNull($gallery->access_code_hash);
        $this->assertNotEquals('482931', $gallery->access_code_hash);
        $this->assertTrue(Hash::check('482931', $gallery->access_code_hash));
    }

    public function test_correct_code_grants_access(): void
    {
        $user    = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->protected('482931')
            ->create();

        $response = $this->post(route('gallery.access.store', $gallery->slug), [
            'code' => '482931',
        ]);

        $response->assertRedirect(route('gallery.show', $gallery->slug));
    }

    public function test_incorrect_code_returns_error(): void
    {
        $user    = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->protected('482931')
            ->create();

        $response = $this->post(route('gallery.access.store', $gallery->slug), [
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_generated_code_is_6_digits(): void
    {
        $code = SetGalleryAccessCode::generateCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_after_correct_code_gallery_is_visible(): void
    {
        $user    = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->protected('482931')
            ->create();

        // Simulate entering the code
        $this->post(route('gallery.access.store', $gallery->slug), ['code' => '482931']);

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertOk();
        $response->assertViewIs('pages.gallery.show');
    }
}
