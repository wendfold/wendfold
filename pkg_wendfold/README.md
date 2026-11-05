# Wendfold - Package Installer

`pkg_wendfold` is the primary public installer for the Wendfold suite.

For normal Joomla installs and updates, use this package ZIP rather than the child extension ZIPs directly.

## What the package installs

The package installs the full Wendfold suite:

1. `com_wendfold`
2. `plg_user_wendfold`
3. `plg_task_wendfold`

On a fresh suite install, the package enables the two companion plugins automatically.
If the package is installed over an existing site that already has one or both child extensions, it preserves the current enabled or disabled state for those plugins.

## Why this ZIP is the normal install path

Wendfold is one product presented publicly as one suite.
The package keeps that suite-first experience intact while preserving the correct Joomla-native component/plugin split internally.

## Supporting docs

- `../README.md`
- `../docs/installation.md`
- `../docs/updating.md`
- `../docs/package-vs-child-zips.md`
- `../CHANGELOG.md`

## License

GPL v2 or later. See `LICENSE.txt`.
