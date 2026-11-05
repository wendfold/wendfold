# leaflet-gpx local patches

Upstream version: 1.7.0
License: BSD-2-Clause (see `LICENSE` in this directory)
Upstream tag: https://github.com/mpetazzoni/leaflet-gpx/releases/tag/v1.7.0

`gpx.js` is vendored leaflet-gpx 1.7.0 with the two patches below. No upstream 1.x release fixes them. leaflet-gpx 2.x requires Leaflet 2 alpha, which this component does not ship. Reapply on upgrade.

## Waypoint popup HTML

`marker.bindPopup` concatenated waypoint `<name>` and `<desc>` into popup HTML. Leaflet writes that string with `innerHTML`. A GPX file uploaded on an event is shown to every viewer who opens the waypoint popup, including guests. `_escape_html` escapes `&`, `<`, `>`, `"` and `'`, and treats `null` or `undefined` as an empty string, before that string is built. The markup stays a bold name, then a line break and the description when the description is non-empty, so ordinary text such as "Fuel stop" still renders bold, then a line break, then the description.

The rest of this file does not place GPX text in HTML. There is no other `bindPopup` and no `innerHTML`. Waypoint names and named track-point labels passed as marker `title` are Leaflet DOM title text, not HTML.

## Duration string quotes

`get_duration_string_iso` replaced `'` and `"` with `String.prototype.replace` string patterns, which change only the first match. CodeQL rule `js/incomplete-sanitization` flags that pattern. The call is now `.replace(/'/g, ':').replace(/"/g, '')`. Duration text this function builds is unchanged; each quote is replaced.

Reapply on upgrade.
