/**
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Draw a kickstands-up marker with the bundled Leaflet files.
 * OpenStreetMap tiles load only when the map element opts in.
 */
(function () {
    'use strict';

    function boot() {
        var el = document.getElementById('hc-ksu-map');

        if (!el || !window.L || typeof L.map !== 'function' || typeof L.marker !== 'function') {
            return;
        }

        var lat = parseFloat(el.getAttribute('data-lat'));
        var lng = parseFloat(el.getAttribute('data-lng'));

        if (!isFinite(lat) || !isFinite(lng)) {
            return;
        }

        var images = el.getAttribute('data-leaflet-images') || '';

        if (images && L.Icon && L.Icon.Default) {
            L.Icon.Default.imagePath = images;
        }

        var map = L.map(el).setView([lat, lng], 15);

        if (el.getAttribute('data-osm') === '1' && typeof L.tileLayer === 'function') {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 18
            }).addTo(map);
        }

        L.marker([lat, lng]).addTo(map);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
