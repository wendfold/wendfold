/**
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Draw a GPX route with the bundled Leaflet and leaflet-gpx files.
 * OpenStreetMap tiles load only when the map element opts in.
 */
(function () {
    'use strict';

    function boot() {
        var mapEl = document.getElementById('hc-route-map');

        if (!mapEl || !window.L || typeof L.map !== 'function' || typeof L.GPX !== 'function') {
            return;
        }

        var gpxUrl = mapEl.getAttribute('data-gpx-url') || '';

        if (!gpxUrl) {
            return;
        }

        var iconBase = mapEl.getAttribute('data-icon-base') || '';
        var leafletImages = mapEl.getAttribute('data-leaflet-images') || '';
        var osmEnabled = mapEl.getAttribute('data-osm') === '1';

        if (leafletImages && L.Icon && L.Icon.Default) {
            L.Icon.Default.imagePath = leafletImages;
        }

        var map = L.map(mapEl);

        if (osmEnabled && typeof L.tileLayer === 'function') {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18
            }).addTo(map);
        }

        new L.GPX(gpxUrl, {
            async: true,
            marker_options: {
                startIconUrl: iconBase + 'pin-icon-start.png',
                endIconUrl: iconBase + 'pin-icon-end.png',
                shadowUrl: iconBase + 'pin-shadow.png',
                wptIconUrls: {
                    '': iconBase + 'pin-icon-wpt.png'
                },
                wptIconTypeUrls: {
                    '': iconBase + 'pin-icon-wpt.png'
                }
            },
            polyline_options: { color: '#ff6600', weight: 4, opacity: 0.9 }
        }).on('loaded', function (e) {
            map.fitBounds(e.target.getBounds());
        }).addTo(map);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
