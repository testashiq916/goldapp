<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('stktype', 'code'),
            ],
            'name' => 'required|string|max:30',
            'def' => 'nullable|boolean',
            'compare' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Stock type code is required',
            'code.max' => 'Code cannot exceed 10 characters',
            'code.unique' => 'This code already exists',
            'name.required' => 'Stock type name is required',
            'name.max' => 'Name cannot exceed 30 characters',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->code)),
            'name' => trim((string) $this->name),
        ]);
    }
}

