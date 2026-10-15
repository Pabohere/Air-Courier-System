<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::firstOrCreate(['id' => 1], ['name' => 'Air Courier', 'currency' => 'USD', 'weight_unit' => 'kg', 'receipt_footer' => 'Thank you for choosing Air Courier. Please keep this receipt for your records.']);
        foreach (['Cash', 'Bank transfer'] as $name) {
            PaymentMethod::firstOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
