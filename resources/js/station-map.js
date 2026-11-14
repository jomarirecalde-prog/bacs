import mapboxgl from 'mapbox-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

const PH_CENTER = [121.774, 12.8797];
const DEFAULT_ZOOM = 6;
const STYLE_STREETS = 'mapbox://styles/mapbox/streets-v12';
const STYLE_SATELLITE = 'mapbox://styles/mapbox/satellite-streets-v12';
const BUILDINGS_LAYER_ID = 'station-3d-buildings';
const DEM_SOURCE_ID = 'station-mapbox-dem';

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatLocatedAt(iso) {
    if (!iso) {
        return 'No GPS reported yet';
    }
    try {
        return new Date(iso).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
    } catch {
        return iso;
    }
}

function popupHtml(station) {
    const tone = station.online ? 'Online' : station.presence;
    return `
        <div class="text-sm leading-snug">
            <div class="font-bold text-ink">${escapeHtml(station.name)}</div>
            <div class="text-xs text-muted">${escapeHtml(station.code)}</div>
            <div class="mt-2 space-y-1 text-xs">
                <div><span class="font-semibold">Status:</span> ${escapeHtml(tone)}</div>
                <div><span class="font-semibold">Site:</span> ${escapeHtml(station.address)}</div>
                <div><span class="font-semibold">Last GPS:</span> ${escapeHtml(formatLocatedAt(station.located_at))}</div>
            </div>
            <a class="mt-2 inline-block font-semibold text-brand-700 hover:underline" href="${escapeHtml(station.show_url)}">Station details</a>
        </div>
    `;
}

function resolveAccessToken(config) {
    const fromConfig = typeof config.mapboxToken === 'string' ? config.mapboxToken.trim() : '';
    return fromConfig;
}

function resolveInitialStyle(config) {
    if (typeof config.mapStyle === 'string' && config.mapStyle.trim()) {
        const style = config.mapStyle.trim();
        if (style.includes('satellite')) {
            return { basemap: 'satellite', style };
        }
        return { basemap: 'streets', style };
    }
    return { basemap: 'streets', style: STYLE_STREETS };
}

function showMapError(root, message) {
    root.innerHTML = `
        <div class="flex h-full items-center justify-center p-6 text-center">
            <div class="max-w-md space-y-2">
                <p class="font-semibold text-ink">Map unavailable</p>
                <p class="text-sm text-muted">${escapeHtml(message)}</p>
            </div>
        </div>
    `;
}

function findLabelLayerId(map) {
    const layers = map.getStyle()?.layers ?? [];
    const match = layers.find(
        (layer) => layer.type === 'symbol' && layer.layout && layer.layout['text-field'],
    );
    return match?.id;
}

function stationFlyOptions(map, center, zoom) {
    const pitch = map.__station3dEnabled ? 58 : 0;
    return { center, zoom, pitch, duration: 600, essential: true };
}

/** @type {null | (() => void)} */
let destroyStationMap = null;

export function teardownStationMap() {
    if (destroyStationMap) {
        destroyStationMap();
        destroyStationMap = null;
    }
}

function mountMapControls(wrapper, callbacks) {
    const host = document.createElement('div');
    host.className = 'station-map-controls pointer-events-none absolute inset-0 z-10';
    host.innerHTML = `
        <div class="pointer-events-auto absolute left-3 top-3 flex flex-col gap-2 sm:left-4 sm:top-4">
            <div class="flex rounded-xl border border-edge bg-surface/95 p-1 shadow-md backdrop-blur-sm" role="group" aria-label="Map style">
                <button type="button" data-basemap="streets" class="station-map-basemap-btn rounded-lg px-3 py-1.5 text-xs font-semibold text-ink transition" aria-pressed="true">Map</button>
                <button type="button" data-basemap="satellite" class="station-map-basemap-btn rounded-lg px-3 py-1.5 text-xs font-semibold text-ink transition" aria-pressed="false">Satellite</button>
            </div>
            <button type="button" id="station-map-toggle-3d" class="rounded-xl border border-edge bg-surface/95 px-3 py-2 text-xs font-semibold text-ink shadow-md backdrop-blur-sm transition" aria-pressed="false">3D terrain &amp; buildings</button>
        </div>
    `;
    wrapper.appendChild(host);

    const basemapBtns = host.querySelectorAll('.station-map-basemap-btn');
    const toggle3d = host.querySelector('#station-map-toggle-3d');

    basemapBtns.forEach((btn) => {
        btn.addEventListener('click', () => {
            callbacks.onBasemap(btn.getAttribute('data-basemap'));
        });
    });
    toggle3d?.addEventListener('click', () => {
        callbacks.onToggle3d();
    });

    const sync = (state) => {
        basemapBtns.forEach((btn) => {
            const active = btn.getAttribute('data-basemap') === state.basemap;
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            btn.classList.toggle('station-map-basemap-active', active);
        });
        if (toggle3d) {
            toggle3d.setAttribute('aria-pressed', state.terrain3d ? 'true' : 'false');
            toggle3d.classList.toggle('station-map-3d-active', state.terrain3d);
        }
    };

    return { host, sync };
}

