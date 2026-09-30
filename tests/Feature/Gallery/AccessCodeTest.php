<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_code_and_session_no_longer_grant_private_access(): void
    {
        $gallery = Gallery::factory()->for(User::factory())->published()->protected('482931')->create();
        $photo = Photo::create([
            'gallery_id' => $gallery->id, 'drive_file_id' => 'private-file', 'filename' => 'private.jpg',
        ]);
        $this->post('/g/'.$gallery->slug.'/access', ['code' => '482931'])->assertNotFound();
        $this->withSession(['gallery_access_'.$gallery->id => true])
            ->get(route('gallery.photo.download', [$gallery->slug, $photo->id]))->assertForbidden();
        $this->get(route('gallery.show', $gallery->slug))
            ->assertSee('Continuar com Google')->assertDontSee('name="code"', false);
    }
}
