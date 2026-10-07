<?php

namespace App\Http\Requests\OfficialTime;

use App\Enums\LeaveDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideOfficialTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([LeaveDecision::Approved->value, LeaveDecision::Denied->value])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
