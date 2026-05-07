<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->uuid('qr_token')->nullable()->unique()->after('status');
        });

        DB::table('dining_tables')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(fn (object $table) => DB::table('dining_tables')
                ->where('id', $table->id)
                ->update(['qr_token' => (string) Str::uuid()]));

    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropUnique(['qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};
