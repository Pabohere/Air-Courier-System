<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            $table->boolean('in_storage')->default(false);
            $table->string('storage_location', 100)->nullable();
            $table->dateTime('stored_at')->nullable();
            $table->index(['current_branch_id', 'in_storage']);
        });
        Schema::create('storage_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('from_location', 100)->nullable();
            $table->string('to_location', 100)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('weight', 8, 3);
            $table->foreignId('staff_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->index(['branch_id', 'occurred_at']);
        });
        $snapshotAt = now();
        DB::table('shipments')->whereNotNull('origin_branch_id')->whereNotNull('current_branch_id')
            ->whereIn('status', ['Received', 'Processing', 'Ready for Dispatch', 'Arrived at Branch', 'Ready for Collection', 'Exception', 'Cancelled'])
            ->orderBy('id')->chunkById(100, function ($shipments) use ($snapshotAt): void {
                foreach ($shipments as $shipment) {
                    DB::table('shipments')->where('id', $shipment->id)->update(['in_storage' => true, 'storage_location' => 'Unassigned storage', 'stored_at' => $snapshotAt]);
                    DB::table('storage_movements')->insert(['shipment_id' => $shipment->id, 'branch_id' => $shipment->current_branch_id, 'type' => 'Opening balance', 'to_location' => 'Unassigned storage', 'quantity' => $shipment->quantity, 'weight' => $shipment->actual_weight, 'staff_id' => null, 'notes' => 'Opening snapshot from the recorded parcel location. Original storage receipt time is unknown.', 'occurred_at' => $snapshotAt]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_movements');
        Schema::table('shipments', function (Blueprint $table): void {
            $table->dropIndex(['current_branch_id', 'in_storage']);
            $table->dropColumn(['in_storage', 'storage_location', 'stored_at']);
        });
    }
};
