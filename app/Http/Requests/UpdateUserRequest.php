<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'username'  => ['sometimes', 'required', 'string', 'max:100', 'unique:users,username,' . $userId],
            'email'     => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'password'  => ['sometimes', 'nullable', 'string', 'min:6'],
            'role'      => ['sometimes', 'required', 'in:admin,user'],
        ];
    }
}
