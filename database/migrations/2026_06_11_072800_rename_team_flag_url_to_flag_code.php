<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('flag_url', 'flag_code');
        });

        DB::table('teams')
            ->whereNotNull('flag_code')
            ->update([
                'flag_code' => DB::raw("replace(replace(flag_code, 'https://flagcdn.com/w320/', ''), '.png', '')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('teams')
            ->whereNotNull('flag_code')
            ->update([
                'flag_code' => DB::raw("concat('https://flagcdn.com/w320/', flag_code, '.png')"),
            ]);

        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('flag_code', 'flag_url');
        });
    }
};
