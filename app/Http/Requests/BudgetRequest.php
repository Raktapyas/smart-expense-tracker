<?php

namespace App\Http\Requests;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return ! $budget instanceof Budget || (int) $budget->user_id === (int) $this->user()->id;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $budget = $this->route('budget');

        return [
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where(function ($query) use ($userId) {
                    $query->where('type', 'expense')
                        ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
                }),
                Rule::unique('budgets', 'category_id')
                    ->where('user_id', $userId)
                    ->ignore($budget instanceof Budget ? $budget->id : null),
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'between:0.01,999999999999.99'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('category_id')) {
                return;
            }

            $budget = $this->route('budget');

            $exists = Budget::where('user_id', $this->user()->id)
                ->whereNull('category_id')
                ->when($budget instanceof Budget, fn ($q) => $q->whereKeyNot($budget->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('category_id', 'You already have an overall monthly budget.');
            }
        });
    }
}
