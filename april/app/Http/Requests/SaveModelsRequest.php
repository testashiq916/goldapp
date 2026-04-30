<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveModelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|max:1',
            'names' => 'required|array',
            'names.*' => 'nullable|string|max:15',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Model type is required',
            'type.max' => 'Model type must be 1 character',
            'names.required' => 'At least one model name is required',
            'names.array' => 'Names must be an array',
            'names.*.max' => 'Model name cannot exceed 15 characters',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('names') && is_array($this->input('names'))) {
            $clean = array_values(array_filter(array_map(
                fn ($name) => strtoupper(trim((string) $name)),
                $this->input('names', [])
            )));
            $this->merge(['names' => $clean]);
        }
    }
}

