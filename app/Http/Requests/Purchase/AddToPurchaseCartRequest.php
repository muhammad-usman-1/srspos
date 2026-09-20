<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class AddToPurchaseCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', \Illuminate\Validation\Rule::exists('products', 'barcode')->where('store_id', auth()->user()->store_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'barcode.required' => __('Barcode is required'),
            'barcode.exists' => __('Product not found with this barcode'),
        ];
    }
}
