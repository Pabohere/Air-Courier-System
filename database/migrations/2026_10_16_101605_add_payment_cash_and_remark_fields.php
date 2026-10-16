<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('cash_tendered', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('remark', 150)->nullable();
        });
        foreach (['Cash', 'Bank transfer', 'MMQR', 'Pay'] as $name) {
            if (! DB::table('payment_methods')->where('name', $name)->exists()) {
                DB::table('payment_methods')->insert(['name' => $name, 'active' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['cash_tendered', 'change_amount', 'remark']);
        });
    }
};
