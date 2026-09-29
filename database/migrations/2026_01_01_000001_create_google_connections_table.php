<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('google_id');
            $table->string('google_email');
            $table->text('access_token');   // encrypted
            $table->text('refresh_token');  // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('google_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_connections');
    }
};
