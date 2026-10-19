<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLaneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('lanes', 'number')->ignore($this->route('lane')),
            ],
            'status' => ['required', 'string', Rule::in(['active', 'maintenance', 'broken'])],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}