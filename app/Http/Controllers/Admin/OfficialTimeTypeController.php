<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OfficialTimeType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficialTimeTypeController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('official_time_types', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'requires_location' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['requires_attachment'] = $request->boolean('requires_attachment');
        $data['requires_location'] = $request->boolean('requires_location');
        $data['is_active'] = $request->boolean('is_active', true);

        OfficialTimeType::query()->create($data);

        return back()->with('success', 'Official Time type added.');
    }

    public function update(Request $request, OfficialTimeType $officialTimeType)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', Rule::unique('official_time_types', 'code')->ignore($officialTimeType->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'requires_location' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['requires_attachment'] = $request->boolean('requires_attachment');
        $data['requires_location'] = $request->boolean('requires_location');
        $data['is_active'] = $request->boolean('is_active');

        $officialTimeType->update($data);

        return back()->with('success', 'Official Time type updated.');
    }
}
