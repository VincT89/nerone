<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $selectionId = DB::table('selections')->latest('id')->value('id');
        $phaseId = DB::table('selection_phases')
            ->where('selection_id', $selectionId)
            ->orderBy('position')
            ->value('id');

        DB::table('selection_phases')->where('id', $phaseId)->whereNull('ends_at')->update([
            'ends_at' => CarbonImmutable::parse('2026-12-02', 'Europe/Rome')->endOfDay()->utc()->format('Y-m-d H:i:s'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        /** La scadenza è gestita dalla produzione, quindi il rollback conserva il valore salvato. */
    }
};
