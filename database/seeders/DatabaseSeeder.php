<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::firstOrCreate(['id' => 1], ['name' => 'Standard', 'currency' => 'USD', 'weight_unit' => 'kg', 'receipt_footer' => 'Thank you for choosing Standard. Please keep this receipt for your records.']);
        foreach (['Cash', 'Bank transfer', 'MMQR', 'Pay'] as $name) {
            PaymentMethod::firstOrCreate(['name' => $name], ['active' => true]);
        }
    }
}
