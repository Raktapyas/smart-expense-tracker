<?php

namespace App\Http\Requests;

use App\Services\CsvImporter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_account_id' => [
                'required',
                Rule::exists('accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'date_format' => ['required', Rule::in(array_keys(CsvImporter::DATE_FORMATS))],
            'skip_duplicates' => ['nullable', 'boolean'],
        ];
    }

    /** @return array{default_account_id: int, date_format: string, skip_duplicates: bool} */
    public function options(): array
    {
        return [
            'default_account_id' => (int) $this->input('default_account_id'),
            'date_format' => (string) $this->input('date_format'),
            'skip_duplicates' => $this->boolean('skip_duplicates'),
        ];
    }
}
