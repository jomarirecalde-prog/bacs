@extends('layouts.app')

@section('title', 'Manual salary computation')
@section('page-title', 'Manual salary computation')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="page-stack">
    <div class="page-header">
        <nav class="breadcrumb min-w-0" aria-label="Breadcrumb">
            <a href="{{ route('admin.payroll.employees.salary.index', $employee) }}" class="truncate transition hover:text-brand-700">Salary</a>
            <span class="text-faint" aria-hidden="true">/</span>
            <span class="truncate text-ink-soft">New computation</span>
        </nav>
    </div>

    @if ($errors->any())
        <div class="alert-critical text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.store', $employee) }}" class="card overflow-hidden">
        @csrf
        <div class="card-header">
            <h2 class="card-title">Computation setup</h2>
        </div>
        <div class="form-card-body">
            @include('admin.payroll.manual-computations.partials.form-fields')
        </div>
        <div class="card-footer flex flex-wrap gap-2">
            <button type="submit" class="btn-primary">Save draft &amp; calculate</button>
            <a href="{{ route('admin.payroll.employees.salary.index', $employee) }}" class="btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection
