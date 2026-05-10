<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->unsignedSmallInteger('prep_minutes')->nullable()->after('price');
            $table->boolean('is_bestseller')->default(false)->after('prep_minutes');
            $table->boolean('is_available')->default(true)->after('is_bestseller');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['description', 'prep_minutes', 'is_bestseller', 'is_available']);
        });
    }
};
