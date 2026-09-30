<?php

namespace App\Http\Requests\TravelOrder;

use App\Enums\TravelTransportation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTravelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->employee !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $draft = $this->input('action') === 'draft';

        return [
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'official_station' => ['nullable', 'string', 'max:255'],
            'number_of_bh' => ['nullable', 'string', 'max:32'],
            'destinations' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'destinations.*' => ['string', 'max:500'],
            'date_start' => [$draft ? 'nullable' : 'required', 'date'],
            'date_end' => [$draft ? 'nullable' : 'required', 'date', 'after_or_equal:date_start'],
            'purpose' => [$draft ? 'nullable' : 'required', 'string', 'max:5000'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'client_company' => ['nullable', 'string', 'max:255'],
            'transportation' => ['nullable', Rule::enum(TravelTransportation::class)],
            'transportation_other' => ['nullable', 'string', 'max:255'],
            'vehicle_type' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'traveler_ids' => [$draft ? 'nullable' : 'required', 'array', $draft ? 'min:0' : 'min:1'],
            'traveler_ids.*' => ['integer', 'exists:employees,id'],
            'include_requester_as_traveler' => ['sometimes', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }
}
