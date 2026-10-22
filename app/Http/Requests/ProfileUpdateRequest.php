<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^(\+62|0)8[0-9]{8,12}$/'],
        ];
    }

    public function messages(): array
    {
        return ['no_hp.regex' => 'Nomor HP harus diawali 08 atau +628 dan berisi 10 sampai 14 digit.'];
    }
}
