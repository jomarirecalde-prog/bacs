@php
    $mapbox = $mapboxSettings ?? [];
@endphp

<section id="settings-mapbox" class="card card-accent-info scroll-mt-28 overflow-hidden">
    <div class="card-header">
        <div class="min-w-0">
            <h2 class="card-title text-base">Station location map (Mapbox)</h2>
            <p class="mt-0.5 text-sm text-muted">Public token used on the admin station GPS map.</p>
        </div>
        @if (! empty($mapbox['using_database']))
            <span class="chip shrink-0">Saved in app</span>
        @elseif (! empty($mapbox['using_env']))
            <span class="chip shrink-0">Using .env</span>
        @else
            <span class="chip shrink-0">Not configured</span>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.settings.mapbox.update') }}" class="form-card-body">
        @csrf
        @method('PUT')

        <div class="alert-info text-xs sm:text-sm">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>
                Create a <strong class="font-semibold">public</strong> token at
                <a href="https://account.mapbox.com/access-tokens/" class="font-semibold underline" target="_blank" rel="noopener noreferrer">Mapbox</a>
                (starts with <code class="text-xs">pk.</code>).
                Restrict URLs to <code class="text-xs">https://bacsconstruction.com/*</code> if you use URL limits.
                After saving, open
                <a href="{{ route('admin.stations.locations') }}" class="font-semibold underline">Station Locations</a> to verify.
            </span>
        </div>

        <div class="form-grid lg:grid-cols-2">
            <div>
                <label class="label" for="mapbox_access_token">Access token</label>
                <input id="mapbox_access_token" name="mapbox_access_token" type="password" class="input @error('mapbox_access_token') input-error @enderror"
                       value="{{ old('mapbox_access_token') }}"
                       placeholder="{{ ! empty($mapbox['token_configured']) ? 'Leave blank to keep '.$mapbox['token_hint'] : 'pk.…' }}"
                       autocomplete="off"
                       @if (empty($mapbox['token_configured'])) required @endif>
                @error('mapbox_access_token')<p class="error-text">{{ $message }}</p>@enderror
                @if (! empty($mapbox['token_configured']))
                    <p class="mt-1 text-xs text-muted">Configured ({{ $mapbox['token_hint'] }}). Enter a new token only to replace it.</p>
                @endif
            </div>
            <div>
                <label class="label" for="mapbox_style">Map style (optional)</label>
                <input id="mapbox_style" name="mapbox_style" class="input @error('mapbox_style') input-error @enderror"
                       value="{{ old('mapbox_style', $mapbox['style'] ?? '') }}"
                       placeholder="mapbox://styles/mapbox/streets-v12">
                @error('mapbox_style')<p class="error-text">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit" class="btn-primary">Save map settings</button>
        </div>
    </form>
</section>