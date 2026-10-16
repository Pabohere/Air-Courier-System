<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ORIGINAL = ['Booked', 'Received', 'Processing', 'Ready for Dispatch', 'Dispatched', 'In Transit', 'Delivered', 'Cancelled', 'Exception'];

    private const BRANCH = ['Arrived at Branch', 'Ready for Collection', 'Collected'];

    public function up(): void
    {
        Schema::table('shipments', fn (Blueprint $table) => $table->enum('status', array_merge(self::ORIGINAL, self::BRANCH))->default('Booked')->change());
    }

    public function down(): void
    {
        if (DB::table('shipments')->whereIn('status', self::BRANCH)->exists()) {
            throw new RuntimeException('Cannot remove branch statuses while shipments use them. Preserve these records and use a forward migration.');
        }
        Schema::table('shipments', fn (Blueprint $table) => $table->enum('status', self::ORIGINAL)->default('Booked')->change());
    }
};
