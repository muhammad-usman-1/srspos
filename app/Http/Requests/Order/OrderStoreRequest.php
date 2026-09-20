<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class OrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('customers', 'id')->where('store_id', auth()->user()->store_id)],
            'method' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\Payment::METHODS))],
            'discount_type' => ['nullable', 'in:fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => __('order.validation.customer_not_found'),
            'amount.required' => __('order.validation.amount_required'),
            'amount.min' => __('order.validation.amount_min'),
            'amount.decimal' => __('order.validation.amount_decimal'),
        ];
    }
}
