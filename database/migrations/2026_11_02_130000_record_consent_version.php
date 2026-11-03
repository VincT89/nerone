<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void { Schema::table('auditions',function(Blueprint $table) { $table->timestamp('adult_confirmed_at')->nullable(); $table->string('privacy_version',30)->nullable(); }); }
    public function down():void { Schema::table('auditions',function(Blueprint $table) { $table->dropColumn(['adult_confirmed_at','privacy_version']); }); }
};
