<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_drive_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('folder_id');
            $table->string('email');
            $table->string('permission_id')->nullable();
            $table->boolean('managed')->default(false);
            $table->unique(['user_id', 'folder_id', 'email'], 'gallery_drive_permission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_drive_permissions');
    }
};
