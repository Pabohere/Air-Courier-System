<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanySetting;
use App\Models\Destination;
use App\Models\PaymentMethod;
use App\Models\Rate;
use App\Models\Shipment;
use App\Models\User;
use App\Services\CourierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', ['settings' => CompanySetting::firstOrFail(), 'destinations' => Destination::with('rate')->orderBy('country')->get(), 'methods' => PaymentMethod::all(), 'users' => User::orderBy('name')->get()]);
    }

    public function company(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:2000', 'receipt_footer' => 'nullable|string|max:2000', 'currency' => ['required', 'regex:/^[A-Z]{3}
$/D'], 'weight_unit' => 'required|in:kg,lb']);
        DB::transaction(function () use ($data, $request, $service) {
            $settings = CompanySetting::query()->lockForUpdate()->firstOrFail();
            if (Shipment::exists() && ($settings->currency !== $data['currency'] || $settings->weight_unit !== $data['weight_unit'])) {
                throw ValidationException::withMessages(['currency' => 'Currency and weight unit are locked after the first booking to preserve consistent records.']);
            }
            $before = $settings->toArray();
            $settings->update($data);
            $service->audit($request->user(), 'settings.company', $settings, $before);
        }, 3);

        return back()->with('success', 'Company settings saved.');
    }

    public function destination(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['destination_id' => 'nullable|integer|exists:destinations,id', 'country' => ['required', 'string', 'max:100', Rule::unique('destinations', 'country')->ignore($request->input('destination_id'))], 'active' => 'required|boolean', 'base_charge' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D'], 'per_weight' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D'], 'minimum_charge' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D'], 'additional_charge' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/D']]);
        DB::transaction(function () use ($data, $request, $service) {
            CompanySetting::query()->lockForUpdate()->firstOrFail();
            $destination = isset($data['destination_id']) ? Destination::findOrFail($data['destination_id']) : new Destination;
            $before = $destination->exists ? $destination->load('rate')->toArray() : null;
            $destination->fill(collect($data)->only(['country', 'active'])->all())->save();
            Rate::updateOrCreate(['destination_id' => $destination->id], collect($data)->only(['base_charge', 'per_weight', 'minimum_charge', 'additional_charge'])->all());
            $destination->load('rate');
            $service->audit($request->user(), 'settings.rate', $destination, $before);
        }, 3);

        return back()->with('success', 'Destination and rate saved. Existing shipment prices are unchanged.');
    }

    public function method(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['method_id' => 'nullable|integer|exists:payment_methods,id', 'name' => ['required', 'string', 'max:100', Rule::unique('payment_methods', 'name')->ignore($request->input('method_id'))], 'active' => 'required|boolean']);
        DB::transaction(function () use ($data, $request, $service) {
            $method = isset($data['method_id']) ? PaymentMethod::findOrFail($data['method_id']) : new PaymentMethod;
            $before = $method->exists ? $method->toArray() : null;
            $method->fill(collect($data)->only(['name', 'active'])->all())->save();
            $service->audit($request->user(), 'settings.payment_method', $method, $before);
        });

        return back()->with('success', 'Payment method saved.');
    }

    public function user(Request $request, CourierService $service): RedirectResponse
    {
        $data = $request->validate(['user_id' => 'nullable|integer|exists:users,id', 'name' => 'required|string|max:150', 'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($request->input('user_id'))], 'role' => 'required|in:admin,staff', 'active' => 'required|boolean', 'password' => [filled($request->input('user_id')) ? 'nullable' : 'required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        DB::transaction(function () use ($data, $request, $service) {
            $admins = User::where('role', 'admin')->where('active', true)->lockForUpdate()->get();
            $user = isset($data['user_id']) ? User::query()->lockForUpdate()->findOrFail($data['user_id']) : new User;
            if ($user->exists && $user->active && $user->isAdmin() && ($data['role'] !== 'admin' || ! $data['active']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active administrator is required.']);
            }
            $before = $user->exists ? $user->only(['name', 'email', 'role', 'active']) : null;
            $values = collect($data)->only(['name', 'email', 'role', 'active'])->all();
            if (filled($data['password'] ?? null)) {
                $values['password'] = $data['password'];
            }
            $user->fill($values)->save();
            if ($user->wasChanged('password') || ! $user->active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $service->audit($request->user(), 'settings.user', $user, $before, $user->only(['name', 'email', 'role', 'active']) + ['password_changed' => filled($data['password'] ?? null)]);
        }, 3);

        return back()->with('success', 'User account saved.');
    }

    public function audit(): View
    {
        return view('settings.audit', ['logs' => AuditLog::with('staff')->latest('id')->paginate(30)]);
    }
}
