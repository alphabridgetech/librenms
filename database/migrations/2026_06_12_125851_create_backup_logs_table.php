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
        Schema::create('backup_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedInteger('user_id')->nullable();
            $blueprint->string('action'); // 'create', 'delete', 'download'
            $blueprint->string('filename');
            $blueprint->string('destination')->nullable();
            $blueprint->string('status'); // 'success', 'error'
            $blueprint->text('message')->nullable();
            $blueprint->timestamps();

            $blueprint->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
