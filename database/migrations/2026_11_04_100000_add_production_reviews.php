<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('audition_videos',function(Blueprint $table){$table->id();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->unsignedTinyInteger('position');$table->string('file_path');$table->string('file_mime',80);$table->unsignedBigInteger('bytes');$table->timestamps();$table->unique(['audition_id','position']);});
        Schema::create('audition_comments',function(Blueprint $table){$table->id();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();$table->text('body');$table->timestamps();$table->index(['audition_id','id']);});
        Schema::create('audition_votes',function(Blueprint $table){$table->id();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->string('color',10);$table->timestamps();$table->unique(['audition_id','user_id']);});
        Schema::table('users',function(Blueprint $table){$table->boolean('is_casting_admin')->default(false);});
        /** Il primo operatore esistente gestisce gli account; la migrazione non crea credenziali. */
        $id=DB::table('users')->where('is_casting_operator',true)->orderBy('id')->value('id');
        if($id)DB::table('users')->where('id',$id)->update(['is_casting_admin'=>true]);
    }
    public function down(): void {Schema::dropIfExists('audition_votes');Schema::dropIfExists('audition_comments');Schema::dropIfExists('audition_videos');Schema::table('users',fn(Blueprint $table)=>$table->dropColumn('is_casting_admin'));}
};
