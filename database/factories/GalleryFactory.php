<?php

namespace Database\Factories;

use App\Models\Gallery\Gallery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return [
            'user_id'     => User::factory(),
            'name'        => $name,
            'access_type' => Gallery::ACCESS_PUBLIC,
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }

    public function public(): static
    {
        return $this->state([
            'access_type'      => Gallery::ACCESS_PUBLIC,
            'access_code_hash' => null,
        ]);
    }

    public function protected(string $code = '123456'): static
    {
        return $this->state([
            'access_type'      => Gallery::ACCESS_CODE,
            'access_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
        ]);
    }

    public function withFolder(string $folderId): static
    {
        return $this->state([
            'drive_folder_id'   => $folderId,
            'drive_folder_name' => 'Test Folder',
        ]);
    }
}
