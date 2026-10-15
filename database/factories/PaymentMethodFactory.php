<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'active' => true];
    }
}
