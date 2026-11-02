# Dev tools (not shipped)

These files live outside every extension folder and are **not** included in
`pkg_wendfold` or any child ZIP built by `build/build_release.py`.

## Demo data seed

Populate a fresh Joomla test install with fictional chapter data for screenshots
and demos:

```bash
php dev/seed-demo.php --site=/path/to/joomla
php dev/seed-demo.php --site=/path/to/joomla --reset
php dev/seed-demo.php --site=/path/to/joomla --password='your-shared-secret'
php dev/seed-demo.php --site=/path/to/joomla --reset --password='your-shared-secret'
```

Requirements:

- Joomla site root passed via `--site=`
- `com_wendfold` installed and enabled
- Run from any working directory; the script bootstraps that site

By default every demo user shares one strong random password generated for that
run (printed once in the summary). Pass `--password=` (at least 12 characters)
for a repeatable value across screenshot sessions. `--reset` reseeds with the
same rule: random unless `--password` is given.

`--reset` removes only rows created by a previous run of this script (demo
usernames with an exact `wfdemo_` prefix, `DEMO-` member numbers, `demo-seed-`
event aliases, rows linked by those roots or by `created_by` on a demo user,
and — for older seeder versions — rows whose notes/description/note field
equals `WENDFOLD_DEMO_SEED` exactly), then seeds again. A second run without
`--reset` does nothing (no duplicates). The current seeder never writes that
marker into user-visible description or notes fields.
