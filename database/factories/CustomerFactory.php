<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'phone' => fake()->phoneNumber(), 'address' => fake()->address(), 'notes' => null, 'created_by' => User::factory(), 'updated_by' => fn (array $attributes) => $attributes['created_by']];
    }
}
