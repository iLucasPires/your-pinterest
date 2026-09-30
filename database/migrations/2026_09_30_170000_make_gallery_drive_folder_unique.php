<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('galleries')
            ->whereNotNull('drive_folder_id')
            ->groupBy('drive_folder_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Resolve duplicate gallery drive_folder_id values before adding the unique index.');
        }

        Schema::table('galleries', function (Blueprint $table) {
            $table->unique('drive_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropUnique(['drive_folder_id']);
        });
    }
};
