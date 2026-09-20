<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
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
            'barcode.required' => __('cart.validation.barcode_required'),
            'barcode.exists' => __('cart.validation.barcode_not_found'),
        ];
    }
}
