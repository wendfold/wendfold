# Wendfold - Task Plugin

`plg_task_wendfold` is the companion Scheduled Tasks plugin for Wendfold.

It is part of the Wendfold suite and is not intended to be the normal public install path by itself.
For normal installs and updates, use `pkg_wendfold_v1.0.0.zip` instead.

## Role in the suite

This plugin exposes Joomla Scheduled Task types that automate recurring Wendfold maintenance work.

## Included task types

- `wendfold.lapsed_cleanup`
  - unpublishes member profiles after the configured grace period when they should no longer remain active
- `wendfold.reminder_emails`
  - sends renewal reminder emails
- `wendfold.event_reminders`
  - sends day-of event reminders to members who RSVP'd
- `wendfold.award_recompute`
  - recomputes configurable awards against current metric and membership data

Task runs write summary output to `#__wendfold_sync_log`.

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

Then enable the plugin and create task instances as needed.

## Supporting docs

- `../README.md`
- `../docs/installation.md`
- `../docs/updating.md`
- `../docs/package-vs-child-zips.md`
- `../CHANGELOG.md`

## License

GPL v2 or later. See `LICENSE.txt`.
