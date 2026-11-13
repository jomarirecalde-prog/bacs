import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import icon from 'leaflet/dist/images/marker-icon.png';
import iconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import shadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: iconRetina,
    iconUrl: icon,
    shadowUrl: shadow,
});

const PH_CENTER = [12.8797, 121.774];
const DEFAULT_ZOOM = 6;

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

/** @type {null | (() => void)} */
let destroyStationMap = null;

export function teardownStationMap() {
    if (destroyStationMap) {
        destroyStationMap();
        destroyStationMap = null;
    }
}

function initStationMap(config) {
    const root = document.getElementById('station-location-map');
    if (!root) {
        return;
    }

    teardownStationMap();

    const map = L.map(root, { scrollWheelZoom: true }).setView(PH_CENTER, DEFAULT_ZOOM);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(map);

    const markers = new Map();
    let stations = Array.isArray(config.stations) ? config.stations : [];
    let locatedStations = stations.filter((s) => s.latitude != null && s.longitude != null);

    const renderMarkers = () => {
        const nextIds = new Set();
        locatedStations.forEach((station) => {
            nextIds.add(station.id);
            const latLng = [station.latitude, station.longitude];
            let marker = markers.get(station.id);
            if (!marker) {
                marker = L.marker(latLng).addTo(map);
                markers.set(station.id, marker);
            } else {
                marker.setLatLng(latLng);
            }
            marker.bindPopup(popupHtml(station));
        });
        markers.forEach((marker, id) => {
            if (!nextIds.has(id)) {
                map.removeLayer(marker);
                markers.delete(id);
            }
        });
        if (locatedStations.length === 1) {
            map.setView([locatedStations[0].latitude, locatedStations[0].longitude], 15);
        } else if (locatedStations.length > 1) {
            const bounds = L.latLngBounds(locatedStations.map((s) => [s.latitude, s.longitude]));
            map.fitBounds(bounds.pad(0.15));
        }
    };

    renderMarkers();

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
                map.setView([station.latitude, station.longitude], 16);
                const marker = markers.get(id);
                marker?.openPopup();
            });
        });

        if (emptyEl) {
            emptyEl.hidden = stations.length > 0;
        }
    };

    const applyStations = (next) => {
        stations = Array.isArray(next) ? next : [];
        locatedStations = stations.filter((s) => s.latitude != null && s.longitude != null);
        renderMarkers();
        renderList();
    };

    renderList();

    listEl?.addEventListener('click', () => {
        setTimeout(() => map.invalidateSize(), 0);
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

    const onResize = () => map.invalidateSize();
    window.addEventListener('resize', onResize);

    const invalidateTimer = setTimeout(() => map.invalidateSize(), 100);

    destroyStationMap = () => {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
        clearTimeout(invalidateTimer);
        window.removeEventListener('resize', onResize);
        map.remove();
        markers.clear();
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
