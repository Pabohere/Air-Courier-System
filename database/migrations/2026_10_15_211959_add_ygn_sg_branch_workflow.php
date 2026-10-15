<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('country');
            $table->string('timezone');
        });
        DB::table('branches')->insert([
            ['code' => 'YGN', 'name' => 'Yangon branch', 'country' => 'Myanmar', 'timezone' => 'Asia/Yangon'],
            ['code' => 'SG', 'name' => 'Singapore branch', 'country' => 'Singapore', 'timezone' => 'Asia/Singapore'],
        ]);
        Schema::table('users', fn (Blueprint $table) => $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete());
        DB::table('users')->where('role', 'staff')->update(['branch_id' => DB::table('branches')->where('code', 'YGN')->value('id')]);
        foreach (['shipments', 'dispatches'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('origin_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
                $table->foreignId('destination_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            });
        }
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('current_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->string('collector_name', 150)->nullable();
            $table->string('collection_reference', 150)->nullable();
        });
        Schema::table('dispatches', fn (Blueprint $table) => $table->dateTime('flight_departure_at')->nullable());
        foreach (['payments', 'reconciliations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete());
        }
    }

    public function down(): void
    {
        foreach (['payments', 'reconciliations', 'users'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['current_branch_id']);
            $table->dropColumn(['current_branch_id', 'arrived_at', 'collected_at', 'collector_name', 'collection_reference']);
        });
        Schema::table('dispatches', fn (Blueprint $table) => $table->dropColumn('flight_departure_at'));
        foreach (['shipments', 'dispatches'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['origin_branch_id']);
                $table->dropForeign(['destination_branch_id']);
                $table->dropColumn(['origin_branch_id', 'destination_branch_id']);
            });
        }
        Schema::dropIfExists('branches');
    }
};
