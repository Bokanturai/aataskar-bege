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
        Schema::table('agent_services', function (Blueprint $table) {
            $table->index('status');
            $table->index('number');
            $table->index('batch_id');
            $table->index('ticket_id');
            $table->index('service_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_services', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['number']);
            $table->dropIndex(['batch_id']);
            $table->dropIndex(['ticket_id']);
            $table->dropIndex(['service_name']);
        });
    }
};
