<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function index()
    {
        $holidays = Holiday::query()->orderBy('holiday_date')->get();

        return view('admin.settings.index', [
            'company' => Setting::get('company_name', 'BACS'),
            'address' => Setting::get('company_address', ''),
            'holidays' => $holidays,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'company_address' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::put('company_name', $data['company_name']);
        Setting::put('company_address', $data['company_address'] ?? '');
        Cache::forget('app_settings');

        return back()->with('success', 'Settings saved.');
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'type' => ['required', 'in:regular,special'],
        ]);

        Holiday::query()->create($data);

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }
}
