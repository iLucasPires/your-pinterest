<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('drive_file_id');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();       // bytes
            $table->unsignedInteger('width')->nullable();         // px
            $table->unsignedInteger('height')->nullable();        // px
            $table->string('thumbnail_url')->nullable();          // Drive thumbnail URL (short-lived)
            $table->timestamp('drive_modified_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['gallery_id', 'drive_file_id']);
            $table->index('gallery_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
