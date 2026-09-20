<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class RemoveFromCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('products', 'id')->where('store_id', auth()->user()->store_id)],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => __('cart.validation.product_id_required'),
            'product_id.exists' => __('cart.validation.product_not_found'),
        ];
    }
}
