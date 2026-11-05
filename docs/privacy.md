# Privacy

Wendfold keeps QR codes and map libraries on the site. A GPX ride map, and a kickstands-up marker map when that location has coordinates, can request OpenStreetMap tiles. That request is optional and documented here. No Google content loads with the page.

## Bundled libraries

These do not call out to a third-party host:

- **Check-in QR codes.** The administrator attendance page encodes the check-in URL in PHP and shows a 200 by 200 pixel image. The generator is the MIT-licensed qrcode-generator PHP file listed in [`com_wendfold/THIRD-PARTY.md`](../com_wendfold/THIRD-PARTY.md).
- **Map code.** Leaflet 1.9.4 and leaflet-gpx 1.7.0 are installed with the component under `media/com_wendfold/vendor/`. `media/com_wendfold/joomla.asset.json` registers them, and the event view loads them with the Web Asset Manager. Marker images and GPX pin images are in that same vendor folder.

Versions, licenses, and source URLs are in [`com_wendfold/THIRD-PARTY.md`](../com_wendfold/THIRD-PARTY.md).

## OpenStreetMap map tiles

Two local Leaflet maps can request tiles when this option is on:

- A ride event with a GPX file draws the route.
- A kickstands-up location that has latitude and longitude gets a marker. On a ride with no GPX file, that marker is in the route panel. Otherwise it is in the sidebar. With no coordinates, the page shows the address text only.

| | |
| --- | --- |
| Option | Components, Wendfold, Options, Privacy, **OpenStreetMap map tiles** |
| Parameter | `osm_tiles` |
| Default | On |
| Host | `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` |

When the option is on, the visitor's browser requests those tiles. The request includes the visitor's IP address and the map area (the tile coordinates). The event page shows `COM_WENDFOLD_EVENT_OSM_NOTICE` under the map.

Turn the option off and the GPX line is drawn on an empty background. The page shows `COM_WENDFOLD_EVENT_OSM_DISABLED`, and the route script does not add a tile layer. The kickstands-up marker map is omitted, so it does not request tiles either.

The option is in `com_wendfold/admin/config.xml`. The default in the manifest applies when the parameter has not been saved, including existing installs.

OpenStreetMap tile usage is covered by the [OpenStreetMap Foundation tile usage policy](https://operations.osmfoundation.org/policies/tiles/).

## Google Maps directions link

**Get Directions** on a kickstands-up address is an outbound link to `https://maps.google.com/maps`. The browser sends that address to Google Maps only when the visitor clicks the link. The link is not controlled by the OpenStreetMap tile option.

No Google content loads automatically. The event page does not embed a Google Maps iframe, and it does not request `maps.google.com` on page view.

## Privacy requests

The privacy plugin, **Privacy - Wendfold** (`plg_privacy_wendfold`), connects Wendfold to Joomla's privacy requests (`com_privacy`). It handles export requests and removal requests for a Joomla user.

The plugin must be enabled. A fresh package install enables it. A later package update that adds a companion plugin enables it. Plugins that were already installed keep their state, so a plugin an administrator disabled stays disabled; check **System → Manage → Plugins** for **Privacy - Wendfold** if you disabled it earlier.

### What an export contains

An export lists the member's Wendfold data in these domains:

- profile
- motorcycles
- officer terms
- attendance
- RSVPs
- ride-group memberships
- volunteer assignments
- trip participation
- activities
- metric entries
- member awards
- dues payments
- reminder log
- event role assignments
- ride-group role assignments
- sync log rows attributed to the member
- the renewal reminder opt-out on the Joomla user account

A user with no Wendfold profile gets only the renewal reminder opt-out.

### Sync log attribution

A sync log row belongs to the member only when the member performed the action, or when the row contains the member's exact Member ID as a whole word. A name never attributes a row. Names are redacted only inside rows that are already attributed to the member.

### What a removal anonymizes and deletes

A removal request anonymizes the member's data. It clears:

- on the profile: the user account link, Member ID, preferred name, emergency contact, shirt size, photo, notes, chapter on file, waiver version, sponsor, and road captain and road crew flags. The profile is unpublished, its member status is set to Left, and its renewal reminder flags (60-day and 30-day reminder sent) are cleared.
- motorcycle make, model, color, nickname, VIN, year, and notes
- officer notes, and the officer term is no longer current
- notes on attendance, ride-group memberships, volunteer assignments, member awards, and metric entries
- the member's Joomla user ID where it is recorded as the person who checked someone in to an event, or who assigned someone to a ride group
- trip lodging, arrival, and departure notes
- activity titles, descriptions, start and end locations, and motorcycle links
- dues notes, receipt numbers, and payment methods
- the member's event roles (road captain, sweep, volunteer manager, volunteer backup, trip lead, and trip sweep), ride-group road captain and sweep roles, and sponsor links from other profiles to this member
- the member's Member ID and names in attributed sync log rows, which become `[redacted]`
- the renewal reminder opt-out on the Joomla user account

It deletes the member's RSVPs and profile photo files.

### Officers

Removal is refused for a current officer. Joomla shows the reason: end or reassign the officer role first.

### What stays

After removal, the member's Wendfold profile row stays, without its user account link. Historical attendance, metrics, awards, and dues still count.

These also stay:

- the profile's membership and date fields: membership type, member since, chapter join date, renewal date, waiver signed date, and the dues paid and dues paid date fields
- the member's reminder log rows (which reminder was sent, and when). They are included in an export, but a removal does not delete or change them.

### Retention

Wendfold does not delete member data on a schedule. The lapsed-member scheduled task only unpublishes profiles, and deleting a Joomla user only unpublishes the linked profile. Member data stays on the site until a site administrator deletes it, a privacy removal request is completed (which anonymizes it as described above), or Wendfold is uninstalled.

Hosting and log retention for wendfold.com itself are covered by the [wendfold.com privacy policy](https://wendfold.com/privacy), not by this extension.

## Language strings

| Key | Where it appears |
| --- | --- |
| `COM_WENDFOLD_CONFIG_OSM_TILES_LABEL` | Option label |
| `COM_WENDFOLD_CONFIG_OSM_TILES_DESC` | Option description, including what the tile request sends |
| `COM_WENDFOLD_CONFIG_FIELDSET_PRIVACY` | Options fieldset |
| `COM_WENDFOLD_EVENT_OSM_NOTICE` | Under a map when tiles are on |
| `COM_WENDFOLD_EVENT_OSM_DISABLED` | Under the GPX map when tiles are off |
| `COM_WENDFOLD_EVENT_GET_DIRECTIONS` | Kickstands-up link to Google Maps |
