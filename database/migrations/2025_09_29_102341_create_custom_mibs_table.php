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
        Schema::create('custom_mibs', function (Blueprint $table) {
        $table->id();
        $table->string('filename');
        $table->string('model_name')->nullable();
        $table->string('path');         
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_mibs');
    }
};
