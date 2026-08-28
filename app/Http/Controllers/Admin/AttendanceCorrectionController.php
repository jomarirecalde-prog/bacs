<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttendanceCorrectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ReviewAttendanceCorrectionRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Services\AttendanceCorrectionService;
use App\Services\DirectoryCatalog;
use Illuminate\Http\Request;

class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private readonly AttendanceCorrectionService $corrections,
        private readonly DirectoryCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceCorrectionRequest::class);

        $records = AttendanceCorrectionRequest::query()
            ->with(['employee.department', 'reviewer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('date'), fn ($q) => $q->forDate($request->string('date')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.attendance-corrections.index', [
            'records' => $records,
            'employees' => $this->catalog->employeeOptions(),
            'statuses' => AttendanceCorrectionStatus::cases(),
            'filters' => $request->only(['status', 'employee_id', 'date']),
        ]);
    }

    public function show(AttendanceCorrectionRequest $correction)
    {
        $this->authorize('view', $correction);
        $correction->load(['employee.department', 'reviewer', 'attendance.edits.modifier']);

        return view('admin.attendance-corrections.show', [
            'correction' => $correction,
        ]);
    }

    public function review(ReviewAttendanceCorrectionRequest $request, AttendanceCorrectionRequest $correction)
    {
        $this->authorize('review', AttendanceCorrectionRequest::class);

        $notes = $request->string('review_notes')->toString() ?: null;

        if ($request->string('decision')->toString() === 'approve') {
            $this->corrections->approve($request->user(), $correction, $notes);

            return redirect()
                ->route('admin.attendance-corrections.show', $correction)
                ->with('success', 'Correction approved and DTR updated.');
        }

        $this->corrections->reject($request->user(), $correction, $notes);

        return redirect()
            ->route('admin.attendance-corrections.show', $correction)
            ->with('success', 'Correction request rejected.');
    }
}
