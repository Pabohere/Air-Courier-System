<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Validation\ValidationException;

class PaymentDetails
{
    public static function rules(): array
    {
        return ['remark' => 'nullable|string|max:150', 'cash_tendered' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/D']];
    }

    public static function values(array $data, PaymentMethod $method): array
    {
        validator($data, self::rules())->validate();
        $remark = trim($data['remark'] ?? '');
        if (in_array($method->name, ['Bank transfer', 'Pay'], true) && $remark === '') {
            throw ValidationException::withMessages(['remark' => __('Enter the bank name or pay name in the remark.')]);
        }
        $amount = Money::cents($data['amount']);
        $tendered = isset($data['cash_tendered']) ? Money::cents($data['cash_tendered']) : $amount;
        if ($method->name !== 'Cash' && $tendered !== $amount) {
            throw ValidationException::withMessages(['cash_tendered' => __('Change is available only for Cash payments.')]);
        }
        if ($tendered < $amount) {
            throw ValidationException::withMessages(['cash_tendered' => __('Cash received must cover the payment amount.')]);
        }

        return ['remark' => $remark === '' ? null : $remark, 'cash_tendered' => $method->name === 'Cash' ? Money::decimal($tendered) : null, 'change_amount' => Money::decimal($tendered - $amount)];
    }
}
