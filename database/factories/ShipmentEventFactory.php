<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShipmentEvent> */
class ShipmentEventFactory extends Factory
{
    public function definition(): array
    {
        return ['shipment_id' => Shipment::factory(), 'status' => 'Booked', 'staff_id' => User::factory()];
    }
}
