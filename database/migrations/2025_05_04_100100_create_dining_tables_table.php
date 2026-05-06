<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('table_number')->unique();
            $table->string('status', 32)->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
