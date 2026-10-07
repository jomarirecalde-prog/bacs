<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountStatus;
use App\Support\BacsPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'confirmed', BacsPassword::rule()],
            'status' => ['nullable', Rule::enum(AccountStatus::class)],
        ];
    }
}
