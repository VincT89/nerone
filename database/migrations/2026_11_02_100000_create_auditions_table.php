<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('auditions', function (Blueprint $table) {
            $table->id(); $table->uuid('reference')->unique();
            $table->string('first_name',80); $table->string('last_name',80); $table->string('email');
            $table->string('phone',40); $table->string('city',100); $table->string('discipline',40);
            $table->text('presentation')->nullable(); $table->string('video_path')->nullable(); $table->string('video_mime')->nullable();
            $table->string('status',30)->default('Da vedere')->index(); $table->text('notes')->nullable();
            $table->string('portrait')->nullable(); $table->boolean('is_demo')->default(false);
            $table->timestamp('consented_at')->nullable(); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('auditions'); }
};
