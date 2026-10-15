<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchWorkflowDemoSeeder extends Seeder
{
    public const STAGES = ['Received', 'Processing', 'Ready for Dispatch', 'Draft', 'Dispatched', 'In Transit', 'Arrived at Branch', 'Ready for Collection', 'Collected', 'Cancelled', 'Returned to sender', 'Exception'];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Workflow demo data is only permitted in local and testing environments.');
        }
        $this->call(DatabaseSeeder::class);
        DB::transaction(function (): void {
            $courier = app(CourierService::class);
            $inventory = app(InventoryService::class);
            $branches = Branch::whereIn('code', ['YGN', 'SG'])->get()->keyBy('code');
            $staff = [];
            foreach ($branches as $code => $branch) {
                $staff[$code] = User::firstOrCreate(['email' => strtolower($code).'@courier.test'], ['name' => $code.' Demo Staff', 'branch_id' => $branch->id, 'password' => 'CourierDemo2026!', 'role' => 'staff', 'active' => true]);
            }
            foreach (['YGN', 'SG'] as $routeIndex => $code) {
                $origin = $branches[$code];
                $destination = $branches[$code === 'YGN' ? 'SG' : 'YGN'];
                $sender = $staff[$code];
                $receiver = $staff[$destination->code];
                $customer = Customer::firstOrCreate(['phone' => 'DEMO-WF-'.$code], ['name' => '[DEMO] '.$code.' workflow customer', 'address' => 'Fictional demo address', 'notes' => 'Fictional workflow fixtures v1; no real customer data.', 'created_by' => $sender->id, 'updated_by' => $sender->id]);
                foreach (self::STAGES as $index => $stage) {
                    $key = sprintf('de000000-0000-4000-8000-%012d', $routeIndex * 100 + $index + 1);
                    if (Shipment::where('booking_key', $key)->exists()) {
                        continue;
                    }
                    $date = now($origin->timezone)->toDateString();
                    $price = $code === 'YGN' ? '25000.00' : '25.00';
                    $shipment = $courier->book(['booking_key' => $key, 'customer_id' => $customer->id, 'origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'recipient_name' => '[DEMO] '.$destination->code.' recipient '.$index, 'recipient_phone' => 'DEMO-RECIPIENT', 'recipient_address' => 'Fictional recipient address', 'shipment_date' => $date, 'description' => '[DEMO-WF-v1] '.$code.' → '.$destination->code.' / '.$stage, 'quantity' => 2, 'actual_weight' => '2.500', 'currency' => $code === 'YGN' ? 'MMK' : 'SGD', 'price' => $price, 'storage_location' => $code.' / Rack A / Shelf '.($index + 1)], $sender);
                    if ($index < 9) {
                        $courier->recordPayment($shipment, ['amount' => $code === 'YGN' ? '10000.00' : '10.00', 'payment_method_id' => PaymentMethod::where('name', $index % 2 ? 'Bank transfer' : 'Cash')->value('id'), 'payment_date' => $date, 'submission_key' => (string) Str::uuid(), 'reference' => 'DEMO-WF-DEPOSIT-'.$code.'-'.$index], $sender);
                    }
                    if (in_array($stage, ['Cancelled', 'Returned to sender', 'Exception'], true)) {
                        $courier->transition($shipment, $stage === 'Exception' ? 'Exception' : 'Cancelled', 'Fictional demo: '.$stage, $sender);
                        if ($stage === 'Returned to sender') {
                            $inventory->release($shipment, $sender, $stage, 'Fictional demo: returned at counter to sender');
                        }

                        continue;
                    }
                    if ($index === 0) {
                        continue;
                    }
                    $courier->transition($shipment, 'Processing', null, $sender);
                    if ($index === 1) {
                        continue;
                    }
                    $courier->transition($shipment, 'Ready for Dispatch', null, $sender);
                    if ($index === 2) {
                        continue;
                    }
                    $dispatch = $courier->createDispatch(['carrier_name' => '[DEMO] Carrier '.$code.' '.$index, 'partner' => 'Fictional demo airline', 'dispatch_date' => $date, 'flight' => 'DEMO-'.$code.'-'.$index, 'flight_departure_at' => $date.'T14:00', 'shipments' => [$shipment->id], 'notes' => 'Fictional workflow demonstration; not a real flight.'], $sender);
                    if ($stage === 'Draft') {
                        continue;
                    }
                    $courier->handover($dispatch, $sender);
                    if ($stage === 'Dispatched') {
                        continue;
                    }
                    $courier->transition($shipment, 'In Transit', null, $sender);
                    if ($stage === 'In Transit') {
                        continue;
                    }
                    $courier->transition($shipment, 'Arrived at Branch', null, $receiver);
                    $inventory->move($shipment, $receiver, $destination->code.' / Collection rack / '.$index, 'Fictional destination storage check');
                    if ($stage === 'Arrived at Branch') {
                        continue;
                    }
                    $courier->transition($shipment, 'Ready for Collection', null, $receiver);
                    if ($stage === 'Collected') {
                        $courier->recordPayment($shipment, ['amount' => $code === 'YGN' ? '15000.00' : '15.00', 'payment_method_id' => PaymentMethod::where('name', 'Cash')->value('id'), 'payment_date' => now($destination->timezone)->toDateString(), 'submission_key' => (string) Str::uuid(), 'reference' => 'DEMO-WF-BALANCE-'.$code], $receiver);
                        $courier->transition($shipment, 'Collected', 'Fictional counter collection', $receiver, ['collector_name' => '[DEMO] Recipient '.$destination->code]);
                    }
                }
            }
        });
    }
}
