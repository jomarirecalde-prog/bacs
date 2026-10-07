<?php

namespace App\Http\Requests\OfficialTime;

use Illuminate\Foundation\Http\FormRequest;

class ReturnOfficialTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('officialTimeRequest');

        return $request ? ($this->user()?->can('endorse', $request) ?? false) : false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
