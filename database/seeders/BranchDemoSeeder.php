<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Branch demo data is only permitted in local and testing environments.');
        }
        $this->call(DatabaseSeeder::class);
        DB::transaction(function (): void {
            $ygn = Branch::where('code', 'YGN')->firstOrFail();
            $sg = Branch::where('code', 'SG')->firstOrFail();
            $staff = [
                $ygn->id => User::firstOrCreate(['email' => 'ygn@courier.test'], ['name' => 'YGN Demo Staff', 'branch_id' => $ygn->id, 'role' => 'staff', 'active' => true, 'password' => 'CourierDemo2026!']),
                $sg->id => User::firstOrCreate(['email' => 'sg@courier.test'], ['name' => 'SG Demo Staff', 'branch_id' => $sg->id, 'role' => 'staff', 'active' => true, 'password' => 'CourierDemo2026!']),
            ];
            $customer = Customer::firstOrCreate(['name' => 'Branch workflow sample customer'], ['phone' => '+00 000 000', 'address' => 'Fictional demo address', 'notes' => 'Local demonstration only.', 'created_by' => $staff[$ygn->id]->id, 'updated_by' => $staff[$ygn->id]->id]);
            $service = app(CourierService::class);
            foreach ([[$ygn, $sg, 'SGD', '25.00', 'stored'], [$sg, $ygn, 'MMK', '30000.00', 'incoming'], [$ygn, $sg, 'MMK', '25000.00', 'outgoing'], [$sg, $ygn, 'SGD', '35.00', 'collected']] as [$origin, $destination, $currency, $price, $stage]) {
                $description = 'Fictional branch demo: '.$origin->code.'-'.$destination->code.' '.$stage;
                if (Shipment::where('description', $description)->exists()) {
                    continue;
                }
                $originStaff = $staff[$origin->id];
                $destinationStaff = $staff[$destination->id];
                $shipment = $service->book(['booking_key' => (string) Str::uuid(), 'customer_id' => $customer->id, 'origin_branch_id' => $origin->id, 'destination_branch_id' => $destination->id, 'currency' => $currency, 'price' => $price, 'recipient_name' => 'Demo recipient at '.$destination->code, 'recipient_phone' => '+00 000 001', 'recipient_address' => 'Fictional recipient address', 'shipment_date' => today()->toDateString(), 'description' => $description, 'quantity' => 1, 'actual_weight' => '2.500'], $originStaff);
                $service->transition($shipment, 'Processing', 'Fictional demonstration.', $originStaff);
                if ($stage === 'outgoing') {
                    continue;
                }
                $service->transition($shipment, 'Ready for Dispatch', null, $originStaff);
                $dispatch = $service->createDispatch(['carrier_name' => 'Sample parcel carrier', 'partner' => 'Fictional demo airline', 'flight' => 'DEMO '.$origin->code.'-'.$destination->code, 'dispatch_date' => now($origin->timezone)->toDateString(), 'flight_departure_at' => now($origin->timezone)->format('Y-m-d\TH:i'), 'notes' => 'Fictional sample flight, not a real airline schedule.', 'shipments' => [$shipment->id]], $originStaff);
                $service->handover($dispatch, $originStaff);
                if ($stage === 'incoming') {
                    continue;
                }
                $service->transition($shipment, 'Arrived at Branch', 'Sample receipt at destination branch.', $destinationStaff);
                $service->transition($shipment, 'Ready for Collection', null, $destinationStaff);
                if ($stage === 'collected') {
                    $service->recordPayment($shipment, ['amount' => $price, 'payment_method_id' => PaymentMethod::firstOrFail()->id, 'payment_date' => today()->toDateString(), 'submission_key' => (string) Str::uuid(), 'reference' => 'FICTIONAL-DEMO'], $destinationStaff);
                    $service->transition($shipment, 'Collected', null, $destinationStaff, ['collector_name' => 'Demo recipient', 'collection_reference' => 'Fictional signed collection']);
                }
            }
        });
    }
}
