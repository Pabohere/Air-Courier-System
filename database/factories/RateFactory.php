<?php

namespace Database\Factories;

use App\Models\Destination;
use App\Models\Rate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rate> */
class RateFactory extends Factory
{
    public function definition(): array
    {
        return ['destination_id' => Destination::factory(), 'base_charge' => '5.00', 'per_weight' => '12.00', 'minimum_charge' => '15.00', 'additional_charge' => '2.00'];
    }
}
