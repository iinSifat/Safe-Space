<?php
require_once '../config/config.php';

if (!is_logged_in()) {
    redirect('../auth/login.php');
}

check_session_timeout();

// Emergency resources are intended for non-professional users.
if (function_exists('is_professional') && is_professional()) {
    redirect('index.php');
}

// Bangladesh hotline list (minimal, official, and locally relevant).
// Source references (for maintainers): bangladesh.gov.bd emergency hotlines + DGHS health call center.
$hotline_groups = [
    'Bangladesh — Emergency Services' => [
        ['label' => 'National Emergency Service', 'number' => '999', 'note' => 'Police, Fire, and Ambulance dispatch (toll-free).'],
        ['label' => 'Fire Service & Civil Defence hotline', 'number' => '102', 'note' => 'Fire emergencies and rescue support.'],
    ],
    'Bangladesh — Health & Crisis Support' => [
        ['label' => 'Health Call Center (DGHS)', 'number' => '16263', 'note' => 'Health information and support; ask for urgent guidance or nearest services.'],
        ['label' => 'Violence against women & children helpline', 'number' => '109', 'note' => 'Support line for women and children.'],
        ['label' => 'Child Helpline', 'number' => '1098', 'note' => 'Child protection support.'],
    ],
    'Bangladesh — Government Info' => [
        ['label' => 'Government Information & Services', 'number' => '333', 'note' => 'Information about public services; for emergencies, call 999.'],
    ],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Support (Bangladesh) | Safe Space</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="includes/dashboard-layout.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <style>
        .emergency-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .emergency-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .emergency-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .hotline-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .hotline-card {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .hotline-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hotline-item {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--light-gray);
        }

        .hotline-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .hotline-label {
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .hotline-note {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.35;
        }

        .hotline-number {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 10px;
            border: 2px solid #EB5757;
            background: rgba(235, 87, 87, 0.14);
            color: #C72E2E;
            font-weight: 900;
            letter-spacing: 0.5px;
            white-space: nowrap;
            min-width: 84px;
            text-align: center;
        }

        .emergency-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .disclaimer {
            margin-top: 1rem;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.9);
            line-height: 1.35;
        }

        .map-card {
            background: var(--bg-card, #F8F9F7);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            margin-top: 1.5rem;
        }

        #emergency-map {
            width: 100%;
            height: 420px;
            border-radius: var(--radius-lg);
            border: 2px solid var(--light-gray);
            overflow: hidden;
            background: #fff;
        }

        .map-status {
            margin-top: 0.75rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .places-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.25rem;
            margin-top: 1rem;
        }

        .places-list {
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-lg);
            background: white;
            padding: 1rem;
            min-height: 180px;
        }

        .places-title {
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .place-row {
            padding: 10px 0;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .place-row:last-child { border-bottom: none; }

        .place-name {
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .place-meta {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .place-distance {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 10px;
            border: 2px solid #EB5757;
            background: rgba(235, 87, 87, 0.10);
            color: #C72E2E;
            font-weight: 900;
            white-space: nowrap;
            align-self: flex-start;
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="top-bar">
                <h2 style="margin: 0; font-size: 18px; color: var(--text-primary);">Emergency Support</h2>
                <div class="top-bar-right">
                    <a href="notifications.php" style="text-decoration: none; color: var(--text-primary); font-weight: 600; padding: 8px 16px; background: var(--light-bg); border-radius: 8px;">
                        <svg class="icon icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Notifications
                    </a>
                </div>
            </div>

            <div class="content-area">
                <div class="emergency-container">
                    <div class="emergency-header">
                        <h1>Emergency Support</h1>
                        <p class="disclaimer">
                            If you are in Bangladesh and in immediate danger or someone might be harmed, call <strong>999</strong> now.
                            This page lists Bangladesh emergency hotlines and an option to request urgent help from a professional.
                        </p>
                    </div>

                    <div class="hotline-grid">
                        <?php foreach ($hotline_groups as $group_title => $items): ?>
                            <div class="hotline-card">
                                <div class="hotline-title">
                                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align: middle;"><path d="M12 2l7 4v6c0 5-3 9-7 10-4-1-7-5-7-10V6l7-4z"/></svg>
                                    <?php echo htmlspecialchars($group_title); ?>
                                </div>

                                <?php foreach ($items as $it): ?>
                                    <div class="hotline-item">
                                        <div>
                                            <div class="hotline-label"><?php echo htmlspecialchars($it['label']); ?></div>
                                            <?php if (!empty($it['note'])): ?>
                                                <div class="hotline-note"><?php echo htmlspecialchars($it['note']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="hotline-number"><?php echo htmlspecialchars($it['number']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="emergency-actions">
                        <a href="professionals.php?emergency=1" class="btn btn-primary">Request emergency help from a professional</a>
                        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>

                    <div class="map-card">
                        <div class="hotline-title" style="margin-bottom: 0.75rem;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block; vertical-align: middle;"><path d="M21 10c0 6-9 13-9 13S3 16 3 10a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Nearby Hospitals & Therapy Services
                        </div>
                        <div id="emergency-map"></div>
                        <div id="map-status" class="map-status">Requesting your location…</div>

                        <div class="places-grid">
                            <div class="places-list">
                                <div class="places-title">Hospitals</div>
                                <div id="hospitals-list" class="place-meta">Loading…</div>
                            </div>
                            <div class="places-list">
                                <div class="places-title">Therapy / Mental Health</div>
                                <div id="therapy-list" class="place-meta">Loading…</div>
                            </div>
                        </div>

                        <div class="map-status" style="margin-top: 1rem;">
                            Data source: OpenStreetMap (Overpass API). Results depend on local map data availability.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function haversineMeters(lat1, lon1, lat2, lon2) {
            const R = 6371000;
            const toRad = d => (d * Math.PI) / 180;
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a = Math.sin(dLat/2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) ** 2;
            return 2 * R * Math.asin(Math.sqrt(a));
        }

        function formatDistance(m) {
            if (!isFinite(m)) return '';
            if (m < 1000) return `${Math.round(m)} m`;
            return `${(m/1000).toFixed(1)} km`;
        }

        function setList(el, items, userLat, userLon, onSelect) {
            if (!items || items.length === 0) {
                el.innerHTML = '<div class="place-meta">No results found nearby.</div>';
                return;
            }

            el.innerHTML = '';
            items.slice(0, 8).forEach(it => {
                const row = document.createElement('div');
                row.className = 'place-row';

                const left = document.createElement('div');
                const name = document.createElement('div');
                name.className = 'place-name';
                name.textContent = it.name || 'Unnamed';

                const meta = document.createElement('div');
                meta.className = 'place-meta';
                meta.textContent = it.kind;

                left.appendChild(name);
                left.appendChild(meta);

                const dist = document.createElement('div');
                dist.className = 'place-distance';
                dist.textContent = formatDistance(it.distance);

                row.appendChild(left);
                row.appendChild(dist);

                row.style.cursor = 'pointer';
                row.addEventListener('click', () => onSelect(it));
                el.appendChild(row);
            });
        }

        async function overpassQuery(lat, lon, radiusMeters) {
            // Hospitals and mental health related places. Keep the query compact to reduce rate limit issues.
            const q = `
                [out:json][timeout:25];
                (
                  node[amenity=hospital](around:${radiusMeters},${lat},${lon});
                  way[amenity=hospital](around:${radiusMeters},${lat},${lon});
                  relation[amenity=hospital](around:${radiusMeters},${lat},${lon});

                  node[amenity=clinic](around:${radiusMeters},${lat},${lon});
                  way[amenity=clinic](around:${radiusMeters},${lat},${lon});

                  node[healthcare=psychotherapist](around:${radiusMeters},${lat},${lon});
                  node[healthcare=psychologist](around:${radiusMeters},${lat},${lon});
                  node[healthcare=psychiatrist](around:${radiusMeters},${lat},${lon});
                  node[amenity=doctors](around:${radiusMeters},${lat},${lon});
                );
                out center tags;`;

            const res = await fetch('https://overpass-api.de/api/interpreter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: 'data=' + encodeURIComponent(q)
            });

            if (!res.ok) {
                throw new Error('Overpass request failed');
            }

            return res.json();
        }

        function normalizeElements(osm, userLat, userLon) {
            const out = [];
            for (const el of (osm.elements || [])) {
                const lat = el.lat ?? el.center?.lat;
                const lon = el.lon ?? el.center?.lon;
                if (typeof lat !== 'number' || typeof lon !== 'number') continue;

                const tags = el.tags || {};
                const name = tags.name || tags['name:en'] || '';

                let kind = 'Service';
                if (tags.amenity === 'hospital') kind = 'Hospital';
                else if (tags.amenity === 'clinic') kind = 'Clinic';
                else if (tags.healthcare) kind = `Healthcare: ${tags.healthcare}`;
                else if (tags.amenity === 'doctors') kind = 'Doctors';

                out.push({
                    name,
                    kind,
                    lat,
                    lon,
                    distance: haversineMeters(userLat, userLon, lat, lon),
                    tags
                });
            }
            out.sort((a,b) => a.distance - b.distance);
            return out;
        }

        (function initMap() {
            const statusEl = document.getElementById('map-status');
            const hospitalsEl = document.getElementById('hospitals-list');
            const therapyEl = document.getElementById('therapy-list');

            const map = L.map('emergency-map', { zoomControl: true });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const userMarker = L.circleMarker([0,0], {
                radius: 8,
                color: '#0F766E',
                fillColor: '#0F766E',
                fillOpacity: 0.9
            });

            const hospitalLayer = L.layerGroup().addTo(map);
            const therapyLayer = L.layerGroup().addTo(map);

            function showError(msg) {
                statusEl.textContent = msg;
                hospitalsEl.textContent = 'Location required to show nearby places.';
                therapyEl.textContent = 'Location required to show nearby places.';
                map.setView([23.8103, 90.4125], 12); // fallback center
            }

            function focusPlace(place) {
                map.setView([place.lat, place.lon], 16);
            }

            if (!navigator.geolocation) {
                showError('Geolocation is not supported in this browser.');
                return;
            }

            navigator.geolocation.getCurrentPosition(async (pos) => {
                const userLat = pos.coords.latitude;
                const userLon = pos.coords.longitude;

                userMarker.setLatLng([userLat, userLon]).addTo(map).bindPopup('Your location');
                map.setView([userLat, userLon], 14);

                statusEl.textContent = 'Searching nearby hospitals and therapy services…';

                try {
                    const osm = await overpassQuery(userLat, userLon, 5000);
                    const places = normalizeElements(osm, userLat, userLon);

                    const hospitals = places.filter(p => p.tags.amenity === 'hospital');
                    const therapy = places.filter(p =>
                        p.tags.healthcare === 'psychotherapist' ||
                        p.tags.healthcare === 'psychologist' ||
                        p.tags.healthcare === 'psychiatrist' ||
                        p.tags.amenity === 'doctors' ||
                        p.tags.amenity === 'clinic'
                    );

                    const nearestHospital = hospitals[0];
                    const nearestTherapy = therapy[0];

                    // Map markers
                    hospitalLayer.clearLayers();
                    therapyLayer.clearLayers();

                    hospitals.slice(0, 20).forEach(p => {
                        const isNearest = nearestHospital && p.lat === nearestHospital.lat && p.lon === nearestHospital.lon;
                        const marker = L.circleMarker([p.lat, p.lon], {
                            radius: isNearest ? 10 : 7,
                            color: '#C72E2E',
                            fillColor: isNearest ? '#EF4444' : '#C72E2E',
                            fillOpacity: 0.85
                        }).bindPopup(`${(p.name || 'Hospital')}<br>${formatDistance(p.distance)} away`);
                        marker.addTo(hospitalLayer);
                    });

                    therapy.slice(0, 20).forEach(p => {
                        const isNearest = nearestTherapy && p.lat === nearestTherapy.lat && p.lon === nearestTherapy.lon;
                        const marker = L.circleMarker([p.lat, p.lon], {
                            radius: isNearest ? 10 : 7,
                            color: '#7C3AED',
                            fillColor: isNearest ? '#A78BFA' : '#7C3AED',
                            fillOpacity: 0.85
                        }).bindPopup(`${(p.name || 'Service')}<br>${p.kind}<br>${formatDistance(p.distance)} away`);
                        marker.addTo(therapyLayer);
                    });

                    setList(hospitalsEl, hospitals, userLat, userLon, focusPlace);
                    setList(therapyEl, therapy, userLat, userLon, focusPlace);

                    const summaryParts = [];
                    if (nearestHospital) summaryParts.push(`Nearest hospital: ${formatDistance(nearestHospital.distance)}`);
                    if (nearestTherapy) summaryParts.push(`Nearest therapy service: ${formatDistance(nearestTherapy.distance)}`);
                    statusEl.textContent = summaryParts.length ? summaryParts.join(' • ') : 'No nearby places found.';
                } catch (e) {
                    statusEl.textContent = 'Could not load nearby places right now (map data service busy). Please try again later.';
                    hospitalsEl.textContent = 'No data.';
                    therapyEl.textContent = 'No data.';
                }
            }, () => {
                showError('Location permission denied. Enable location to show nearby hospitals and therapy services.');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
        })();
    </script>
</body>
</html>
