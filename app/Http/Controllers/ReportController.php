<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Reconciliation;
use App\Services\BranchAccess;
use App\Services\CourierService;
use App\Services\Money;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function dates(Request $request): array
    {
        $data = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $data['from'] ?? BranchAccess::todayFor($request->user()->isAdmin() ? $request->integer('branch_id') : $request->user()->branch_id);
        $to = $data['to'] ?? $from;
        if ($to < $from || Carbon::parse($from)->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => __('Choose a date range of at most 366 days, ending on or after its start.')]);
        }

        return [$from, $to];
    }

    public function index(Request $request, ReportService $reports): View
    {
        [$from,$to] = $this->dates($request);
        [$branchId, $currency] = BranchAccess::financialScope($request);
        $reconciliations = Reconciliation::with(['staff', 'method'])->where('currency', $currency)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->latest('id')->get()->map(fn ($row) => ['record' => $row, 'current' => $reports->methodDay($row->date->toDateString(), $row->payment_method_id, $row->branch_id, $row->currency)]);

        return view('reports.index', ['report' => $reports->summary($from, $to, $branchId, $currency), 'from' => $from, 'to' => $to, 'reconciliations' => $reconciliations, 'methods' => PaymentMethod::all(), 'currency' => $currency, 'branchId' => $branchId]);
    }

    public function export(Request $request, ReportService $reports): StreamedResponse
    {
        [$from,$to] = $this->dates($request);
        [$branchId, $currency] = BranchAccess::financialScope($request);
        $report = $reports->summary($from, $to, $branchId, $currency);
        $type = $request->validate(['type' => 'nullable|in:payments,shipments,outstanding'])['type'] ?? 'payments';

        return response()->streamDownload(function () use ($report, $type) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            $write = function (array $row) use ($file) {
                fputcsv($file, array_map(fn ($v) => is_string($v) && preg_match('/^[\s]*[=+@\-]/u', $v) ? "'".$v : $v, $row), ',', '"', '');
            };
            if ($type === 'payments') {
                $write(array_map('__', ['Date', 'Tracking', 'Type', 'Method', 'Amount', 'Currency', 'Reference', 'Staff', 'Reason', 'Branch', 'Remark', 'Cash received', 'Change returned']));
                foreach ($report['payments'] as $p) {
                    $write([$p->payment_date->toDateString(), $p->shipment->tracking_number, $p->type, $p->method->name, Money::decimal($p->signedCents()), $p->currency, $p->reference, $p->staff->name, $p->reason, $p->branch_id ? Branch::find($p->branch_id)?->code : 'Legacy', $p->remark, $p->cash_tendered, $p->change_amount]);
                }
            } else {
                $write(array_map('__', ['Shipment date', 'Tracking', 'Customer', 'Destination', 'Status', 'Charge', 'Paid', 'Outstanding', 'Currency', 'Branch route']));
                foreach ($report[$type === 'outstanding' ? 'outstanding' : 'shipments'] as $s) {
                    $write([$s->shipment_date->toDateString(), $s->tracking_number, $s->sender['name'], $s->destination_country, $s->status, $s->price, Money::decimal($s->paidCents()), Money::decimal($s->balanceCents()), $s->currency, $s->origin_branch_id ? $s->originBranch->code.' -> '.$s->destinationBranch->code : 'Legacy']);
                }
            }
            fclose($file);
        }, "courier-$type-$from-$to.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function reconcile(Request $request, ReportService $reports, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:'.BranchAccess::todayFor($request->user()->isAdmin() ? $request->integer('branch_id') : $request->user()->branch_id), 'payment_method_id' => 'required|integer|exists:payment_methods,id', 'actual' => ['required', 'regex:/^-?\d{1,9}(\.\d{1,2})?$/D'], 'notes' => 'nullable|string|max:2000']);
        [$branchId, $currency] = BranchAccess::financialScope($request);
        if (! $branchId && in_array($currency, ['MMK', 'SGD'], true)) {
            throw ValidationException::withMessages(['branch_id' => __('Select a branch for reconciliation.')]);
        }
        DB::transaction(function () use ($data, $request, $reports, $service, $branchId, $currency) {
            $method = PaymentMethod::query()->lockForUpdate()->findOrFail($data['payment_method_id']);
            $day = $reports->methodDay($data['date'], $method->id, $branchId, $currency);
            $actual = Money::cents(ltrim($data['actual'], '-')) * (str_starts_with($data['actual'], '-') ? -1 : 1);
            if ($actual !== $day['expected'] && ! filled($data['notes'] ?? null)) {
                throw ValidationException::withMessages(['notes' => __('Explain the reconciliation variance.')]);
            }
            $row = Reconciliation::create(['date' => $data['date'], 'payment_method_id' => $method->id, 'currency' => $currency, 'branch_id' => $branchId, 'expected' => Money::decimal($day['expected']), 'actual' => Money::decimal($actual), 'ledger_version' => $day['version'], 'notes' => $data['notes'] ?? null, 'staff_id' => $request->user()->id]);
            $service->audit($request->user(), 'payments.reconciled', $row);
        }, 3);

        return back()->with('success', __('Reconciliation snapshot saved. New ledger entries will mark it for review.'));
    }
}
