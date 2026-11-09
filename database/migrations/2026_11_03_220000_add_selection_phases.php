<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('selection_phases',function(Blueprint $table){$table->id();$table->foreignId('selection_id')->constrained()->cascadeOnDelete();$table->string('title',120);$table->unsignedInteger('position');$table->dateTime('starts_at')->nullable();$table->dateTime('ends_at')->nullable();$table->timestamps();$table->unique(['selection_id','position']);});
        Schema::table('auditions',function(Blueprint $table){$table->foreignId('phase_id')->nullable()->constrained('selection_phases')->restrictOnDelete();});
        Schema::table('material_requests',function(Blueprint $table){$table->foreignId('phase_id')->nullable()->constrained('selection_phases')->restrictOnDelete();});
        foreach(DB::table('selections')->get() as $selection){$phaseId=DB::table('selection_phases')->insertGetId(['selection_id'=>$selection->id,'title'=>'Prima selezione','position'=>1,'created_at'=>now(),'updated_at'=>now()]);DB::table('auditions')->where('selection_id',$selection->id)->update(['phase_id'=>$phaseId]);}
        foreach(DB::table('material_requests')->get() as $material){$phaseId=DB::table('auditions')->where('id',$material->audition_id)->value('phase_id');DB::table('material_requests')->where('id',$material->id)->update(['phase_id'=>$phaseId]);}
        Schema::create('phase_admissions',function(Blueprint $table){$table->id();$table->foreignId('audition_id')->constrained()->cascadeOnDelete();$table->foreignId('phase_id')->constrained('selection_phases')->restrictOnDelete();$table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();$table->timestamps();$table->unique(['audition_id','phase_id']);});
    }
    public function down(): void {Schema::dropIfExists('phase_admissions');Schema::table('material_requests',fn(Blueprint $table)=>$table->dropConstrainedForeignId('phase_id'));Schema::table('auditions',fn(Blueprint $table)=>$table->dropConstrainedForeignId('phase_id'));Schema::dropIfExists('selection_phases');}
};
