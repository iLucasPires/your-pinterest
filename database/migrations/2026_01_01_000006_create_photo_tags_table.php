<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index('user_id');
        });

        Schema::create('photo_photo_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_tag_id')->constrained('photo_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['photo_id', 'photo_tag_id']);
            $table->index('photo_id');
            $table->index('photo_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_photo_tag');
        Schema::dropIfExists('photo_tags');
    }
};
