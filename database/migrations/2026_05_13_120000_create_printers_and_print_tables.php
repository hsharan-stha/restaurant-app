<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('connection_type', 32);
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(9100);
            $table->string('paper_width', 8)->default('80');
            $table->string('role', 32);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('auto_print_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('print_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_print_kitchen')->default(false);
            $table->boolean('auto_print_cashier')->default(false);
            $table->foreignId('kitchen_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->foreignId('cashier_printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('printer_id')->nullable()->constrained('printers')->nullOnDelete();
            $table->string('print_type', 32);
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->json('order_item_ids')->nullable();
            $table->unsignedInteger('bytes_sent')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('kitchen_printed_at')->nullable()->after('is_delivered');
            $table->timestamp('cashier_printed_at')->nullable()->after('kitchen_printed_at');
        });

        if (! DB::table('print_settings')->exists()) {
            DB::table('print_settings')->insert([
                'auto_print_kitchen' => false,
                'auto_print_cashier' => false,
                'kitchen_printer_id' => null,
                'cashier_printer_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['kitchen_printed_at', 'cashier_printed_at']);
        });

        Schema::dropIfExists('print_logs');
        Schema::dropIfExists('print_settings');
        Schema::dropIfExists('printers');
    }
};
