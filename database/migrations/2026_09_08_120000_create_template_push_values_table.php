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
        Schema::create('template_push_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('device_id');
            $table->string('template_name');
            $table->string('template_folder')->default('');
            $table->longText('field_values')->nullable();
            $table->string('port_mode')->nullable();
            $table->unsignedInteger('pvid')->nullable();
            $table->text('custom_commands')->nullable();
            $table->longText('selected_interfaces')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'template_name', 'template_folder'], 'tpv_device_template_unique');
            $table->foreign('device_id')->references('device_id')->on('devices')->onDelete('cascade');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_push_values');
    }
};
