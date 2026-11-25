<?php

namespace App\Http\Requests;

class UploadImportRequest extends ImportOptionsRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
