<?php

namespace Database\Factories;

use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CompanySetting> */
class CompanySettingFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'Test Courier', 'currency' => 'USD', 'weight_unit' => 'kg'];
    }
}