function initStationMap(config) {
    const root = document.getElementById('station-location-map');
    if (!root) {
        return;
    }

    teardownStationMap();

    const accessToken = resolveAccessToken(config);
    if (!accessToken) {
        showMapError(
            root,
            'Set MAPBOX_ACCESS_TOKEN in the server .env (public pk. token), then refresh Laravel config '
                + '(production: php artisan config:clear && php artisan config:cache, or re-run deploy). '
                + 'View page source and search for mapboxToken — if it is null, PHP is not reading the token yet.',
        );
        return;
    }

    mapboxgl.accessToken = accessToken;

    const initial = resolveInitialStyle(config);
    let basemap = initial.basemap;
    let terrain3d = false;

    root.style.height = '100%';
    root.style.width = '100%';

    const map = new mapboxgl.Map({
        container: root,
        style: initial.style,
        center: PH_CENTER,
        zoom: DEFAULT_ZOOM,
        scrollZoom: true,
        attributionControl: true,
        pitchWithRotate: true,
        touchPitch: true,
        antialias: true,
    });
    map.__station3dEnabled = false;

    map.addControl(
        new mapboxgl.NavigationControl({ showCompass: true, visualizePitch: true }),
        'top-right',
    );

    const wrapper = root.parentElement;
    const controls = wrapper ? mountMapControls(wrapper, {
        onBasemap: (mode) => {
            if (mode !== 'streets' && mode !== 'satellite') {
                return;
            }
            if (mode === basemap) {
                return;
            }
            basemap = mode;
            const nextStyle = mode === 'satellite' ? STYLE_SATELLITE : STYLE_STREETS;
            map.setStyle(nextStyle);
            controls.sync({ basemap, terrain3d });
        },
        onToggle3d: () => {
            terrain3d = !terrain3d;
            map.__station3dEnabled = terrain3d;
            applyTerrain3d(map, terrain3d);
            controls.sync({ basemap, terrain3d });
        },
    }) : null;
    controls?.sync({ basemap, terrain3d });

    const markers = new Map();
    let stations = Array.isArray(config.stations) ? config.stations : [];
    let locatedStations = stations.filter((s) => s.latitude != null && s.longitude != null);

    const fitToStations = () => {
        if (locatedStations.length === 1) {
            const s = locatedStations[0];
            map.flyTo(stationFlyOptions(map, [s.longitude, s.latitude], 15));
            return;
        }
        if (locatedStations.length > 1) {
            const bounds = new mapboxgl.LngLatBounds();
            locatedStations.forEach((s) => {
                bounds.extend([s.longitude, s.latitude]);
            });
            const pitch = terrain3d ? 50 : 0;
            map.fitBounds(bounds, { padding: 48, maxZoom: 15, duration: 600, pitch });
        }
    };

    const renderMarkers = () => {
        const nextIds = new Set();
        locatedStations.forEach((station) => {
            nextIds.add(station.id);
            const lngLat = [station.longitude, station.latitude];
            let marker = markers.get(station.id);
            if (!marker) {
                const popup = new mapboxgl.Popup({ offset: 24, closeButton: true, maxWidth: '280px' });
                marker = new mapboxgl.Marker().setLngLat(lngLat).setPopup(popup).addTo(map);
                markers.set(station.id, marker);
            } else {
                marker.setLngLat(lngLat);
            }
            marker.getPopup().setHTML(popupHtml(station));
        });
        markers.forEach((marker, id) => {
            if (!nextIds.has(id)) {
                marker.remove();
                markers.delete(id);
            }
        });
        fitToStations();
    };

    function clearTerrain3d(target) {
        target.setTerrain(null);
        if (target.getLayer(BUILDINGS_LAYER_ID)) {
            target.removeLayer(BUILDINGS_LAYER_ID);
        }
        if (target.getSource(DEM_SOURCE_ID)) {
            target.removeSource(DEM_SOURCE_ID);
        }
        target.easeTo({ pitch: 0, duration: 450 });
    }

    function applyTerrain3d(target, enabled) {
        if (!enabled) {
            clearTerrain3d(target);
            return;
        }

        if (!target.getSource(DEM_SOURCE_ID)) {
            target.addSource(DEM_SOURCE_ID, {
                type: 'raster-dem',
                url: 'mapbox://mapbox.mapbox-terrain-dem-v1',
                tileSize: 512,
                maxzoom: 14,
            });
        }
        target.setTerrain({ source: DEM_SOURCE_ID, exaggeration: 1.2 });

        if (!target.getLayer(BUILDINGS_LAYER_ID)) {
            const labelLayerId = findLabelLayerId(target);
            target.addLayer(
                {
                    id: BUILDINGS_LAYER_ID,
                    source: 'composite',
                    'source-layer': 'building',
                    filter: ['==', 'extrude', 'true'],
                    type: 'fill-extrusion',
                    minzoom: 14,
                    paint: {
                        'fill-extrusion-color': '#c4cad4',
                        'fill-extrusion-height': ['get', 'height'],
                        'fill-extrusion-base': ['get', 'min_height'],
                        'fill-extrusion-opacity': 0.72,
                    },
                },
                labelLayerId,
            );
        }

        target.easeTo({ pitch: 58, duration: 650 });
    }

    const onStyleReady = () => {
        if (terrain3d) {
            applyTerrain3d(map, true);
        }
        renderMarkers();
        map.resize();
    };

    map.on('style.load', onStyleReady);

    const listEl = document.getElementById('station-location-list');
    const emptyEl = document.getElementById('station-location-empty');
    const countEl = document.getElementById('station-location-count');

    const renderList = () => {
        if (countEl) {
            countEl.textContent = `${locatedStations.length} of ${stations.length} with GPS`;
        }
        if (!listEl) {
            return;
        }
        listEl.innerHTML = stations
            .map((station) => {
                const hasGps = station.latitude != null && station.longitude != null;
                const badge = station.online ? 'badge-brand' : 'badge-neutral';
                return `
                    <button type="button" data-station-id="${station.id}" class="station-map-list-item w-full rounded-xl border border-edge bg-surface px-3 py-2.5 text-left transition hover:border-brand-300 hover:bg-brand-50/40 ${hasGps ? '' : 'opacity-70'}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-ink">${escapeHtml(station.name)}</div>
                                <div class="truncate text-xs text-muted">${escapeHtml(station.code)} · ${escapeHtml(station.address)}</div>
                            </div>
                            <span class="${badge} shrink-0 text-[10px]">${escapeHtml(station.presence)}</span>
                        </div>
                        <div class="mt-1 text-xs text-muted">${hasGps ? escapeHtml(formatLocatedAt(station.located_at)) : 'Waiting for device GPS (heartbeat)'}</div>
                    </button>
                `;
            })
            .join('');

        listEl.querySelectorAll('.station-map-list-item').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-station-id'));
                const station = stations.find((s) => s.id === id);
                if (!station || station.latitude == null || station.longitude == null) {
                    return;
                }
                map.flyTo(stationFlyOptions(map, [station.longitude, station.latitude], 16));
                const marker = markers.get(id);
                const popup = marker?.getPopup();
                if (marker && popup && !popup.isOpen()) {
                    marker.togglePopup();
                }
            });
        });

        if (emptyEl) {
            emptyEl.hidden = stations.length > 0;
        }
    };

    const applyStations = (next) => {
        stations = Array.isArray(next) ? next : [];
        locatedStations = stations.filter((s) => s.latitude != null && s.longitude != null);
        if (map.isStyleLoaded()) {
            renderMarkers();
        }
        renderList();
    };

    renderList();

    listEl?.addEventListener('click', () => {
        setTimeout(() => map.resize(), 0);
    });

    let refreshTimer = null;
    if (config.locationsUrl) {
        refreshTimer = setInterval(async () => {
            try {
                const res = await fetch(config.locationsUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                if (Array.isArray(data.stations)) {
                    applyStations(data.stations);
                }
            } catch {
                // Silent refresh; map keeps last known positions.
            }
        }, 60000);
    }

    const onResize = () => map.resize();
    window.addEventListener('resize', onResize);

    const invalidateLayout = () => map.resize();
    const invalidateTimer = setTimeout(invalidateLayout, 100);
    requestAnimationFrame(() => {
        requestAnimationFrame(invalidateLayout);
    });

    destroyStationMap = () => {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        clearTimeout(invalidateTimer);
        window.removeEventListener('resize', onResize);
        markers.forEach((marker) => marker.remove());
        markers.clear();
        controls?.host.remove();
        map.remove();
    };
}

export function bootStationMapFromDom() {
    const el = document.getElementById('station-map-config');
    if (!el || !document.getElementById('station-location-map')) {
        return;
    }
    let config = {};
    try {
        config = JSON.parse(el.textContent || '{}');
    } catch {
        config = {};
    }
    initStationMap(config);
}