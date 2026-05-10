<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->unsignedBigInteger('floor_id')->nullable()->after('id');
            $table->string('table_name', 128)->nullable()->after('table_number');
            $table->string('shape', 16)->default('square')->after('table_name');
            $table->decimal('x_position', 10, 2)->default(0)->after('shape');
            $table->decimal('y_position', 10, 2)->default(0)->after('x_position');
            $table->decimal('width', 10, 2)->default(120)->after('y_position');
            $table->decimal('height', 10, 2)->default(80)->after('width');
            $table->decimal('scale_x', 10, 4)->default(1)->after('height');
            $table->decimal('scale_y', 10, 4)->default(1)->after('scale_x');
            $table->decimal('rotation', 10, 4)->default(0)->after('scale_y');
            $table->string('fill_color', 32)->nullable()->after('rotation');
            $table->unsignedSmallInteger('seat_capacity')->nullable()->after('fill_color');
        });

        foreach (DB::table('dining_tables')->orderBy('id')->cursor() as $row) {
            DB::table('dining_tables')->where('id', $row->id)->update([
                'table_name' => 'Table '.$row->table_number,
                'shape' => 'square',
                'x_position' => 40 + (($row->id - 1) % 8) * 140 + 60,
                'y_position' => 40 + intdiv($row->id - 1, 8) * 120 + 40,
                'width' => 120,
                'height' => 80,
                'scale_x' => 1,
                'scale_y' => 1,
                'rotation' => 0,
                'fill_color' => null,
                'seat_capacity' => 4,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('dining_tables', function (Blueprint $table) {
            $table->dropColumn([
                'floor_id',
                'table_name',
                'shape',
                'x_position',
                'y_position',
                'width',
                'height',
                'scale_x',
                'scale_y',
                'rotation',
                'fill_color',
                'seat_capacity',
            ]);
        });
    }
};
