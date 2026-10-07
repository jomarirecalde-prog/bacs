<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use App\Support\BacsPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $admin = $this->route('admin');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($admin?->id),
            ],
            'username' => [
                'required',
                'string',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($admin?->id),
            ],
            'password' => ['nullable', 'confirmed', BacsPassword::rule()],
            'status' => ['required', Rule::enum(AccountStatus::class)],
        ];
    }
}
