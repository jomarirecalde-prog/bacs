<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('action', 'like', $term)
                        ->orWhere('module', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->string('module')))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $modules = AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');

        return view('admin.audit.index', compact('logs', 'modules'));
    }
}
