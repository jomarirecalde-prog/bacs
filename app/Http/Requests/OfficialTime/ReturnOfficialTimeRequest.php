<?php

namespace App\Http\Requests\OfficialTime;

use Illuminate\Foundation\Http\FormRequest;

class ReturnOfficialTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
