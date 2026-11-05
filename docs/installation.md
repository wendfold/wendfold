# Installation

## Recommended install path

For normal Joomla sites, install Wendfold using the suite package:

- `pkg_wendfold_v1.0.0.zip`

This is the only public installer. Download it from <https://wendfold.com/download>.

It installs the full suite in one shot while preserving Joomla-native extension boundaries under the hood.

## What the package installs

The package installer deploys:

1. `com_wendfold`
2. `plg_user_wendfold`
3. `plg_task_wendfold`
4. `plg_privacy_wendfold`

On a fresh package install, it enables the three companion plugins automatically so the suite is ready for normal setup work immediately after installation.

If you install the package over a site that already has some of the child extensions, the installer preserves the existing plugin enabled or disabled state instead of forcing a new state.

## Joomla install steps

1. Sign in to Joomla administrator.
2. Go to `System -> Install -> Extensions`.
3. Upload `pkg_wendfold_v1.0.0.zip`.
4. Wait for Joomla to finish installing the package and child extensions.
5. Open the Wendfold component and review configuration.
6. Go to the Plugin Manager if you want to inspect the companion plugins.
7. Go to `System -> Scheduled Tasks` and create task instances for any Wendfold task types you want to run automatically.

## Recommended first post-install review

After installation, review at least:

- component configuration and chapter defaults
- CSV import column mapping (see [Member roster CSV import](csv-import.md))
- access and permissions
- user/profile sync expectations
- reminder and email settings
- Scheduled Tasks cadence and ownership
- media folder permissions for uploads
- the Privacy option for OpenStreetMap map tiles (on by default; see `docs/privacy.md`)

## About the child ZIPs

The build also produces ZIPs for the component and the two plugins. They are internal build outputs: the package carries them inside it and installs them for you. They are never published on their own, so there is no separate download for them. To repair an install, reinstall the package.

## Important note about GitHub downloads

GitHub's automatic `Source code (zip)` and `Source code (tar.gz)` assets are source snapshots.
They are not installable Joomla extension packages.
