<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin', 'staff'])->default('staff');
            $t->boolean('active')->default(true);
        });
        Schema::create('company_settings', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->text('receipt_footer')->nullable();
            $t->char('currency', 3)->default('USD');
            $t->enum('weight_unit', ['kg', 'lb'])->default('kg');
            $t->timestamps();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('phone', 50)->index();
            $t->text('address');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('destinations', function (Blueprint $t) {
            $t->id();
            $t->string('country')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('destination_id')->unique()->constrained()->restrictOnDelete();
            $t->decimal('base_charge', 12, 2);
            $t->decimal('per_weight', 12, 2);
            $t->decimal('minimum_charge', 12, 2);
            $t->decimal('additional_charge', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('payment_methods', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('dispatches', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('partner');
            $t->date('dispatch_date')->index();
            $t->string('flight')->nullable();
            $t->string('awb')->nullable();
            $t->text('notes')->nullable();
            $t->timestamp('handed_over_at')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('handed_over_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('shipments', function (Blueprint $t) {
            $t->id();
            $t->string('tracking_number')->unique();
            $t->uuid('booking_key')->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('destination_id')->constrained()->restrictOnDelete();
            $t->foreignId('dispatch_id')->nullable()->constrained()->restrictOnDelete();
            $t->json('sender');
            $t->string('recipient_name');
            $t->string('recipient_phone', 50);
            $t->text('recipient_address');
            $t->string('destination_country');
            $t->date('shipment_date')->index();
            $t->text('description');
            $t->unsignedInteger('quantity');
            $t->decimal('actual_weight', 10, 3);
            $t->string('weight_unit', 2);
            $t->char('currency', 3);
            $t->json('rate_snapshot');
            $t->decimal('price', 12, 2);
            $t->enum('status', ['Booked', 'Received', 'Processing', 'Ready for Dispatch', 'Dispatched', 'In Transit', 'Delivered', 'Cancelled', 'Exception'])->default('Booked')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('shipment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $t->string('from_status')->nullable();
            $t->string('status');
            $t->text('notes')->nullable();
            $t->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $t->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $t->foreignId('original_payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $t->enum('type', ['payment', 'refund']);
            $t->decimal('amount', 12, 2);
            $t->char('currency', 3);
            $t->date('payment_date')->index();
            $t->string('reference')->nullable();
            $t->text('reason')->nullable();
            $t->uuid('submission_key')->unique();
            $t->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('reconciliations', function (Blueprint $t) {
            $t->id();
            $t->date('date')->index();
            $t->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $t->char('currency', 3);
            $t->decimal('expected', 14, 2);
            $t->decimal('actual', 14, 2);
            $t->unsignedBigInteger('ledger_version')->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'reconciliations', 'payments', 'shipment_events', 'shipments', 'dispatches', 'payment_methods', 'rates', 'destinations', 'customers', 'company_settings'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active']));
    }
};
