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
        Schema::create('config_backup_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedInteger('device_id');
            $blueprint->unsignedInteger('user_id')->nullable();
            $blueprint->string('filename');
            $blueprint->string('tftp_server')->nullable();
            $blueprint->string('status'); // 'success', 'error'
            $blueprint->text('message')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('device_id')->references('device_id')->on('devices')->onDelete('cascade');
            $blueprint->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('config_backup_logs');
    }
};
