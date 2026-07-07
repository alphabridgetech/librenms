<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ztp_devices', function (Blueprint $table) {
            $table->id();
            $table->string('mac_address', 17)->unique()->comment('Switch MAC address e.g. aa:bb:cc:dd:ee:ff');
            $table->string('device_name', 255)->comment('Hostname / friendly name to assign to the device');
            $table->string('ip_address', 45)->nullable()->comment('Static IP to assign during provisioning');
            $table->string('subnet_mask', 45)->nullable()->default('255.255.255.0');
            $table->string('gateway', 45)->nullable()->comment('Default gateway for the device');
            $table->string('template_name', 255)->nullable()->comment('Template file to use for config generation');
            $table->string('template_folder', 255)->nullable();
            $table->text('template_commands')->nullable()->comment('Raw CLI commands (fallback if no template)');
            $table->enum('status', ['pending', 'provisioned', 'failed'])->default('pending');
            $table->timestamp('last_seen_at')->nullable()->comment('When device last requested config');
            $table->timestamp('provisioned_at')->nullable()->comment('When provisioning was completed');
            $table->text('notes')->nullable()->comment('Admin notes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ztp_devices');
    }
};
