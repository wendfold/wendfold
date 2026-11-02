# Wendfold - Component

`com_wendfold` is the main Joomla component for Wendfold.

It is part of the Wendfold suite and is not intended to be the normal public install path by itself.
For normal installs and updates, use `pkg_wendfold_v1.0.0.zip` instead.

## Role in the suite

The component provides the core data model, administrator interface, and front-end workflows for Wendfold.

The companion plugins remain separate because they are the Joomla-native integration points for:

- user lifecycle events
- Scheduled Tasks automation

## Technical relationship to the package

Wendfold is one product presented publicly as one suite.
The package installer exists to make that suite install and update cleanly while preserving the correct Joomla extension split internally.

## When this child installer is useful

The component ZIP can still be useful for:

- local development
- controlled repair work
- advanced manual administration and testing

If you are manually installing child ZIPs, install in this order:

1. `com_wendfold_v1.0.0.zip`
2. `plg_user_wendfold_v1.0.0.zip`
3. `plg_task_wendfold_v1.0.0.zip`

## Supporting docs

- `../README.md`
- `../docs/installation.md`
- `../docs/updating.md`
- `../docs/package-vs-child-zips.md`
- `../CHANGELOG.md`
- `../docs/privacy.md`
- `THIRD-PARTY.md`

## License

GPL v2 or later. See `LICENSE.txt`.
