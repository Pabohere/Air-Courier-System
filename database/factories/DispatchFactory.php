<?php

namespace Database\Factories;

use App\Models\Dispatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Dispatch> */
class DispatchFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => 'DSP-'.Str::random(12), 'partner' => 'Test Partner', 'dispatch_date' => today(), 'created_by' => User::factory()];
    }
}
