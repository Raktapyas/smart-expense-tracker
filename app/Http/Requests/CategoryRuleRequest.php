<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('keyword');

        if (is_string($keyword)) {
            $this->merge(['keyword' => mb_strtolower(trim($keyword))]);
        }
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'keyword' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('category_rules', 'keyword')->where('user_id', $userId),
            ],
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where(
                        fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId)
                    )
                ),
            ],
        ];
    }
}
