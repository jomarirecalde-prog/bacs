@if ($showPersonalInfoReminderBanner ?? false)
    <div class="alert-warning mb-4" role="status">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
        <span class="min-w-0 flex-1">
            <span class="block font-bold">Please update your personal information</span>
            <span class="mt-1 block text-warn-900/90">
                Some details are still missing
                ({{ implode(', ', $personalInfoReminderLabels) }}).
                Keep your profile complete so HR and payroll records stay accurate.
            </span>
            <a href="{{ route('profile.show') }}" class="btn btn-primary btn-sm mt-3 inline-flex">Update profile</a>
        </span>
    </div>
@endif