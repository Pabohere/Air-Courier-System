<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Destination;
use App\Models\PaymentMethod;
use App\Models\Rate;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is only permitted in local and testing environments.');
        }
        $this->call(DatabaseSeeder::class);
        if (User::where('email', 'admin@courier.test')->exists()) {
            return;
        }
        DB::transaction(function () {
            $admin = User::create(['name' => 'Branch Admin', 'email' => 'admin@courier.test', 'password' => 'CourierDemo2026!', 'role' => 'admin', 'active' => true]);
            $staff = User::create(['branch_id' => Branch::where('code', 'YGN')->value('id'), 'name' => 'Operations Staff', 'email' => 'staff@courier.test', 'password' => 'CourierDemo2026!', 'role' => 'staff', 'active' => true]);
            $countries = ['Singapore' => ['5.00', '12.00', '15.00'], 'Thailand' => ['3.00', '8.00', '10.00'], 'Japan' => ['8.00', '18.00', '25.00'], 'United Kingdom' => ['10.00', '24.00', '30.00']];
            foreach ($countries as $country => $r) {
                $d = Destination::firstOrCreate(['country' => $country], ['active' => true]);
                Rate::firstOrCreate(['destination_id' => $d->id], ['base_charge' => $r[0], 'per_weight' => $r[1], 'minimum_charge' => $r[2], 'additional_charge' => '2.00']);
            }
            $customers = [];
            foreach (['Avery Lane', 'Morgan Reed', 'Jamie Park', 'Taylor Chen', 'Alex Rivers', 'Casey Lin'] as $index => $name) {
                $customers[] = Customer::create(['name' => $name, 'phone' => '+95 000 100 '.str_pad((string) $index, 3, '0', STR_PAD_LEFT), 'address' => 'Sample customer address '.($index + 1).', Yangon', 'notes' => 'Fictional sample record for local testing.', 'created_by' => $staff->id, 'updated_by' => $staff->id]);
            }
            $service = app(CourierService::class);
            $destinations = Destination::where('active', true)->get();
            $method = PaymentMethod::firstOrFail();
            for ($i = 0; $i < 10; $i++) {
                $s = $service->book(['booking_key' => (string) Str::uuid(), 'customer_id' => $customers[$i % 6]->id, 'destination_id' => $destinations[$i % 4]->id, 'recipient_name' => 'Demo Recipient '.($i + 1), 'recipient_phone' => '+00 000 000 '.($i + 1), 'recipient_address' => 'Sample recipient address', 'shipment_date' => today()->subDays($i < 6 ? 0 : ($i - 5))->toDateString(), 'description' => ['Documents', 'Clothing and gifts', 'Household items'][$i % 3], 'quantity' => ($i % 3) + 1, 'actual_weight' => ['1.250', '2.500', '0.500', '3.000'][$i % 4]], $staff);
                if ($i % 3 !== 2) {
                    $price = Money::cents($s->price);
                    $amount = $i % 3 === 0 ? intdiv($price, 2) : $price;
                    $service->recordPayment($s, ['amount' => Money::decimal($amount), 'payment_method_id' => $method->id, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid(), 'reference' => 'DEMO-'.($i + 1)], $staff);
                }
                if ($i < 7) {
                    $service->transition($s, 'Received', 'Parcel received at branch.', $staff);
                }if ($i < 5) {
                    $service->transition($s, 'Processing', null, $staff);
                }if ($i < 3) {
                    $service->transition($s, 'Ready for Dispatch', null, $staff);
                }
            }
            $ready = Shipment::where('status', 'Ready for Dispatch')->limit(2)->pluck('id')->all();
            $dispatch = $service->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Sample Air Partner', 'dispatch_date' => today()->toDateString(), 'flight' => 'DEMO 101', 'awb' => 'SAMPLE-AWB-001', 'shipments' => $ready, 'notes' => 'Fictional sample dispatch.'], $admin);
            $service->handover($dispatch, $admin);
        });
    }
}
