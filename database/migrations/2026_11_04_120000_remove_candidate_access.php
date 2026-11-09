<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        if(DB::table('auditions')->select('selection_id','email')->groupBy('selection_id','email')->havingRaw('count(*) > 1')->exists())throw new RuntimeException('Sono presenti candidature duplicate: verificare i dati prima di aggiungere il vincolo email.');
        if(!Schema::hasIndex('auditions','auditions_selection_email_unique'))Schema::table('auditions',fn(Blueprint $table)=>$table->unique(['selection_id','email'],'auditions_selection_email_unique'));
        /** Si eliminano esclusivamente gli accessi candidati; le candidature mantengono tutti i dati e gli allegati. */
        DB::transaction(function(){DB::table('candidate_access_tokens')->delete();DB::table('candidate_messages')->delete();DB::table('candidates')->delete();});
    }
    public function down(): void {
        /** Un rollback dello schema non ricrea gli account e i token eliminati. */
        if(Schema::hasIndex('auditions','auditions_selection_email_unique'))Schema::table('auditions',fn(Blueprint $table)=>$table->dropUnique('auditions_selection_email_unique'));
    }
};
