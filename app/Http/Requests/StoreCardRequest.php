<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // authorization عبر Policy داخل Controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'uuid'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:500'],
            'status' => ['nullable', 'in:available,reserved,sold'], // غالبًا اتركها available
        ];
    }
}
