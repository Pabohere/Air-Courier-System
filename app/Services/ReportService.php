<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function payments(string $from, string $to): Collection
    {
        return Payment::with(['method', 'shipment', 'staff'])->whereDate('payment_date', '>=', $from)->whereDate('payment_date', '<=', $to)->orderBy('payment_date')->orderBy('id')->get();
    }

    public function summary(string $from, string $to): array
    {
        return DB::transaction(function () use ($from, $to): array {
            $shipments = Shipment::with('payments')->whereDate('shipment_date', '>=', $from)->whereDate('shipment_date', '<=', $to)->get();
            $payments = $this->payments($from, $to);
            $outstanding = Shipment::with('payments')->get()->filter(fn ($s) => $s->balanceCents() > 0);
            $received = $payments->where('type', 'payment')->sum(fn ($p) => Money::cents($p->amount));
            $refunds = $payments->where('type', 'refund')->sum(fn ($p) => Money::cents($p->amount));
            $methods = $payments->groupBy('payment_method_id')->map(fn ($rows) => ['name' => $rows->first()->method->name, 'received' => $rows->where('type', 'payment')->sum(fn ($p) => Money::cents($p->amount)), 'refunds' => $rows->where('type', 'refund')->sum(fn ($p) => Money::cents($p->amount)), 'net' => $rows->sum(fn ($p) => $p->signedCents()), 'version' => $rows->max('id')]);

            return compact('shipments', 'payments', 'outstanding', 'received', 'refunds', 'methods') + ['charges' => $shipments->where('status', '!=', 'Cancelled')->sum(fn ($s) => Money::cents($s->price)), 'cancelled' => $shipments->where('status', 'Cancelled')->count(), 'net' => $received - $refunds];
        });
    }

    public function methodDay(string $date, int $method): array
    {
        $rows = Payment::whereDate('payment_date', $date)->where('payment_method_id', $method)->get();

        return ['expected' => $rows->sum(fn ($p) => $p->signedCents()), 'version' => $rows->max('id') ?? 0];
    }
}
