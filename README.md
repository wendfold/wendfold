# Wendfold

Wendfold is a chapter-management suite for Joomla 6. It is built for clubs, chapters, and member organizations, and covers members, officers, dues, events, rides, attendance, trips, volunteer staffing, and reports.

It ships as one Joomla package, `pkg_wendfold`. The package installs four Joomla extensions:

| Extension | Purpose |
| --- | --- |
| `com_wendfold` | The component: data model, administrator UI, and front-end views |
| `plg_user_wendfold` | User plugin: links Joomla user accounts to member profiles |
| `plg_task_wendfold` | Task plugin: Scheduled Tasks for reminders, lapsed-member cleanup, and award recompute |
| `plg_privacy_wendfold` | Privacy plugin: exports and anonymizes Wendfold member data for Joomla privacy requests |

## Requirements

- Joomla 6.x. Current stable is 6.2.0.
- PHP 8.3 or newer. CI tests on PHP 8.3, 8.4, and 8.5. Running a patched PHP (8.3.36, 8.4.27, 8.5.12 or later, which fix CVE-2026-109618 and CVE-2026-109619) is strongly recommended.
- MySQL 8.0.13 or later, or MariaDB 10.4 or later. PostgreSQL is not supported.

## Install

1. Download `pkg_wendfold_vX.Y.Z.zip` from <https://wendfold.com/download>.
2. Optional: check the download against `SHA256SUMS-vX.Y.Z.txt` from the same page:

   ```bash
   sha256sum -c SHA256SUMS-vX.Y.Z.txt --ignore-missing
   ```

3. In the Joomla administrator, open **System → Install → Extensions** and upload the package ZIP.
4. Review the component options. To turn on automation, create Scheduled Task instances for the Wendfold task types.

On a fresh install, the package enables the three companion plugins. If you install it over existing child extensions, it keeps their current enabled or disabled state.

The package is the only installer that is published. The build also produces child ZIPs (`com_wendfold_*`, `plg_user_wendfold_*`, `plg_task_wendfold_*`, `plg_privacy_wendfold_*`), but they are internal build outputs that the package carries inside it. They are never published on their own, on wendfold.com or on GitHub.

GitHub's automatic "Source code" archives are repository snapshots. They are not installable Joomla packages.

For more detail, see [`docs/installation.md`](docs/installation.md), [`docs/updating.md`](docs/updating.md), and [`docs/package-vs-child-zips.md`](docs/package-vs-child-zips.md).

## Uninstall

Uninstall `pkg_wendfold` from **System → Manage → Extensions**. Joomla removes the component and the three companion plugins with the package.

Uninstall deletes all Wendfold database tables and their data. It also removes `media/com_wendfold`, including GPX uploads, the Wendfold scheduled tasks, and the empty Chapter groups Wendfold created. Uploaded files in `images/wendfold/` (member photos and location images) stay on disk unless you set **Remove uploaded files** to Yes on the **Uninstall** tab of the Wendfold Options before uninstalling. Site menu items, Chapter groups that still have members, the `com_wendfold.php` log file, and shared `images/profiles` or `images/locations` folders also stay. Remove leftover files in the Media Manager if you no longer need them.

Back up the database before uninstalling if you need to keep chapter data.

## Build from source

You need Python 3.9 or newer. The build uses only the standard library. Until the repository is public, cloning needs repository access.

```bash
git clone https://github.com/wendfold/wendfold.git
cd wendfold
python3 build/build_release.py
cd build/dist && sha256sum -c SHA256SUMS.txt
```

`build/build_release.py` reads `<version>` from every manifest and stops if they differ. It writes these files to `build/dist/`:

- `pkg_wendfold_vX.Y.Z.zip`: the installable package, with the four child ZIPs under `constituents/`
- `com_wendfold_vX.Y.Z.zip`, `plg_user_wendfold_vX.Y.Z.zip`, `plg_task_wendfold_vX.Y.Z.zip`, `plg_privacy_wendfold_vX.Y.Z.zip`: internal build outputs, not published
- `SHA256SUMS.txt`: internal checksums for all five ZIPs. A release publishes only the package ZIP, with `SHA256SUMS-vX.Y.Z.txt` (see [`RELEASING.md`](RELEASING.md)).

Each ZIP has its manifest at the ZIP root. The suite update feed is `updates/pkg_wendfold.xml` and is not packed into the ZIPs. `build/dist/` and `build/staging/` are git-ignored.

For coding standards, CI, and pull requests, see [`CONTRIBUTING.md`](CONTRIBUTING.md). For how releases get cut, see [`RELEASING.md`](RELEASING.md).

## Awards

Wendfold can grant awards to members from metric totals, membership years, or other awards. See [`docs/awards.md`](docs/awards.md).

## Support

- **Help, bug reports, and feature requests:** email support@wendfold.com or see <https://wendfold.com/support>.
- **GitHub Issues:** will be available as an additional channel once the repository is public. Until then, please use email.
- **Security vulnerabilities:** please don't report them in public. Use GitHub private vulnerability reporting (the repository's **Security** tab, **Report a vulnerability**), or email security@wendfold.com. See [`SECURITY.md`](SECURITY.md).

See [`SUPPORT.md`](SUPPORT.md) for what to include in a report.

## Privacy

The privacy plugin handles Joomla privacy export and removal requests for Wendfold member data. QR codes and the Leaflet map libraries are bundled with the component. A GPX ride map, and a kickstands-up marker map when that location has coordinates, can still request OpenStreetMap tiles. That option is on by default. Google Maps is a click-only directions link. See [`docs/privacy.md`](docs/privacy.md) and [`com_wendfold/THIRD-PARTY.md`](com_wendfold/THIRD-PARTY.md).

## License

Copyright (C) 2026 Adan Lozano

Wendfold is free software under the GNU General Public License, version 2 or (at your option) any later version (`GPL-2.0-or-later`). See [`LICENSE.txt`](LICENSE.txt).

Joomla! is a trademark of Open Source Matters, Inc. Wendfold is not affiliated with or endorsed by the Joomla! Project or Open Source Matters.
