<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PasswordUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password_lama' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return ['password_lama.current_password' => 'Password lama tidak sesuai.'];
    }
}
