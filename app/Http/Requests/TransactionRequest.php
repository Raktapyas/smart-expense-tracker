<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Transaction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        if ($transaction instanceof Transaction) {
            return (int) $transaction->user_id === (int) $this->user()->id;
        }

        return true;
    }

    public function rules(): array
    {
        $ownAccount = Rule::exists('accounts', 'id')->where('user_id', $this->user()->id);

        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'account_id' => ['required', $ownAccount],
            'transfer_account_id' => [
                Rule::requiredIf(fn () => $this->input('type') === TransactionType::Transfer->value),
                'nullable',
                'different:account_id',
                $ownAccount,
            ],
            'category_id' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) {
                    $type = $this->input('type');

                    if (! $value || $type === TransactionType::Transfer->value) {
                        return;
                    }

                    $valid = Category::availableTo($this->user()->id)
                        ->whereKey($value)
                        ->where('type', $type)
                        ->exists();

                    if (! $valid) {
                        $fail('Choose a valid category for this transaction type.');
                    }
                },
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'merchant' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
