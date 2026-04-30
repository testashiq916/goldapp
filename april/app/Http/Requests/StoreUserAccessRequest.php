<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:add,edit'],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/'],
            'name' => ['required', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'max:50'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
            'maxcredit' => ['nullable', 'numeric', 'min:0'],
            'maxdisc' => ['nullable', 'numeric', 'min:0'],
            'maxdiscperc' => ['nullable', 'numeric', 'min:0'],
            'minvaperc' => ['nullable', 'numeric', 'min:0'],
            'maxadjwgtbc' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code', ''))),
            'name' => strtoupper(trim((string) $this->input('name', ''))),
        ]);
    }
}
