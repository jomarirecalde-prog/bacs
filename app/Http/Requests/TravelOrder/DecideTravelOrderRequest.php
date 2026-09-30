<?php

namespace App\Http\Requests\TravelOrder;

use App\Enums\LeaveDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideTravelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('endorse', $this->route('travelOrder')) ?? false;
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
