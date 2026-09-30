<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('variants_source_hash', 64)->nullable();
            $table->timestamp('drive_modified_at', 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['thumbnail_path', 'preview_path', 'variants_source_hash']);
        });
    }
};
