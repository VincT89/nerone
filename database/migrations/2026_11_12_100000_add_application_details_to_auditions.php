<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auditions', function (Blueprint $table) {
            $table->json('application_details')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('full_photo_path')->nullable();
            $table->string('full_photo_mime')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auditions', fn (Blueprint $table) => $table->dropColumn([
            'application_details', 'cv_path', 'full_photo_path', 'full_photo_mime',
        ]));
    }
};
