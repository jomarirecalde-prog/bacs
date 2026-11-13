@extends('layouts.app')

@section('title', 'Station Locations')
@section('page-title', 'Station Locations')
@section('page-subtitle', 'Last known GPS from attendance station devices')

@section('content')
<div class="-mx-3 -my-3 flex h-[calc(100dvh-4rem)] min-h-0 flex-col overflow-hidden sm:-mx-4 sm:-my-4 lg:-mx-8 lg:-my-8">
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-line bg-surface px-3 py-2.5 sm:px-4 lg:px-8">
        <div class="min-w-0 space-y-0.5">
            <p id="station-location-count" class="text-sm font-medium text-ink">Loading station positions…</p>
            <p class="text-xs text-muted">Last reported GPS from station heartbeats and scans.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.stations.monitoring') }}" class="btn-outline">Station Monitoring</a>
            <a href="{{ route('admin.stations.index') }}" class="btn-outline-info">Manage Stations</a>
        </div>
    </div>

    <div class="grid min-h-0 flex-1 gap-0 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,20rem)]">
        <div class="relative min-h-0 min-h-[min(45vh,20rem)] lg:min-h-0">
            <div id="station-location-map" class="absolute inset-0 z-0 bg-shell-100" role="region" aria-label="Map of attendance station locations"></div>
        </div>

        <aside class="flex max-h-[min(40vh,16rem)] min-h-0 flex-col overflow-hidden border-t border-line bg-surface lg:max-h-none lg:border-t-0 lg:border-l">
            <div class="card-header shrink-0 border-b border-line">
                <h2 class="card-title">Stations</h2>
            </div>
            <div id="station-location-list" class="flex-1 space-y-2 overflow-y-auto p-4"></div>
            <p id="station-location-empty" hidden class="p-4 text-sm text-muted">No attendance stations yet.</p>
        </aside>
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
