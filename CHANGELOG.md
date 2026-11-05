# Changelog

All notable changes to Wendfold are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Versions apply to the whole suite: `pkg_wendfold`, `com_wendfold`,
`plg_user_wendfold`, `plg_task_wendfold`, and `plg_privacy_wendfold` always
share one version number.

The public changelog (`updates/changelog.xml`, published at
<https://wendfold.com/updates/changelog.xml>, and <https://wendfold.com/changelog>)
is generated from this file by `scripts/release/generate-changelog.py`. Bullets
tagged `<!-- internal -->` are for maintainers: they stay here but are left out of
the public changelog.

## [Unreleased]

## [1.0.0] - 2026-11-02

### Added

- Wendfold suite package `pkg_wendfold` for Joomla 6. It installs the Wendfold
  component (`com_wendfold`), the Wendfold user plugin, the Wendfold task
  plugin and the Wendfold privacy plugin in one step.
- Fresh package installs enable the companion plugins. Installing over existing
  Wendfold extensions keeps their enabled or disabled state.
- Joomla update notifications for the whole suite from one update server,
  <https://wendfold.com/updates/pkg_wendfold.xml>. Joomla shows this changelog
  in System > Update > Extensions and in System > Manage > Extensions.
- Downloads from the GitHub release: the package
  <https://github.com/wendfold/wendfold/releases/download/v1.0.0/pkg_wendfold_v1.0.0.zip>
  and its checksum file `SHA256SUMS-v1.0.0.txt`. Joomla updates download the
  same file. The <https://wendfold.com/download> page links to it.
- Component option **OpenStreetMap map tiles** (on by default) and a short
  notice on GPX ride maps. The same option covers a local kickstands-up
  marker map when that location has coordinates. Turning it off draws the
  route without requesting tiles and omits the marker map. See
  `docs/privacy.md`.
- Generic member-roster CSV import. Column headers are mapped in component
  options (CSV Import) and stored in component parameters. The identifier
  column defaults to `Member ID` and also accepts `member_id`, `memberid`,
  `Member Number`, and `membernumber`. The sync screen can preview detected
  headers and the resolved mapping. Comma, semicolon and tab delimiters and a
  UTF-8 byte order mark are detected. Invalid dates, email addresses or phone
  numbers, a missing identifier column, or an email address that another
  account already uses reject the whole file without changing existing rows.
- One package update server for the suite, served at
  `https://wendfold.com/updates/pkg_wendfold.xml` from `updates/pkg_wendfold.xml`,
  with a Joomla changelog at `updates/changelog.xml`. The child extensions don't
  declare their own update servers. <!-- internal -->
- Public repository scaffolding: README, contributing guide, code of conduct,
  security policy, release process, issue and pull request templates. <!-- internal -->
- Continuous integration on PHP 8.3, 8.4, and 8.5: PHP security floor, PHP lint,
  PHPCS, XML well-formedness checks, release build, checksum
  verification, and ZIP layout checks. <!-- internal -->
- Tag-triggered release workflow. It builds the ZIPs, verifies `SHA256SUMS.txt`,
  writes the package sha256 into the update XML, builds the wendfold.com
  site-upload bundle (`downloads/pkg_wendfold_vX.Y.Z.zip`,
  `downloads/SHA256SUMS-vX.Y.Z.txt`, `updates/pkg_wendfold.xml`, and
  `updates/changelog.xml` when present), and creates a draft GitHub release, which is
  the package source once published. The draft carries only `pkg_wendfold_vX.Y.Z.zip`,
  `SHA256SUMS-vX.Y.Z.txt`, `pkg_wendfold.xml`, and the bundle ZIP; a guard step
  fails the job if a child ZIP or any other file is staged. The workflow never
  publishes the release or uploads anything to wendfold.com. <!-- internal -->
- `scripts/release/generate-changelog.py` builds `updates/changelog.xml`, the
  wendfold.com changelog page text and the draft GitHub release notes from a
  `CHANGELOG.md` version section, leaving out bullets tagged `<!-- internal -->`.
  The release workflow does not copy the section raw into the release
  notes. <!-- internal -->
- CI brand guard: each `CI (PHP x.y)` job fails if a tracked text file contains
  a legacy product name, and prints only `file:line` for each hit. <!-- internal -->
- Administrators can manage membership types, officer roles, and event types,
  including their order and publish state. Events, officers, and members use
  these lookups, and a lookup that is still in use cannot be deleted.
- Generic participation metrics. Members log
  activities on the site, and officers can approve, unapprove, or reject them.
  When any metric on an activity needs approval, the activity stays pending
  until it is approved. Event credits add metric entries for attendance.
- **My activities** site menu item type. A logged-in member sees only their
  own activity entries, newest first, with Date, Metric, Value, Notes, and
  Approval (Pending, Counted, or Rejected) columns and page navigation. A
  **Counted totals** list shows each published metric's all-time total from
  counted entries up to today, and metrics with no counted total are left out.
  Guests are sent to the login page and return to My activities after logging
  in. An account without a published member profile is sent to the dashboard
  with "No member profile was found for your account." The member dashboard
  links to the page.
- Configurable awards. Administrators define awards with
  criteria, periods (lifetime, calendar year, rolling, or custom), match-any or
  match-all rules, tiers, and prerequisites. Five default awards are seeded.
  The Member Awards screen supports manual grants, locks, and a presented date.
  The site shows an awards catalog with progress, and the dashboard and profile
  show earned awards. Awards are recalculated by the nightly
  **Wendfold: Award Recompute** task, the **Recalculate** button, and after
  metric changes.
- Members can opt out of renewal reminders, on the administrator member form and
  on their own profile edit page.
- Awards guide (`docs/awards.md`), including how prerequisites affect
  calendar-year awards and the recompute window.
- Release ZIPs built from the same commit are byte-identical, with sorted
  `SHA256SUMS.txt`, and CI checks that the build is reproducible. <!-- internal -->
- Privacy plugin `plg_privacy_wendfold` (**Privacy - Wendfold**) for Joomla's
  privacy requests. A data export includes the member's Wendfold data: profile,
  motorcycles, officer terms, attendance, RSVPs, groups, volunteering, trip
  participation, activities, metric entries, awards, dues, reminder log, event
  and group roles, sync log rows attributed to the member, and the renewal
  reminder opt-out. A sync log row is attributed only by who performed it or by
  an exact Member ID match, never by name.
- A privacy removal request anonymizes the member's personal fields, deletes
  their RSVPs and profile photo files, clears event role and sponsor links,
  redacts their attributed sync log text, and clears the reminder opt-out.
  Removal is refused, with a reason, for a current officer.
- Cancelling an event emails members whose RSVP is Yes or Maybe that the event
  is cancelled, with a link to the public event page. Members who opted out of
  reminders are not emailed. The notice is sent once, only after the event is
  actually saved as Cancelled, including from the administrator event
  form.
- Membership types, officer roles, event types, dues payments, and motorcycles
  are checked out while an administrator edits them, like events, members,
  officers, locations, metric definitions, and award definitions. The lists for
  events, members, officers, locations, metric definitions, award definitions,
  membership types, officer roles, event types, and dues show the checked-out
  lock and have a Check-in toolbar button.
- The database schema is managed through Joomla's versioned SQL. Joomla records
  schema version 1.0.0 for Wendfold, and the installer does not change tables
  during install or update.
- Installing or updating Wendfold requires MySQL 8.0.13 or later or MariaDB
  10.4 or later. The package and the component check the database before any
  SQL runs. Any other database, including PostgreSQL, is refused with
  "Wendfold requires MySQL 8.0.13+ or MariaDB 10.4+. %s is not supported.",
  and an older MySQL or MariaDB is refused with "Wendfold requires MySQL
  8.0.13+ or MariaDB 10.4+; this site runs %s.", where `%s` names the
  database server, for example PostgreSQL, Microsoft SQL Server, or SQLite,
  followed by its version when Joomla reports one.
  A fresh install seeds the
  default metrics and awards, and those rows stay editable and deletable. The link
  from a member profile to a Joomla account can be empty, so the profile history
  can be kept after the account is removed.
- Each event stores an alias, shown beside the title. Leave it blank and it is
  generated from the title. A generated alias, and Save as Copy, gets a numeric
  suffix when another event already uses it, including an unpublished or trashed
  event. An alias you type is refused when another event already uses it,
  including a trashed event.
- Event links use the alias stored on the event. Two events can share a title
  and each keeps its own address. A path segment that exactly matches a stored
  alias opens that event, even when the alias is only digits. A segment that is
  only digits opens the event with that id only when no event stores that alias.
- Check-in QR codes are generated on the server with a bundled PHP library.
- Kickstands-up maps do not load Google Maps on page view. The **Get
  Directions** link sends the address only when the visitor
  clicks it. With OpenStreetMap tiles on and coordinates present, a local
  Leaflet marker is shown.
- The events list, RSVP cut-off, QR self check-in, and scheduled reminder windows
  use the site timezone from the Joomla global configuration. A site west of UTC
  does not treat the evening as the next day.
- Chapter user group ids, the dues amount, the waiver version, and the renewal
  reminder lead times are stored in component options. The six group options
  are user group lists. Install and update keep a saved group that still
  exists, replace one that is missing, and repair the user-group tree.
  Uninstall removes only empty groups that Wendfold created.
- Member photos uploaded on the profile edit page are stored in
  `images/wendfold/profiles`. Install and update create
  `images/wendfold/profiles` and `images/wendfold/locations`, and the image
  fields on the location and event forms open in `images/wendfold/locations`.
- Uninstall deletes every Wendfold database table and its data, removes
  `media/com_wendfold` including GPX uploads, deletes the Wendfold scheduled
  tasks, and removes the empty Chapter groups Wendfold created. Uploaded files
  in `images/wendfold/` are kept unless **Remove uploaded files** is set to Yes
  on the **Uninstall** tab of the Wendfold Options (default No). Site menu
  items, Chapter groups that still have members, the `com_wendfold.php` log
  file, and shared `images/profiles` or `images/locations` folders stay.
  Uninstall completes and removes the extension.
- Renewal reminders catch up to the nearest due stage, are sent at most once per
  member per run, and skip life or exempt, blocked, unpublished, and opted-out
  members. Once a nearer stage is sent, farther stages for that renewal cycle
  are not sent. The first lead time must be more than 10 days before the
  second.
- Event reminders go only to members who answered Going or Maybe, and skip
  unpublished and trashed events.
- Attendance credits follow every event state change. Credits for an
  unpublished event count as pending, archived events still count, and
  trashed events don't count. Restoring an event from the trash restores its
  credits.
- Deleting an event deletes its attendance, RSVPs, groups, volunteers, trip
  participants, event credits, and metric entries in the same transaction. A
  batch delete continues after a failed event and reports each failure.
- Site roll call and road captain attendance work for archived events.
  Unpublished events show "This event is not published."; trashed and missing
  events stay blocked.
- Event Status includes Archived and Trashed, so editing a trashed event does
  not republish it. Event saves, including volunteer roles and credits,
  run in one transaction, and failed saves show a generic error with the
  detail written to the component log.
- Event type labels in reports and lists come from the event type lookup.
  A missing or unknown type shows "General" on the site. Attendance roster,
  awards, and sync screens use language strings for their labels.
- The members list column header uses the configured member number label, or
  "Member Number".
- The Delete toolbar button on events, members, locations, dues, officers, and
  award lists is shown only to users with Delete permission.
- Email blasts are sent through Joomla's mailer factory, and use no deprecated request
  input calls. Motorcycle Save and Cancel return to the member
  edit page.
- Award recompute is serialized per award and runs after the surrounding save
  is committed. Revokes are lock-safe, and removing a check-in updates awards
  straight away.
- An event that has check-ins can't be cancelled until the check-ins are
  removed, including when saving Cancelled from the administrator event form.
- Check-ins on a Cancelled event are refused, and existing credits on Cancelled
  events don't count toward totals. Meeting roll-call add and remove stay
  allowed on Completed events and are refused only when Cancelled.
- Concurrent publish, delete, and attendance changes on the same event are
  serialized, and award recomputes for attendance and event credit changes run
  only after the change is saved.
- Reminder email send-failure log entries identify the member by profile id,
  not the recipient's email address.
- Mail send-failure log entries for renewal reminders, event reminders, event
  cancellation notices, email blasts, and user plugin notifications replace
  email addresses in the mailer error text with `[redacted]` before logging,
  and keep the profile and event ids. The raw mailer error is not
  logged.
- Package updates enable companion plugins that are new in that version.
  Plugins already installed keep their enabled or disabled state, so
  a plugin an administrator disabled stays disabled. Fresh installs enable
  every companion plugin.
- Administrator list publish, unpublish, archive, trash, and delete messages
  count only the items that actually changed. Items already in that state are
  not counted, and items that are missing, checked out by another user, or not
  permitted count as failed. When every selected item is already in that state,
  the message is "No items needed changing".
- Only one CSV roster import runs at a time. A second import started while
  one is running is refused with "An import is already running". During the
  import, a member row changed or removed after the preview is skipped
  with a row warning, and the chapter-switch log lists only the rows it
  applied.
- Email Blast sends to the audience chosen on the compose form (all members,
  dues paid, dues unpaid, renewing within 60 days, or officers). The recipient
  preview and the send use the same audience.
- The Options button on the members list is shown to users who can change the
  component options, matching Joomla's own toolbar check.
- Uploading a member roster CSV completes without a server error, and the
  temporary copy of the file is deleted after every upload.
- Administrator edit forms show each field's help text under the field on the
  event, member, location, ride, motorcycle, officer, and dues payment screens.
- Event and trip links open with search-engine-friendly URLs on. The site
  router uses the active menu item, then removes each path segment it has
  matched, and Joomla treats anything left over as a missing page. The event
  list, an event or trip opened by its number or its name, a stop on a trip,
  and the other event pages are covered, with or without an events menu item.
  A check-in link under its menu item opens the check-in page. The member
  pages that use the same routing are covered too.
- A new meeting keeps the audience and workflow chosen on the form, including
  All Visitors and Standard RSVP. The meeting type suggests an audience, a
  workflow, and an RSVP setting, and each suggestion is used only when that
  field is not sent with the save. An audience that is not exactly one of the
  four choices, including a blank value, is refused on every save, including
  edits and events that are not meetings, and the save shows a validation
  error and stores nothing.
- An Allow RSVP value that is not exactly 0 or 1, or a Workflow Mode that is
  not exactly 1, 2, 3, 4, or 5, is refused on every event save. A blank value
  is refused as well. The save shows a validation error for that field and
  leaves the stored row unchanged. An edit that leaves either field out keeps
  the stored value. A new meeting uses the meeting type's workflow and RSVP
  suggestions when those fields are not sent, and a new ride, rally, social,
  other event, or overnight trip uses its usual workflow and RSVP default.
- Date-only fields (payment, member, officer term, and ride dates) keep their
  calendar day on a site that is not UTC. Date-only values and last activity
  show on the viewer's calendar day, and kickstands-up times, including the
  day-of reminder, stay on the stored wall clock.
- The Sync screen **Run Lapsed Cleanup** button runs the cleanup, which
  unpublishes members whose renewal is more than 30 days past. **Keep** and
  **Remove from Roster** act only on the submitted member while that member is
  still flagged for a chapter transfer, and the review list shows every
  flagged member. Remove from Roster unpublishes the member and clears the
  flag.
- The administrator Activities list opens without a server error, and
  approving an activity returns to a working list.
- Check-in on a trashed event is refused on every check-in path.
- A repeated check-in, including a double tap and a duplicate administrator
  check-in, shows the "already checked in" notice.
- Metric keys must use lowercase letters, digits, and underscores. Event credit
  and activity values must be plain non-negative numbers within the metric's
  decimals. Event credits are checked before the event is saved.
- Profile edit saves a motorcycle row only when it has data. Motorcycle changes
  are limited to the member's own rows, saved together with the profile, and the
  year and VIN are checked with specific messages. Existing longer VINs are
  kept.
- The attendance **Add Member** list shows every member's name and raises no
  PHP 8.5 deprecation warnings.
- Completing or cancelling an event that is already closed leaves it unchanged.
  A complete or cancel that doesn't take effect while the event is open
  reports a failure, and re-applying the current ride status is a successful
  no-op.
- Road captain complete, cancel, and ride-status changes on an already closed
  event show "This event is already completed or cancelled".
- Scheduled task runs and command-line runs write Wendfold failure lines to
  the same `com_wendfold.php` log file as web requests. This covers the
  Wendfold task plugin's renewal reminder and lapsed-member cleanup tasks,
  event and renewal reminders, award recompute, and the Wendfold user
  plugin.
- The new-member notice that the Wendfold user plugin emails to the
  administrator is translated when the Joomla account is created from the
  command line, so it shows text, not raw `PLG_USER_WENDFOLD_` language keys.
- Road captain manual check-in reports "That member is already on the roster"
  after a duplicate-key failure only when the member's attendance row is still
  there after the rollback. Otherwise it shows "Unable to complete the requested
  action. Please try again."
- Lookup titles on the site that use administrator language strings show
  their translated text.
- Saving a dues payment for a member and year that already has one shows "A
  payment for this member and year already exists". Saving a member with a
  member number or Joomla user that another member already uses shows a
  field-specific message, and any other duplicate shows a general one. A CSV
  import that hits a duplicate value stops, changes nothing, and names the CSV
  row.
- RSVPs can't overfill an event when members answer at the same time. The RSVP
  form sends whether the member is going, the event is locked while capacity is
  re-counted, and sending the same answer twice changes nothing.
- Volunteer sign-up and board assignment lock the event and the role, then
  re-count filled slots, so a role can't go over its slots and a member can't
  hold two active roles. A board assignment to a full role is refused.
- Ride groups get their order under the event lock, so two groups never share a
  number. Adding a group retries once on a duplicate group number and reports a
  database error if the retry also fails. Placing a rider in a different group
  moves them, so each rider has one group row. Creating, adding, assigning, and
  saving groups report a database error and change nothing when the save fails,
  never success.

- The Wendfold W-fold mark appears in the administrator toolbar titles and on
  the Components menu entry, and the mark and extension icon ship in the
  component media.
- The administrator attendance roster shows each member's waiver status from
  their signed waiver, so a member with a signed waiver is not shown as NO
  WAIVER.
- Kickstands-up and kickstands-down times are labelled Departure and Return,
  with "Kick Stands Up" and "Kick Stands Down" as an accessible tooltip. The
  check-in label is translatable, and the page header does not overwrite the
  page's own item data, such as the trip status event.
- Metric amounts use singular and plural unit language strings and leave out a
  unit that repeats the metric label, on the site dashboard, My activities, the
  activity log, and the administrator Activities list. Member dashboard metric
  cards keep informative units, such as Distance (miles).
- My activities, the administrator Activities list, and the activity log show a
  title for every metric entry, taken from its activity, attendance, volunteer,
  or manual source, instead of an em dash. Notes stay in their own
  column.
- Event and trip teasers keep words apart across paragraphs and list items,
  decode entities before escaping once, and an event Description left empty
  in the editor shows the empty state, not a blank teaser.
- The development demo seeder (`dev/seed-demo.php`) writes screenshot-ready
  data without a visible seed marker: mixed waivers, awards, a check-in event
  today, kickstands times only on rides, Weekend Group Ride on the next
  Saturday after the reference date, and Saturday Morning Ride on the
  Saturday before it. `--reset` also removes rows from earlier seeder
  versions. It is not part of the package. <!-- internal -->

### Changed

- Component schema changes are applied through Joomla's versioned SQL.
  `install.mysql.utf8.sql` creates the full 1.0.0 schema, and
  `sql/updates/mysql/1.0.0.sql` is the schema-version baseline recorded
  in `#__schemas`. The component installer does not run `ALTER TABLE`
  during install or update. <!-- internal -->
- The GitHub release is the single package source. Each release is published as
  `https://github.com/wendfold/wendfold/releases/download/vX.Y.Z/pkg_wendfold_vX.Y.Z.zip`
  with `SHA256SUMS-vX.Y.Z.txt` beside it, and the update XML's `<downloadurl>`
  points there. The release workflow fails if the `<downloadurl>` for the tagged
  version is anything else. wendfold.com keeps the update feed and the download
  page. <!-- internal -->
- PHPCS checks the Joomla coding standard, using
  `joomla/coding-standards` 3.0.0-alpha and PHP_CodeSniffer 3.13.6 pinned through
  Composer. On pull requests, each `CI (PHP x.y)` job fails on any PHPCS error
  in the PHP files the pull request adds or changes, outside `tmpl/` and
  `vendor/`; warnings don't fail it. The full-tree PHPCS report stays
  report-only. <!-- internal -->
- Public docs send downloads to <https://wendfold.com/download> and support to
  support@wendfold.com and <https://wendfold.com/support>. Security reports go to
  GitHub private vulnerability reporting, with security@wendfold.com as the fallback. <!-- internal -->
- Releases are package-only: `pkg_wendfold_vX.Y.Z.zip` is the only installer that
  is published. The child ZIPs and the build's `SHA256SUMS.txt` are internal build
  outputs and are not attached to the draft GitHub release. <!-- internal -->
- Leaflet 1.9.4 and leaflet-gpx 1.7.0 ship under `media/com_wendfold/vendor`
  and load through the Web Asset Manager, including marker images. <!-- internal -->
- Wendfold Chapter groups are created through Joomla's user group table, and
  the user group tree is rebuilt after install, update, and uninstall, so
  each group sits under its configured parent. <!-- internal -->
- The component installer gets its database connection from Joomla's
  dependency injection container and writes its warnings to the
  `com_wendfold.php` log. A source scan test over the package, the component,
  and the three plugins fails the build if `Factory::getDbo()`,
  `Factory::getMailer()`, another deprecated `Factory` helper, application
  `->input` property access, or `finfo_close()` appears. The
  `#__wendfold_member_awards` table has no `acknowledged_at` or
  `acknowledged_by` columns. <!-- internal -->
- CI's language key scanner also checks `plg_privacy_wendfold`, so a missing
  `PLG_PRIVACY_WENDFOLD_*` en-GB key fails the build. <!-- internal -->
- The roster upload copies the CSV to Joomla's `tmp_path`, falling back to
  `sys_get_temp_dir()`, and `SyncController` does not call `finfo_close()`,
  which is deprecated in PHP 8.5. <!-- internal -->
- `scripts/release/set-update-sha256.py` replaces a self-closing `<sha256/>`
  or `<sha256 />` in place, so an entry gets one `<sha256>`. It stops with
  an error if the entry already has more than one `<sha256>`, and checks that the
  result has exactly one, equal to the package ZIP's hash. <!-- internal -->
- Joomla 6.2 deprecation cleanup:
  - Database queries are built with `$db->createQuery()`; shipped code has no
    `getQuery(true)` calls.
  - Views read model data through explicit model getters, and list and filter
    state is read through the `StateValue` helper.
  - Query limits are set with `$query->setLimit()`.
  - The component and package installer scripts return a service provider that
    registers an `InstallerScriptInterface` implementation.
  - The task, user, and privacy plugin providers construct the plugin with its
    config only.
  - The only Joomla 6.2 deprecation notices left come from core's
    `PrivacyPlugin::$db` property, which the Wendfold privacy plugin inherits.
  - `DeprecatedApiCleanupTest` fails the build if any of these patterns
    return. <!-- internal -->

### Security

- The administrator event Description uses the Joomla editor and is filtered by
  each user group's text filters from Global Configuration. Site event and trip
  pages show the filtered HTML. Plain-text event, dues payment, and location
  notes and volunteer role text are filtered as plain text on save, so they
  don't store raw HTML.
- Events on the site follow one audience rule. Guests and signed-in accounts
  without a published member profile see only All Visitors events. Members see
  All Visitors and Logged-in Members events. Officers see every scope. Road
  captains see All Visitors, Logged-in Members, and Road Captains Only events.
  Any other scope value stays hidden. RSVP, My RSVPs, the member dashboard,
  and check-in use the same rule. A QR check-in token still checks the person
  in, but a restricted event shows a generic confirmation without its title,
  date, location, description, or route. GPX uploads are stored under a
  random file name. The route map and download are available only to someone
  who can open that event.
- Administrator member saves do not accept photo uploads. Members manage
  their own photo on the front end, and the admin member form shows the photo
  read-only. A posted member id that does not match the record being saved
  is refused.
- Scheduled renewal and event reminder tasks don't check the permissions of
  whoever is logged in when Joomla's scheduler runs them during a page view, so
  a visitor without `wendfold.emailblast` can't make a due run skip its mail.
  Who may run a task stays `com_scheduler`'s permission. <!-- internal -->
- Every update entry carries the SHA-256 checksum of the package ZIP, and the Joomla
  updater refuses a download that doesn't match it.
- Every Wendfold action that changes data, in the administrator and on the
  site, runs only from a submitted form (HTTP POST) with a valid security
  token. Removing attendance, completing or cancelling an event, and road
  captain attendance changes don't work from a link, and the email
  recipient preview doesn't run from one either.
- Email Blast, its recipient preview and the manual renewal reminder send need
  the **Send email blast** permission (`wendfold.emailblast`). Install and
  update grant it to the Super Users and Administrator groups only. Other
  groups can be given it on the **Permissions** tab in the Wendfold
  options. Without it, the Email Blast menu item still shows but opening it
  gives a permission error.
- Attendance, roster sync and Member Awards actions check Joomla's
  Create, Edit, Edit State and Delete permissions for the component, as well as
  access to it.
- Member names on the administrator attendance roster cannot inject
  script or skip the confirmation prompt before an attendance change.
- A member name or a translated sentence cannot run script from a
  confirmation prompt. Meeting roll call removal, cancelling an RSVP, sync
  lapsed-member cleanup, sync roster removal, the member award lock and
  unlock prompts, and the Member Awards **Recalculate** toolbar button ask
  through an escaped confirmation attribute. Recalculate runs only from a POST
  request with a valid security token.
  The meeting removal prompt includes that member's name. The check-in welcome
  line escapes the signed-in member's name.
- Check-in QR codes and map scripts don't come from outside services. The
  check-in link is not sent to a third-party QR code service, and the map
  libraries are not loaded from public CDNs.
- Administrator CSV downloads for the member status report, the chapter activity
  report, and the attendance trends report prefix a text cell with a tab when it
  starts with `=`, `+`, `-`, `@`, a tab, a carriage return, a line feed, or a
  full-width formula character, and quote that field, so a spreadsheet does not
  run it as a formula. Counts, miles, and other real numbers stay numbers. The
  shared writer passes the separator, quote, and escape to `fputcsv()`, which
  removes a PHP 8.4 deprecation on those downloads. The officer form controller
  accepts a null factory, and the profile photo check does not call
  `finfo_close()`, which PHP 8.5 deprecates. The bundled QR code file
  stops when it is opened outside Joomla. The member status download takes the
  application from the controller, so that export writes the file.
- Every state-changing component task checks for an HTTP POST before the CSRF
  token, because Joomla accepts a matching `X-CSRF-Token` header on any request
  method. Administrator attendance confirmations use an escaped `data-confirm`
  attribute read by `media/js/confirm.js`, not inline `onclick` handlers. `core.edit.state` and `wendfold.emailblast` are
  declared in `access.xml`. <!-- internal -->
- CI and release builds update PHP to the latest patch release and fail if PHP is
  below the security floor for CVE-2026-109618 and CVE-2026-109619: 8.3.36,
  8.4.27, or 8.5.12. <!-- internal -->
- Route map popups escape GPX waypoint names and descriptions, so text from an
  uploaded GPX file cannot run script in the popup.

### Language

- English (en-GB) language files for the package, the component (site and
  administrator) and the plugins.

### Notes

- Child extensions installed by the package can't be uninstalled individually.
  Uninstalling the package removes the whole suite.
- Requires Joomla 6 and PHP 8.3 or later.
- Install the package ZIP only. The component and plugin ZIPs are internal build
  outputs that the package carries inside it. They are never published on their
  own, so there is no separate download for them. To repair an install, reinstall
  the package.
- Upgrading from a pre-1.0 development build is not supported. 1.0.0 is the
  first release, so install it fresh: uninstall any pre-release (alpha or beta)
  build and reinstall the package. The only supported upgrade path starts at
  1.0.0.
- Schedule **Wendfold: Award Recompute**, and run **Recalculate** once after
  install or a bulk metric import. Locked and manually granted awards are never
  changed by recompute.
- Uninstall deletes only empty user groups that Wendfold recorded creating.
  Groups an option points at that Wendfold did not create are left for an
  administrator to remove. A saved pair of reminder lead times closer than 10
  days is treated as 60 and 30 days.
- Event cancellation notices use the renewal reminder opt-out and go to the
  same Yes and Maybe RSVPs as day-of event reminders.
- After a privacy removal, the member's Wendfold profile row stays, without its
  user account link, so historical attendance, metrics, awards, and dues still
  count.
