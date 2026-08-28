<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->employee;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;
        $employeeId = $this->user()->employee?->id;

        return [
            'first_name' => ['required', 'string', 'max:80', 'regex:/^[\pL\s\'.-]+$/u'],
            'middle_name' => ['nullable', 'string', 'max:80', 'regex:/^[\pL\s\'.-]+$/u'],
            'last_name' => ['required', 'string', 'max:80', 'regex:/^[\pL\s\'.-]+$/u'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[\pL\s\'.-]+$/u'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('employees', 'email')->ignore($employeeId),
            ],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim(strip_tags((string) $this->input('first_name'))),
            'middle_name' => $this->filled('middle_name') ? trim(strip_tags((string) $this->input('middle_name'))) : null,
            'last_name' => trim(strip_tags((string) $this->input('last_name'))),
            'suffix' => $this->filled('suffix') ? trim(strip_tags((string) $this->input('suffix'))) : null,
            'email' => strtolower(trim(strip_tags((string) $this->input('email')))),
            'contact_number' => $this->filled('contact_number') ? trim(strip_tags((string) $this->input('contact_number'))) : null,
            'address' => $this->filled('address') ? trim(strip_tags((string) $this->input('address'))) : null,
        ]);
    }
}
