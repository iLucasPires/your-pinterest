<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
use App\Policies\GalleryPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographer_can_access_own_gallery(): void
    {
        $user    = User::factory()->create();
        $gallery = Gallery::factory()->for($user)->create();

        $policy = new GalleryPolicy();

        $this->assertTrue($policy->view($user, $gallery));
        $this->assertTrue($policy->update($user, $gallery));
        $this->assertTrue($policy->delete($user, $gallery));
    }

    public function test_photographer_cannot_access_another_photographers_gallery(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $gallery = Gallery::factory()->for($owner)->create();

        $policy = new GalleryPolicy();

        $this->assertFalse($policy->view($other, $gallery));
        $this->assertFalse($policy->update($other, $gallery));
        $this->assertFalse($policy->delete($other, $gallery));
    }
}
