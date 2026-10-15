<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['staff_id' => User::factory(), 'action' => 'test.action', 'subject_type' => 'Shipment', 'subject_id' => 1, 'after' => []];
    }
}
