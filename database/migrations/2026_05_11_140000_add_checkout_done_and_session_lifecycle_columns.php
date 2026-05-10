<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('order_number')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('checkout_at')->nullable();
        });

        Schema::table('customer_sessions', function (Blueprint $table) {
            $table->string('status', 24)->default('active');
            $table->decimal('total_bill', 12, 2)->nullable();
        });

        DB::table('orders')->whereNull('ordered_at')->update([
            'ordered_at' => DB::raw('created_at'),
        ]);

        DB::table('customer_sessions')->whereNotNull('closed_at')->update([
            'status' => 'completed',
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_sessions', function (Blueprint $table) {
            $table->dropColumn(['status', 'total_bill']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'ordered_at', 'completed_at', 'checkout_at']);
        });
    }
};
