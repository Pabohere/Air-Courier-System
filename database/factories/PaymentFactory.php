<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['shipment_id' => Shipment::factory(), 'payment_method_id' => PaymentMethod::factory(), 'type' => 'payment', 'amount' => '5.00', 'currency' => 'USD', 'payment_date' => today(), 'submission_key' => (string) Str::uuid(), 'staff_id' => User::factory()];
    }
}
