/* Shares basemap setup and adapts authorized, fictional transfer positions.
 * API: no app requests; Leaflet uses the configured CARTO/OpenStreetMap tiles.
 * DOM: optional carto-basemap-key meta; the calling page owns its map container.
 */
(() => {
  'use strict';
  function markerStatus(item) {
    if (item.status === 'scheduled') {
      return 'scheduled';
    }
    if (item.status === 'arrived') {
      return 'arrived';
    }
    if (item.status === 'completed') {
      return 'completed';
    }
    if (item.status === 'cancelled') {
      return 'cancelled';
    }
    if (item.is_delayed) {
      return 'delayed';
    }
    return 'in_progress';
  }

  function createBasemap(map, options = {}) {
    let onLoad = options.onLoad;
    if (onLoad === undefined) {
      onLoad = () => {};
    }
    let onFallback = options.onFallback;
    if (onFallback === undefined) {
      onFallback = () => {};
    }
    let onError = options.onError;
    if (onError === undefined) {
      onError = () => {};
    }
    const config = window.TRACE_MAP_CONFIG || {};
    const key =
      config.cartoApiKey || document.querySelector('meta[name="carto-basemap-key"]')?.content.trim();
    const osmAttribution =
      `${window.HospitalI18n.mark("trace_map_shared.copy")} ` +
      `<a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">${window.HospitalI18n.mark("trace_map_shared.openstreetmap")}` +
      `</a> ${window.HospitalI18n.mark("trace_map_shared.contributors")}`;
    const osm = () =>
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: osmAttribution,
        className: 'osm-dark-tiles',
        keepBuffer: 2
      });
    const useCarto = Boolean(key) && !config.tileUrl;
    const cartoUrl = () =>
      `https://basemaps.cartocdn.com/rastertiles/${window.HospitalTheme.current === 'light' ? 'light_all' : 'dark_all'}/{z}/{x}/{y}{r}.png?key=${encodeURIComponent(key)}`;
    let layer;
    if (config.tileUrl) {
      layer = L.tileLayer(config.tileUrl, {
        maxZoom: 19,
        attribution: config.attribution || osmAttribution,
        className: config.tileClassName || ''
      });
    } else {
      if (useCarto) {
        layer = L.tileLayer(cartoUrl(), {
          maxZoom: 20,
          attribution:
            `${osmAttribution}, ${window.HospitalI18n.mark("trace_map_shared.copy")} ` +
            `<a href="https://carto.com/attribution/" target="_blank" rel="noopener">${window.HospitalI18n.mark("trace_map_shared.carto")}` +
            `</a>`
        });
      } else {
        layer = osm();
      }
    }
    let failures = 0;
    let loadedTiles = 0;
    let fallback = false;
    function updateTheme() {
      if (useCarto && !fallback) {
        failures = 0;
        loadedTiles = 0;
        layer.setUrl(cartoUrl());
      }
    }
    window.addEventListener('hospital:themechange', updateTheme);
    map.once('unload', () => window.removeEventListener('hospital:themechange', updateTheme));
    function monitor(currentLayer, canFallback) {
      currentLayer.on('tileload', () => {
        loadedTiles += 1;
        onLoad();
      });
      currentLayer.on('tileerror', () => {
        failures += 1;
        if (canFallback && failures >= 3 && map.hasLayer(currentLayer)) {
          map.removeLayer(currentLayer);
          layer = osm();
          fallback = true;
          failures = 0;
          monitor(layer, false);
          layer.addTo(map);
          onFallback();
        } else if (!canFallback && failures >= 4 && loadedTiles === 0) {
          onError();
        }
      });
    }
    monitor(layer, Boolean(key || config.tileUrl));
    layer.addTo(map);
    return layer;
  }

  function adapt(item) {
    const status = markerStatus(item);
    const coordinates = item.geometry.map(point => [point.latitude, point.longitude]);
    return {
      id: item.id,
      get name() {
        return window.HospitalI18n.t("trace.traslado_value", { value0: item.id });
      },
      ambulance: item.ambulance.code,
      origin: item.origin.name,
      destination: item.destination.name,
      eta: window.TraceApi.time(item.eta).slice(0, 5),
      status,
      color: ['#8b5cf6', '#3b82f6', '#06b6d4', '#10b981', '#f97316'][Number(item.id) % 5],
      coordinates,
      currentPosition: item.position ? [item.position.latitude, item.position.longitude] : coordinates[0],
      sampledAt: item.position?.recorded_at,
      positionId: item.position?.id ?? null,
      lifecycle: item.status,
      distanceMeters: item.distance_meters
    };
  }
  window.TraceMapShared = Object.freeze({ createBasemap, adapt });
})();
