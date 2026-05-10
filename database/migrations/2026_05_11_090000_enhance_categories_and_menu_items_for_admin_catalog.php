<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('name');
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->string('icon', 64)->nullable()->after('is_active');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('discount_price', 10, 2)->nullable()->after('price');
            $table->boolean('is_popular')->default(false)->after('is_bestseller');
            $table->string('dietary_type', 20)->nullable()->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'is_active', 'icon']);
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['discount_price', 'is_popular', 'dietary_type']);
        });
    }
};
