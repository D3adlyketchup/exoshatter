/* ============================================================
   Exoshatter's Planetracker
   Free, ad-free, real-time flight map.
   Data: api.adsb.fi (ADS-B state vectors) + api.adsbdb.com (routes)
   ============================================================ */

(function () {
  'use strict';

  const REFRESH_MS = 8000;
  const RADIUS_NM = 250; // adsb.fi max radius per request
  const DEFAULT_CENTER = [50.11, 8.68]; // Frankfurt — a dense traffic hub as a sane default
  const DEFAULT_ZOOM = 7;
  const STALE_MS = 45000; // grey out a plane if not refreshed within this window

  /* ---------------------------------------------------------- */
  /* Map setup                                                   */
  /* ---------------------------------------------------------- */

  const map = L.map('map', {
    center: DEFAULT_CENTER,
    zoom: DEFAULT_ZOOM,
    zoomControl: true,
    worldCopyJump: true,
  });

  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap &copy; CARTO',
    subdomains: 'abcd',
    maxZoom: 19,
  }).addTo(map);

  /* ---------------------------------------------------------- */
  /* State                                                        */
  /* ---------------------------------------------------------- */

  const markers = new Map(); // hex -> { marker, data, lastSeen }
  const routeCache = new Map(); // callsign -> route info (or null)
  let activeHex = null;
  let fetchTimer = null;
  let inFlightController = null;

  const el = (id) => document.getElementById(id);
  const statCount = el('stat-count');
  const statRefresh = el('stat-refresh');
  const statUpdated = el('stat-updated');
  const statusText = el('status-text');
  const statusDot = document.querySelector('.status-dot');
  const footCoords = el('foot-coords');
  const drawer = el('drawer');

  /* ---------------------------------------------------------- */
  /* Plane icon                                                   */
  /* ---------------------------------------------------------- */

  function planeSvg(track, faded) {
    const rot = Number.isFinite(track) ? track : 0;
    return `
      <svg width="26" height="26" viewBox="0 0 24 24" style="transform:rotate(${rot}deg)">
        <path d="M12 1.5 L14.6 9.5 L22.5 13.2 L22.5 15 L14.6 13 L14.6 18.5 L18 21 L18 22.4 L12 21 L6 22.4 L6 21 L9.4 18.5 L9.4 13 L1.5 15 L1.5 13.2 L9.4 9.5 Z"
              fill="${faded ? 'var(--text-faint)' : 'var(--phosphor)'}"
              stroke="#0a0e0f" stroke-width="0.6"/>
      </svg>`;
  }

  function makeIcon(track, faded) {
    return L.divIcon({
      className: 'plane-icon' + (faded ? ' stale' : ''),
      html: planeSvg(track, faded),
      iconSize: [26, 26],
      iconAnchor: [13, 13],
    });
  }

  /* ---------------------------------------------------------- */
  /* Formatting helpers                                           */
  /* ---------------------------------------------------------- */

  function fmt(value, unit, digits) {
    if (value === undefined || value === null || value === '' || Number.isNaN(value)) return '\u2014';
    const n = typeof value === 'number' ? value.toFixed(digits ?? 0) : value;
    return unit ? `${n} ${unit}` : `${n}`;
  }

  function vrateLabel(fpm) {
    if (fpm === undefined || fpm === null || Number.isNaN(fpm)) return '\u2014';
    if (Math.abs(fpm) < 100) return 'level';
    const dir = fpm > 0 ? '\u2191 climbing' : '\u2193 descending';
    return `${dir} ${Math.abs(Math.round(fpm))} fpm`;
  }

  function timeAgo() {
    const now = new Date();
    return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }

  /* ---------------------------------------------------------- */
  /* Fetch loop                                                   */
  /* ---------------------------------------------------------- */

  async function fetchAircraft() {
    const c = map.getCenter();
    footCoords.textContent = `lat ${c.lat.toFixed(3)} \u00b7 lon ${c.lng.toFixed(3)}`;

    if (inFlightController) inFlightController.abort();
    inFlightController = new AbortController();

    const url = `https://api.adsb.fi/v2/lat/${c.lat.toFixed(4)}/lon/${c.lng.toFixed(4)}/dist/${RADIUS_NM}`;

    try {
      const res = await fetch(url, { signal: inFlightController.signal });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      const list = Array.isArray(json.ac) ? json.ac : [];
      renderAircraft(list);
      setStatus('live', 'live');
      statUpdated.textContent = timeAgo();
      statCount.textContent = list.length;
    } catch (err) {
      if (err.name === 'AbortError') return;
      setStatus('error', 'connection lost \u2014 retrying');
    }
  }

  function setStatus(mode, label) {
    statusDot.classList.remove('live', 'error');
    if (mode === 'live') statusDot.classList.add('live');
    if (mode === 'error') statusDot.classList.add('error');
    statusText.textContent = label;
  }

  function renderAircraft(list) {
    const now = Date.now();
    const seenHex = new Set();

    list.forEach((ac) => {
      if (typeof ac.lat !== 'number' || typeof ac.lon !== 'number') return;
      seenHex.add(ac.hex);

      const track = typeof ac.track === 'number' ? ac.track : (typeof ac.true_heading === 'number' ? ac.true_heading : 0);
      const existing = markers.get(ac.hex);

      if (existing) {
        existing.marker.setLatLng([ac.lat, ac.lon]);
        existing.marker.setIcon(makeIcon(track, false));
        existing.data = ac;
        existing.lastSeen = now;
        const callsign = (ac.flight || '').trim() || ac.hex.toUpperCase();
        existing.marker.setTooltipContent(callsign);
      } else {
        const marker = L.marker([ac.lat, ac.lon], { icon: makeIcon(track, false) });
        const callsign = (ac.flight || '').trim() || ac.hex.toUpperCase();
        marker.bindTooltip(callsign, { direction: 'top', offset: [0, -10], className: 'plane-tooltip' });
        marker.on('click', () => openDrawer(ac.hex));
        marker.addTo(map);
        markers.set(ac.hex, { marker, data: ac, lastSeen: now });
      }
    });

    // fade / prune aircraft no longer in the feed
    for (const [hex, entry] of markers) {
      if (!seenHex.has(hex)) {
        const age = now - entry.lastSeen;
        if (age > STALE_MS * 3) {
          map.removeLayer(entry.marker);
          markers.delete(hex);
          if (activeHex === hex) closeDrawer();
        } else if (age > STALE_MS) {
          entry.marker.setIcon(makeIcon(entry.data.track, true));
        }
      }
    }

    // live-refresh drawer if the selected plane updated
    if (activeHex && markers.has(activeHex)) {
      populateDrawer(markers.get(activeHex).data);
    }
  }

  function scheduleLoop() {
    fetchAircraft();
    fetchTimer = setInterval(fetchAircraft, REFRESH_MS);
  }

  /* ---------------------------------------------------------- */
  /* Drawer                                                       */
  /* ---------------------------------------------------------- */

  function openDrawer(hex) {
    activeHex = hex;
    const entry = markers.get(hex);
    if (!entry) return;
    populateDrawer(entry.data);
    drawer.classList.add('open');
    drawer.setAttribute('aria-hidden', 'false');
    fetchRoute(entry.data.flight);
  }

  function closeDrawer() {
    activeHex = null;
    drawer.classList.remove('open');
    drawer.setAttribute('aria-hidden', 'true');
  }

  function populateDrawer(ac) {
    const callsign = (ac.flight || '').trim() || ac.hex.toUpperCase();
    el('d-callsign').textContent = callsign;
    el('d-registration').textContent = ac.r ? `reg. ${ac.r}` : '';
    el('d-altitude').textContent = fmt(ac.alt_baro, 'ft');
    el('d-speed').textContent = fmt(ac.gs, 'kt', 0);
    el('d-heading').textContent = Number.isFinite(ac.track) ? `${Math.round(ac.track)}\u00b0` : '\u2014';
    el('d-vrate').textContent = vrateLabel(ac.baro_rate);
    el('d-squawk').textContent = ac.squawk || '\u2014';
    el('d-type').textContent = ac.t || ac.desc || '\u2014';
    el('d-icao').textContent = (ac.hex || '\u2014').toUpperCase();

    const cached = routeCache.get(callsign);
    if (cached !== undefined) {
      el('d-origin').textContent = cached ? cached.origin : 'not available';
      el('d-destination').textContent = cached ? cached.destination : 'not available';
    } else {
      el('d-origin').textContent = '\u2026';
      el('d-destination').textContent = '\u2026';
    }
  }

  async function fetchRoute(rawCallsign) {
    const callsign = (rawCallsign || '').trim();
    if (!callsign) {
      el('d-origin').textContent = 'not available';
      el('d-destination').textContent = 'not available';
      return;
    }
    if (routeCache.has(callsign)) {
      const cached = routeCache.get(callsign);
      el('d-origin').textContent = cached ? cached.origin : 'not available';
      el('d-destination').textContent = cached ? cached.destination : 'not available';
      return;
    }
    try {
      const res = await fetch(`https://api.adsbdb.com/v0/callsign/${encodeURIComponent(callsign)}`);
      const json = await res.json();
      const route = json && json.response && json.response.flightroute;
      if (route && route.origin && route.destination) {
        const info = {
          origin: `${route.origin.iata_code || route.origin.icao_code || '?'} \u00b7 ${route.origin.municipality || route.origin.name}`,
          destination: `${route.destination.iata_code || route.destination.icao_code || '?'} \u00b7 ${route.destination.municipality || route.destination.name}`,
        };
        routeCache.set(callsign, info);
        if (activeHex && (markers.get(activeHex) || {}).data && ((markers.get(activeHex).data.flight || '').trim() === callsign)) {
          el('d-origin').textContent = info.origin;
          el('d-destination').textContent = info.destination;
        }
      } else {
        routeCache.set(callsign, null);
        if (activeHex) {
          el('d-origin').textContent = 'not available';
          el('d-destination').textContent = 'not available';
        }
      }
    } catch (e) {
      routeCache.set(callsign, null);
      if (activeHex) {
        el('d-origin').textContent = 'not available';
        el('d-destination').textContent = 'not available';
      }
    }
  }

  el('drawer-close').addEventListener('click', closeDrawer);

  /* ---------------------------------------------------------- */
  /* Locate me                                                    */
  /* ---------------------------------------------------------- */

  el('btn-locate').addEventListener('click', () => {
    if (!navigator.geolocation) return;
    setStatus('live', 'locating\u2026');
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        map.setView([pos.coords.latitude, pos.coords.longitude], 8);
        fetchAircraft();
      },
      () => setStatus('error', 'location unavailable'),
      { timeout: 8000 }
    );
  });

  /* ---------------------------------------------------------- */
  /* Refetch on significant pan/zoom (in addition to the timer)  */
  /* ---------------------------------------------------------- */

  let lastFetchCenter = map.getCenter();
  map.on('moveend', () => {
    const c = map.getCenter();
    if (c.distanceTo(lastFetchCenter) > 50000) { // 50km
      lastFetchCenter = c;
      fetchAircraft();
    }
  });

  /* ---------------------------------------------------------- */
  /* Boot                                                         */
  /* ---------------------------------------------------------- */

  scheduleLoop();
})();
