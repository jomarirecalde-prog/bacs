<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = WorkSchedule::query()->withCount('employees')->orderByDesc('is_default')->orderBy('name')->get();

        return view('admin.schedules.index', compact('schedules'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->syncDefault($data);

        WorkSchedule::query()->create($data);

        return back()->with('success', 'Work schedule created.');
    }

    public function update(Request $request, WorkSchedule $schedule)
    {
        $data = $this->validated($request, $schedule->id);
        $this->syncDefault($data, $schedule->id);
        $schedule->update($data);

        return back()->with('success', 'Work schedule updated.');
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('work_schedules', 'name')->ignore($ignore)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'break_start' => ['nullable', 'date_format:H:i'],
            'break_end' => ['nullable', 'date_format:H:i', 'after:break_start'],
            'required_minutes' => ['required', 'integer', 'min:60', 'max:1440'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['work_days'] = array_map('intval', $data['work_days']);
        $data['status'] = $data['status'] ?? AccountStatus::Active->value;

        return $data;
    }

    private function syncDefault(array $data, ?int $ignore = null): void
    {
        if (! empty($data['is_default'])) {
            WorkSchedule::query()->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->update(['is_default' => false]);
        }
    }
}
