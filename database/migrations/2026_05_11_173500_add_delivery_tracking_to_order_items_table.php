<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->string('preparation_status', 24)->default('pending')->after('options');
            $table->unsignedInteger('delivered_quantity')->default(0)->after('preparation_status');
            $table->timestamp('delivered_at')->nullable()->after('delivered_quantity');
            $table->foreignId('served_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            $table->boolean('is_delivered')->default(false)->after('served_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('served_by');
            $table->dropColumn([
                'preparation_status',
                'delivered_quantity',
                'delivered_at',
                'is_delivered',
            ]);
        });
    }
};
