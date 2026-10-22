<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Bentuk input tambah item; kecocokan opsi dengan produk dicek di CartService. */
class CartItemStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:10'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*' => ['integer', 'distinct'],
            'catatan' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'qty.min' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 10.',
            'catatan.max' => 'Catatan maksimal 100 karakter.',
        ];
    }
}
