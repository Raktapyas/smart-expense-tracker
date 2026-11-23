<?php

namespace App\Http\Requests;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'opening_balance' => ['required', 'numeric', 'decimal:0,2', 'between:-999999999999,999999999999'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
