<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_gallery(): void
    {
        $user = User::factory()->create();

        $gallery = Gallery::create([
            'user_id'     => $user->id,
            'name'        => 'John & Maria',
            'slug'        => 'john-and-maria',
            'access_type' => Gallery::ACCESS_PUBLIC,
        ]);

        $this->assertDatabaseHas('galleries', [
            'user_id' => $user->id,
            'name'    => 'John & Maria',
            'slug'    => 'john-and-maria',
        ]);

        $this->assertEquals(Gallery::ACCESS_PUBLIC, $gallery->access_type);
        $this->assertFalse($gallery->is_published);
    }

    public function test_gallery_slug_is_generated_from_name(): void
    {
        $user = User::factory()->create();

        $gallery = Gallery::create([
            'user_id'     => $user->id,
            'name'        => 'My Wedding Photos',
            'access_type' => Gallery::ACCESS_PUBLIC,
        ]);

        $this->assertEquals('my-wedding-photos', $gallery->slug);
    }

    public function test_duplicate_slugs_get_incremented_suffix(): void
    {
        $user = User::factory()->create();

        Gallery::create(['user_id' => $user->id, 'name' => 'Beach Session', 'access_type' => Gallery::ACCESS_PUBLIC]);
        Gallery::create(['user_id' => $user->id, 'name' => 'Beach Session', 'access_type' => Gallery::ACCESS_PUBLIC]);

        $this->assertDatabaseHas('galleries', ['slug' => 'beach-session']);
        $this->assertDatabaseHas('galleries', ['slug' => 'beach-session-1']);
    }
}
