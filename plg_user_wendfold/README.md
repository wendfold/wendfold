# Wendfold - User Plugin

`plg_user_wendfold` is the companion Joomla user plugin for Wendfold.

It is part of the Wendfold suite and is not intended to be the normal public install path by itself.
For normal installs and updates, use `pkg_wendfold_v1.0.0.zip` instead.

## Role in the suite

This plugin handles Joomla-native user lifecycle integration for Wendfold.
It keeps Joomla user accounts and Wendfold member profiles aligned.

## Current behavior

### On Joomla user creation

- creates a new row in `#__wendfold_profiles` when no linked profile exists
- re-publishes the profile if a soft-deactivated profile already exists for the same `user_id`

### On Joomla user deletion

- does not remove the profile row
- soft-deactivates the linked profile by setting `published = 0`

## Dependency

Requires `com_wendfold`.

## Technical relationship to the package

Wendfold is one product presented publicly as one suite.
The package installer exists to make that suite install and update cleanly while preserving the correct Joomla extension split internally.

## When this child installer is useful

The plugin ZIP can still be useful for:

- local development
- controlled repair work
- advanced manual administration and testing

If you are manually installing child ZIPs, install in this order:

1. `com_wendfold_v1.0.0.zip`
2. `plg_user_wendfold_v1.0.0.zip`
3. `plg_task_wendfold_v1.0.0.zip`

Then enable the plugin if needed.

## Supporting docs

- `../README.md`
- `../docs/installation.md`
- `../docs/updating.md`
- `../docs/package-vs-child-zips.md`
- `../CHANGELOG.md`

## License

GPL v2 or later. See `LICENSE.txt`.
