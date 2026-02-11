<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
        return [
            'package_id' => ['required', 'exists:packages,id'],
            'card_id' => ['nullable', 'exists:cards,id'],
            'payment_method' => ['required', 'in:BOP,cash,palpay'],
            'payment_proof_url' => ['nullable', 'url'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
