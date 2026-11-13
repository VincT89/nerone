<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('audition_videos', function (Blueprint $table) {
            $table->string('compression_status', 24)->nullable();
            $table->unsignedBigInteger('original_bytes')->nullable();
            $table->string('compression_source_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audition_videos', function (Blueprint $table) {
            $table->dropColumn(['compression_status','original_bytes','compression_source_path']);
        });
    }
};
