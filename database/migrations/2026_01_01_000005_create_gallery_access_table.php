<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks sessions that have been granted access to a code-protected gallery.
        // Used to enforce rate limiting and re-check on new sessions without requiring accounts.
        Schema::create('gallery_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('granted_at');
            $table->timestamps();

            $table->index(['gallery_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_access');
    }
};
