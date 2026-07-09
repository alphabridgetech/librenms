<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ztp_devices', 'snmp_community')) {
            Schema::table('ztp_devices', function (Blueprint $table) {
                $table->string('snmp_community', 100)->nullable()->default('public')
                      ->after('gateway')
                      ->comment('SNMP v2c community string for LibreNMS auto-discovery');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ztp_devices', 'snmp_community')) {
            Schema::table('ztp_devices', function (Blueprint $table) {
                $table->dropColumn('snmp_community');
            });
        }
    }
};
