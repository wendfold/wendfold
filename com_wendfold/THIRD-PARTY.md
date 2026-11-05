# Third-party notices

These libraries are bundled with `com_wendfold`. Each copy keeps its own license file. Wendfold does not load them from a CDN.

| Library | Version | License | Source | Path in this component |
| --- | --- | --- | --- | --- |
| qrcode-generator (PHP) | commit `83b7e8fe3fddd3b0368dbafd6ce56995bd25e3c8` (2025-08-07) | MIT | https://github.com/kazuhikoarase/qrcode-generator/tree/83b7e8fe3fddd3b0368dbafd6ce56995bd25e3c8/php | `admin/vendor/qrcode-generator/` |
| Leaflet | 1.9.4 | BSD-2-Clause | https://github.com/Leaflet/Leaflet/releases/tag/v1.9.4 | `media/vendor/leaflet/` |
| leaflet-gpx | 1.7.0 | BSD-2-Clause | https://github.com/mpetazzoni/leaflet-gpx/releases/tag/v1.7.0 | `media/vendor/leaflet-gpx/` |

The PHP QR library is the single file `qrcode.php` from that commit, plus the repository `LICENSE`. The file header identifies it as Kazuhiko Arase's QRCode for PHP, copyright 2009. Check-in QR images are rendered by Wendfold from that library. The symbol contents are the check-in URL, and the image is 200 by 200 pixels.

The bundled `qrcode.php` is kept byte-identical to that upstream file. Static analysis and PHPCompatibility warnings in it, including `trigger_error` with `E_USER_ERROR` (deprecated since PHP 8.4), are accepted as vendored code. That path is only reachable with oversized input.

Leaflet is the v1.9.4 release archive (`leaflet.css`, `leaflet.js`, `leaflet.js.map`, and `dist/images`). leaflet-gpx is tag v1.7.0 (`gpx.js` and the pin images). Marker and pin images are the files shipped with those versions.

OpenStreetMap tiles are not bundled. When the Privacy option is on, the visitor's browser requests tiles from `tile.openstreetmap.org` for a GPX route map, and for a kickstands-up marker map when that location has coordinates. See [docs/privacy.md](../docs/privacy.md).

## Outbound links

| Service | Where | What is sent |
| --- | --- | --- |
| Google Maps (`https://maps.google.com/maps`) | **Get Directions** on a kickstands-up address | The address, only when the visitor clicks the link |

No Google content loads automatically. The page does not embed Google Maps.

The word "QR Code" is a registered trademark of DENSO WAVE INCORPORATED.
