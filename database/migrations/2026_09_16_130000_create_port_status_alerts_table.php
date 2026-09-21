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
        Schema::create('port_status_alerts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedInteger('device_id');
            $blueprint->unsignedInteger('port_id');
            $blueprint->string('ifname')->nullable();
            $blueprint->string('ifdescr')->nullable();
            $blueprint->string('ifalias')->nullable();
            $blueprint->boolean('open')->default(true);
            $blueprint->timestamp('down_at');
            $blueprint->timestamp('recovered_at')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('device_id')->references('device_id')->on('devices')->onDelete('cascade');
            $blueprint->foreign('port_id')->references('port_id')->on('ports')->onDelete('cascade');
            $blueprint->index(['port_id', 'open']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_status_alerts');
    }
};
