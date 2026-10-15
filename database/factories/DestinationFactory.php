<?php

namespace Database\Factories;

use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Destination> */
class DestinationFactory extends Factory
{
    public function definition(): array
    {
        return ['country' => fake()->unique()->country(), 'active' => true];
    }
}
