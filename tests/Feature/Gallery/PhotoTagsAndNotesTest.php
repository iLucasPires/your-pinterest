<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\Gallery\PhotoTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoTagsAndNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_can_attach_tags_notes_and_rating(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::create([
            'user_id' => $user->id,
            'name' => 'Casamento 2026',
            'slug' => 'casamento-2026',
            'is_published' => true,
        ]);

        $tag1 = PhotoTag::create(['user_id' => $user->id, 'name' => 'Casamento', 'slug' => 'casamento']);
        $tag2 = PhotoTag::create(['user_id' => $user->id, 'name' => 'Noivos', 'slug' => 'noivos']);

        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive_123',
            'filename' => 'foto_01.jpg',
            'notes' => 'Foto principal para o álbum da noiva.',
            'rating' => 5,
            'sort_order' => 1,
        ]);

        $photo->tags()->attach([$tag1->id, $tag2->id]);

        $this->assertDatabaseHas('photos', [
            'id' => $photo->id,
            'filename' => 'foto_01.jpg',
            'notes' => 'Foto principal para o álbum da noiva.',
            'rating' => 5,
        ]);

        $this->assertDatabaseHas('photo_tag', [
            'photo_id' => $photo->id,
            'tag_id' => $tag1->id,
        ]);

        $photo->refresh();
        $this->assertCount(2, $photo->tags);
        $this->assertEquals(['Casamento', 'Noivos'], $photo->tags->pluck('name')->all());
        $this->assertEquals('Foto principal para o álbum da noiva.', $photo->notes);
        $this->assertEquals(5, $photo->rating);
    }

    public function test_gallery_page_renders_category_pills_notes_and_rating(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::create([
            'user_id' => $user->id,
            'name' => 'Aniversário',
            'slug' => 'aniversario-2026',
            'is_published' => true,
            'access_type' => Gallery::ACCESS_PUBLIC,
        ]);

        $tagFesta = PhotoTag::create(['user_id' => $user->id, 'name' => 'Festa', 'slug' => 'festa']);
        $tagBolo = PhotoTag::create(['user_id' => $user->id, 'name' => 'Bolo', 'slug' => 'bolo']);

        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive_456',
            'filename' => 'bolo.jpg',
            'notes' => 'Momento do parabéns',
            'rating' => 4,
            'sort_order' => 1,
        ]);

        $photo->tags()->attach([$tagFesta->id, $tagBolo->id]);

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertOk();
        $response->assertSee('Festa');
        $response->assertSee('Bolo');
        $response->assertSee('Momento do parabéns');
        $response->assertSee('★ 4');
    }
}
