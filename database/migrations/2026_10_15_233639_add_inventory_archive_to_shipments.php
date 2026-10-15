<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dateTime('inventory_archived_at')->nullable();
            $table->text('inventory_archive_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropColumn(['inventory_archived_at', 'inventory_archive_reason']);
        });
    }
};
