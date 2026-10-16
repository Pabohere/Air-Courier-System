<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('key')->unique();
            $table->string('kind', 20);
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->json('payload');
            $table->unsignedInteger('revision')->default(1);
            $table->json('photos')->nullable();
            $table->foreignId('shipment_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('entry_drafts')->exists()) {
            throw new RuntimeException('Preserve entry drafts before removing their table.');
        }
        Schema::dropIfExists('entry_drafts');
    }
};
