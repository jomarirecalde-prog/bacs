<?php

namespace App\Http\Requests\OfficialBusiness;

use App\Enums\TravelTransportation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfficialBusinessRequest extends FormRequest
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
            'purpose' => [$draft ? 'nullable' : 'required', 'string', 'max:5000'],
            'purpose_particular' => ['nullable', 'string', 'max:5000'],
            'destination_from' => ['nullable', 'string', 'max:255'],
            'destination_to' => [$draft ? 'nullable' : 'required', 'string', 'max:255'],
            'date_covered_start' => [$draft ? 'nullable' : 'required', 'date'],
            'date_covered_end' => [$draft ? 'nullable' : 'required', 'date', 'after_or_equal:date_covered_start'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'transportation' => ['nullable', Rule::enum(TravelTransportation::class)],
            'transportation_mode' => ['nullable', Rule::enum(TravelTransportation::class)],
            'transportation_other' => ['nullable', 'string', 'max:255'],
            'vehicle_id' => ['nullable', 'integer'],
            'vehicle_name' => ['nullable', 'string', 'max:255'],
            'plate_number' => ['nullable', 'string', 'max:32'],
            'accommodation_type' => ['nullable', 'string', 'max:255'],
            'accommodation_particular' => ['nullable', 'string', 'max:5000'],
            'trip_ticket_id' => ['nullable', 'integer'],
            'participant_employee_ids' => ['nullable', 'array', 'max:10'],
            'participant_employee_ids.*' => ['integer', 'exists:employees,id'],
            'include_requester_as_participant' => ['sometimes', 'boolean'],
            'external_participants' => ['nullable', 'array', 'max:10'],
            'external_participants.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
