<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function payments(string $from, string $to, ?int $branchId = null, ?string $currency = null): Collection
    {
        return Payment::with(['method', 'shipment', 'staff'])->where('currency', $currency ?? CompanySetting::firstOrFail()->currency)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->orderBy('payment_date')->orderBy('id')->get();
    }

    public function summary(string $from, string $to, ?int $branchId = null, ?string $currency = null): array
    {
        return DB::transaction(function () use ($from, $to, $branchId, $currency): array {
            $currency ??= CompanySetting::firstOrFail()->currency;
            $shipments = Shipment::with(['payments', 'originBranch', 'destinationBranch'])->where('currency', $currency)->when($branchId, fn ($q) => $q->where('origin_branch_id', $branchId))->whereDate('shipment_date', '>=', $from)->whereDate('shipment_date', '<=', $to)->get();
            $payments = $this->payments($from, $to, $branchId, $currency);
            $outstanding = Shipment::with(['payments', 'originBranch', 'destinationBranch'])->where('currency', $currency)->when($branchId, fn ($q) => $q->where('origin_branch_id', $branchId))->get()->filter(fn ($s) => $s->balanceCents() > 0);
            $received = $payments->where('type', 'payment')->sum(fn ($p) => Money::cents($p->amount));
            $cashTendered = $payments->where('type', 'payment')->filter(fn ($p) => $p->method->name === 'Cash')->sum(fn ($p) => Money::cents($p->cash_tendered ?? $p->amount));
            $change = $payments->where('type', 'payment')->sum(fn ($p) => Money::cents($p->change_amount));
            $refunds = $payments->where('type', 'refund')->sum(fn ($p) => Money::cents($p->amount));
            $methods = $payments->groupBy('payment_method_id')->map(fn ($rows) => ['name' => $rows->first()->method->name, 'received' => $rows->where('type', 'payment')->sum(fn ($p) => Money::cents($p->amount)), 'refunds' => $rows->where('type', 'refund')->sum(fn ($p) => Money::cents($p->amount)), 'net' => $rows->sum(fn ($p) => $p->signedCents()), 'version' => $rows->max('id')]);

            return compact('shipments', 'payments', 'outstanding', 'received', 'refunds', 'methods', 'cashTendered', 'change') + ['charges' => $shipments->where('status', '!=', 'Cancelled')->sum(fn ($s) => Money::cents($s->price)), 'cancelled' => $shipments->where('status', 'Cancelled')->count(), 'net' => $received - $refunds];
        });
    }

    public function methodDay(string $date, int $method, ?int $branchId = null, ?string $currency = null): array
    {
        $rows = Payment::where('currency', $currency ?? CompanySetting::firstOrFail()->currency)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->whereDate('payment_date', $date)->where('payment_method_id', $method)->get();

        return ['expected' => $rows->sum(fn ($p) => $p->signedCents()), 'version' => $rows->max('id') ?? 0];
    }
}
