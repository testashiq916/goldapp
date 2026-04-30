<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:10|exists:items,code',
            'levels' => 'required|array',
            'levels.*.model' => 'nullable|string|max:20',
            'levels.*.size' => 'nullable|string|max:20',
            'levels.*.weight1' => 'nullable|numeric|min:0|max:999.999',
            'levels.*.weight2' => 'nullable|numeric|min:0|max:999.999',
            'levels.*.minqty' => 'nullable|integer|min:0',
            'levels.*.maxqty' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Item code is required',
            'code.exists' => 'Item code does not exist',
            'levels.required' => 'At least one reorder level is required',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
        ]);
    }
}

