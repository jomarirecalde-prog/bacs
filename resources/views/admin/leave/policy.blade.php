@extends('layouts.app')

@section('title', 'Company Default Leave Policy')
@section('page-title', 'Company Default Leave Policy')
@section('page-subtitle', 'Template for initializing new employee balances only')

@section('content')
<div class="space-y-6">
    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm">These defaults are used when a new employee is created. Changing this policy does <strong>not</strong> overwrite existing employees' individually managed balances.</span>
    </div>

    <form method="POST" action="{{ route('admin.leave.policy.update') }}" class="card card-accent-brand">
        @csrf
        @method('PUT')
        <div class="card-header">
            <h2 class="card-title">Default annual entitlements</h2>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Leave type</th>
                        <th>Category</th>
                        <th>Default days</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $i => $type)
                        <tr>
                            <td class="font-semibold">
                                {{ $type->name }}
                                <input type="hidden" name="types[{{ $i }}][id]" value="{{ $type->id }}">
                            </td>
                            <td class="capitalize">{{ $type->category }}</td>
                            <td>
                                <input type="number" min="0" max="365" step="0.5" name="types[{{ $i }}][entitlement_days]" value="{{ $type->entitlement_days }}" class="input max-w-28" @readonly($type->code === 'special')>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer flex flex-wrap gap-2">
            <button class="btn-primary" type="submit">Save default policy</button>
            <a href="{{ route('admin.leave.entitlements') }}" class="btn-outline">Back to employee balances</a>
        </div>
    </form>
</div>
@endsection
