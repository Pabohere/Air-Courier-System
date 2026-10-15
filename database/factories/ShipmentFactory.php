<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Destination;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return ['tracking_number' => 'TEST-'.strtoupper(Str::random(12)), 'booking_key' => (string) Str::uuid(), 'customer_id' => Customer::factory(), 'destination_id' => Destination::factory(), 'sender' => ['name' => 'Test Sender', 'phone' => '000', 'address' => 'Test address'], 'recipient_name' => 'Test Recipient', 'recipient_phone' => '000', 'recipient_address' => 'Recipient address', 'destination_country' => 'Singapore', 'shipment_date' => today(), 'description' => 'Documents', 'quantity' => 1, 'actual_weight' => '1.000', 'weight_unit' => 'kg', 'currency' => 'USD', 'rate_snapshot' => ['base_charge' => '5.00', 'per_weight' => '12.00', 'minimum_charge' => '15.00', 'additional_charge' => '2.00'], 'price' => '19.00', 'status' => 'Booked', 'created_by' => User::factory(), 'updated_by' => fn (array $a) => $a['created_by']];
    }
}
