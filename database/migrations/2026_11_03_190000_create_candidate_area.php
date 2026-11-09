<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('candidates',function(Blueprint $table){$table->id();$table->string('email')->unique();$table->string('password')->nullable();$table->timestamp('email_verified_at')->nullable();$table->rememberToken();$table->timestamps();});
        Schema::table('auditions',function(Blueprint $table){$table->foreignId('candidate_id')->nullable()->unique()->constrained()->nullOnDelete();$table->string('photo_path')->nullable();$table->string('photo_mime')->nullable();});
        Schema::create('candidate_access_tokens',function(Blueprint $table){$table->id();$table->foreignId('candidate_id')->constrained()->cascadeOnDelete();$table->string('token_hash',64)->unique();$table->timestamp('expires_at');$table->timestamp('consumed_at')->nullable();$table->timestamps();});
        Schema::create('material_requests',function(Blueprint $table){$table->id();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->string('title',160);$table->string('kind',20);$table->text('instructions');$table->text('answer')->nullable();$table->string('file_path')->nullable();$table->string('file_mime')->nullable();$table->timestamp('submitted_at')->nullable();$table->timestamps();});
        Schema::create('candidate_messages',function(Blueprint $table){$table->id();$table->foreignId('candidate_id')->constrained()->cascadeOnDelete();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->string('subject');$table->text('payload');$table->string('delivery',20)->default('local');$table->timestamp('sent_at')->nullable();$table->timestamps();});
    }
    public function down(): void {
        Schema::dropIfExists('candidate_messages');Schema::dropIfExists('material_requests');Schema::dropIfExists('candidate_access_tokens');
        Schema::table('auditions',function(Blueprint $table){$table->dropConstrainedForeignId('candidate_id');$table->dropColumn(['photo_path','photo_mime']);});Schema::dropIfExists('candidates');
    }
};
