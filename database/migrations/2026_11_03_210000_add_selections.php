<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('selections',function(Blueprint $table){$table->id();$table->string('title',160);$table->text('description')->nullable();$table->boolean('is_open')->default(false)->index();$table->timestamps();});
        $id=DB::table('selections')->insertGetId(['title'=>'NERONE','is_open'=>true,'created_at'=>now(),'updated_at'=>now()]);
        Schema::table('auditions',function(Blueprint $table){$table->index('candidate_id','auditions_candidate_lookup');});
        Schema::table('auditions',function(Blueprint $table){$table->dropUnique(['candidate_id']);$table->foreignId('selection_id')->nullable()->constrained()->restrictOnDelete();});
        DB::table('auditions')->whereNull('selection_id')->update(['selection_id'=>$id]);
        Schema::table('auditions',function(Blueprint $table){$table->unique(['candidate_id','selection_id']);$table->index(['selection_id','discipline','status']);});
    }
    public function down(): void {
        Schema::table('auditions',function(Blueprint $table){$table->dropUnique(['candidate_id','selection_id']);$table->dropIndex(['selection_id','discipline','status']);$table->dropConstrainedForeignId('selection_id');});Schema::dropIfExists('selections');
    }
};
