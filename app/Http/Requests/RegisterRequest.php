<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'no_hp' => ['required', 'string', 'max:20', 'regex:/^(\+62|0)8[0-9]{8,12}$/'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
            'setuju_privasi' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'no_hp.regex' => 'Nomor HP harus diawali 08 atau +628 dan berisi 10 sampai 14 digit.',
            'setuju_privasi.accepted' => 'Anda harus menyetujui Kebijakan Privasi untuk mendaftar.',
        ];
    }
}
