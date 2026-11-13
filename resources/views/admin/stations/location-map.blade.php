@extends('layouts.app')

@section('title', 'Station Locations')
@section('page-title', 'Station Locations')
@section('page-subtitle', 'Last known GPS from attendance station devices')

@push('head')
    @vite(['resources/js/station-map.js'])
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p id="station-location-count" class="text-sm text-muted">Loading station positions…</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.stations.monitoring') }}" class="btn-outline">Station Monitoring</a>
            <a href="{{ route('admin.stations.index') }}" class="btn-outline-info">Manage Stations</a>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)]">
        <div class="card overflow-hidden p-0">
            <div id="station-location-map" class="h-[min(70vh,32rem)] w-full bg-shell-100" role="region" aria-label="Map of attendance station locations"></div>
        </div>

        <aside class="card flex max-h-[min(70vh,32rem)] flex-col overflow-hidden">
            <div class="card-header shrink-0">
                <h2 class="card-title">Stations</h2>
            </div>
            <div id="station-location-list" class="flex-1 space-y-2 overflow-y-auto p-4"></div>
            <p id="station-location-empty" hidden class="p-4 text-sm text-muted">No attendance stations yet.</p>
        </aside>
    </div>

    <div class="alert-neutral text-sm">
        Station kiosks must allow device location; coordinates are sent with every heartbeat and QR scan.
        The map shows the <strong>last reported</strong> GPS position for each station.
    </div>
</div>

@php
    $mapConfig = [
        'stations' => $stations,
        'locationsUrl' => $locationsUrl,
    ];
@endphp
<script id="station-map-config" type="application/json">@json($mapConfig)</script>
@endsection
