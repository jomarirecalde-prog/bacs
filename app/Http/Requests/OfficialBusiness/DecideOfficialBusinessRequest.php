<?php

namespace App\Http\Requests\OfficialBusiness;

use App\Enums\LeaveDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideOfficialBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('endorse', $this->route('officialBusinessRequest')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(LeaveDecision::class)],
            'reason' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->input('decision') === LeaveDecision::Denied->value)],
            'signature' => ['nullable', 'string'],
        ];
    }
}
