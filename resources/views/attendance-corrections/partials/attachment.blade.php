@if ($correction->attachment_path)
    <div class="py-3">
        <dt class="text-muted">Supporting document</dt>
        <dd class="mt-2">
            <a class="btn-outline btn-sm inline-flex" href="{{ route('attendance-corrections.attachment', $correction) }}">
                Download {{ $correction->attachment_name ?: 'attachment' }}
            </a>
        </dd>
    </div>
@endif
