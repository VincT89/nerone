<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('auditions', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('auditions', function (Blueprint $table) {
            $table->dropIndex(['deletion_requested_at']);
            $table->dropColumn('deletion_requested_at');
        });
    }
};
