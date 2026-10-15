<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\Reconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reconciliation> */
class ReconciliationFactory extends Factory
{
    public function definition(): array
    {
        return ['date' => today(), 'payment_method_id' => PaymentMethod::factory(), 'currency' => 'USD', 'expected' => '0.00', 'actual' => '0.00', 'staff_id' => User::factory()];
    }
}
