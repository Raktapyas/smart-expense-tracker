<?php

namespace App\Http\Requests;

use App\Enums\Frequency;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recurring = $this->route('recurring');

        return ! $recurring instanceof RecurringTransaction
            || (int) $recurring->user_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        $recurring = $this->route('recurring');
        $isUpdate = $recurring instanceof RecurringTransaction;
        $type = $isUpdate ? $recurring->type->value : $this->input('type');
        $ownAccount = Rule::exists('accounts', 'id')->where('user_id', $this->user()->id);

        $rules = [
            'account_id' => ['required', $ownAccount],
            'transfer_account_id' => [
                Rule::requiredIf($type === TransactionType::Transfer->value),
                'nullable',
                'different:account_id',
                $ownAccount,
            ],
            'category_id' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail) use ($type) {
                    if (! $value || $type === TransactionType::Transfer->value) {
                        return;
                    }

                    $valid = Category::availableTo($this->user()->id)
                        ->whereKey($value)
                        ->where('type', $type)
                        ->exists();

                    if (! $valid) {
                        $fail('Choose a valid category for this type.');
                    }
                },
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999999999.99'],
            'merchant' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'ends_on' => [
                'nullable',
                'date',
                $isUpdate ? 'after_or_equal:'.$recurring->starts_on->toDateString() : 'after_or_equal:starts_on',
            ],
        ];

        if (! $isUpdate) {
            $rules['type'] = ['required', Rule::enum(TransactionType::class)];
            $rules['frequency'] = ['required', Rule::enum(Frequency::class)];
            $rules['starts_on'] = ['required', 'date'];
        }

        return $rules;
    }
}
