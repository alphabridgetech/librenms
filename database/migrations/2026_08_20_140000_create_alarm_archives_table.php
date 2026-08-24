<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('alarm_archives')) {
            Schema::create('alarm_archives', function (Blueprint $table) {
                $table->id();
                $table->string('filename');
                $table->string('file_path');
                $table->string('file_size')->nullable();
                $table->integer('line_count')->default(0);
                $table->dateTime('start_date')->nullable();
                $table->dateTime('end_date')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_archives');
    }
};
