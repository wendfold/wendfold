--
-- Wendfold 1.0.0 baseline schema. MySQL 8.0.13+ / MariaDB 10.6+.
-- Columns and tables marked as legacy are still read or written by current code.
-- The rides table is not part of this baseline.
-- Conventions follow Joomla 6 core: InnoDB, utf8mb4_unicode_ci, INT UNSIGNED ids,
-- no FOREIGN KEY constraints (relations documented in COMMENT 'FK to ...'),
-- created/modified NOT NULL without a DB default (set in PHP, UTC), optional dates NULL,
-- published: 1 published, 0 unpublished, 2 archived, -2 trashed.
-- Hard-coded enums (TINYINT) are documented in COMMENT and mirrored by PHP backed enums.
--

-- ---------------------------------------------------------------------------
-- Lookups that chapters edit
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_membership_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Plain text or a language key',
  `import_code` varchar(50) NOT NULL DEFAULT '' COMMENT 'Value matched from the roster CSV',
  `exempt_from_renewal` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1 = no renewal reminders (e.g. life members)',
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_import_code` (`import_code`),
  KEY `idx_published_ordering` (`published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_officer_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Plain text or a language key',
  `system_key` varchar(50) DEFAULT NULL COMMENT 'Stable key code may test (e.g. head_road_captain); NULL for chapter-defined roles',
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_system_key` (`system_key`),
  KEY `idx_published_ordering` (`published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Plain text or a language key',
  `behavior` tinyint unsigned NOT NULL DEFAULT 4 COMMENT 'Hard-coded: 1 ride, 2 meeting, 3 overnight trip, 4 general',
  `default_riding` tinyint unsigned NOT NULL DEFAULT 0,
  `default_workflow_mode` tinyint unsigned NOT NULL DEFAULT 1,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_published_ordering` (`published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Members
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL COMMENT 'FK to #__users.id; NULL after the account is deleted and the profile anonymized',
  `member_number` varchar(30) DEFAULT NULL COMMENT 'National member number; NULL when unknown',
  `preferred_name` varchar(100) NOT NULL DEFAULT '',
  `membership_type_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_membership_types.id',
  `member_status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 active, 2 lapsed, 3 left chapter',
  `member_since` date DEFAULT NULL,
  `chapter_join_date` date DEFAULT NULL,
  `renewal_date` date DEFAULT NULL COMMENT 'National expiry date from the roster',
  `waiver_signed_date` date DEFAULT NULL,
  `waiver_version` varchar(20) NOT NULL DEFAULT '',
  `is_road_captain` tinyint unsigned NOT NULL DEFAULT 0,
  `is_road_crew` tinyint unsigned NOT NULL DEFAULT 0,
  `national_verified_date` date DEFAULT NULL COMMENT 'NULL = not verified',
  `national_sync_date` datetime DEFAULT NULL,
  `chapter_on_file` varchar(20) NOT NULL DEFAULT '',
  `chapter_switch_flagged` tinyint unsigned NOT NULL DEFAULT 0,
  `sponsor_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `emergency_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'PII',
  `emergency_phone` varchar(30) NOT NULL DEFAULT '' COMMENT 'PII',
  `emergency_relation` varchar(50) NOT NULL DEFAULT '' COMMENT 'PII',
  `shirt_size` varchar(10) NOT NULL DEFAULT '',
  `photo` varchar(255) NOT NULL DEFAULT '' COMMENT 'PII',
  `notes` mediumtext COMMENT 'Officer notes; PII',
  `published` tinyint NOT NULL DEFAULT 1,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `dues_paid` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  `dues_paid_date` date DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `waiver_signed` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  `national_verified` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  `reminder_60_sent` date DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `reminder_30_sent` date DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Legacy column still used by current code.',
  `completed_by` int unsigned NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_id` (`user_id`),
  UNIQUE KEY `idx_member_number` (`member_number`),
  KEY `idx_status_renewal` (`published`, `member_status`, `renewal_date`),
  KEY `idx_sponsor` (`sponsor_id`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_motorcycles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `model_year` smallint unsigned DEFAULT NULL,
  `make` varchar(50) NOT NULL DEFAULT '',
  `model` varchar(100) NOT NULL DEFAULT '',
  `color` varchar(50) NOT NULL DEFAULT '',
  `nickname` varchar(50) NOT NULL DEFAULT '',
  `vin` varchar(30) NOT NULL DEFAULT '' COMMENT 'PII',
  `is_primary` tinyint unsigned NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `year` smallint unsigned DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `notes` text COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  KEY `idx_profile` (`profile_id`, `published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_officers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `role_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_officer_roles.id',
  `term_start` date DEFAULT NULL,
  `term_end` date DEFAULT NULL COMMENT 'NULL = current term open-ended',
  `is_current` tinyint unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  `notes` text,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `role_order` int NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  KEY `idx_current` (`published`, `is_current`, `ordering`),
  KEY `idx_profile` (`profile_id`),
  KEY `idx_role` (`role_id`, `is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Places and events
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `region` varchar(100) NOT NULL DEFAULT '' COMMENT 'State / province, any country',
  `postcode` varchar(20) NOT NULL DEFAULT '',
  `country` char(2) NOT NULL DEFAULT '' COMMENT 'ISO 3166-1 alpha-2 or empty',
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT '',
  `notes` text,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `is_adhoc` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1 = created inline from an event form, hidden from pick lists',
  `published` tinyint NOT NULL DEFAULT 1,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `state` varchar(2) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `zip` varchar(10) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `location_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`(100)),
  KEY `idx_list` (`published`, `is_adhoc`, `name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_event_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_events.id',
  `event_type_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_event_types.id',
  `title` varchar(255) NOT NULL,
  `alias` varchar(400) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '' COMMENT 'URL slug. Unique across every event row, including unpublished and trashed.',
  `description` mediumtext,
  `event_date` date NOT NULL COMMENT 'Local wall-clock first day; never timezone-converted',
  `end_date` date DEFAULT NULL COMMENT 'Last day for multi-day events',
  `ksu_time` time DEFAULT NULL COMMENT 'Local wall-clock',
  `ksd_time` time DEFAULT NULL COMMENT 'Local wall-clock',
  `ksu_location_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_locations.id',
  `ksd_location_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_locations.id',
  `venue_location_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_locations.id',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 scheduled, 2 completed, 3 cancelled, 4 confirmed, 5 weather watch',
  `workflow_mode` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded, see WorkflowMode enum',
  `audience_scope` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded, see AudienceScope enum',
  `is_riding_event` tinyint unsigned NOT NULL DEFAULT 0,
  `allow_rsvp` tinyint unsigned NOT NULL DEFAULT 1,
  `show_in_event_list` tinyint unsigned NOT NULL DEFAULT 1,
  `capacity` int unsigned NOT NULL DEFAULT 0 COMMENT '0 = unlimited',
  `difficulty` tinyint unsigned NOT NULL DEFAULT 0,
  `route_link` varchar(2048) NOT NULL DEFAULT '',
  `route_link_2` varchar(2048) NOT NULL DEFAULT '',
  `gpx_file` varchar(255) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL DEFAULT '',
  `road_captain_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `sweep_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `volunteer_signup_open` tinyint unsigned NOT NULL DEFAULT 0,
  `volunteer_notes` text,
  `volunteer_manager_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `volunteer_backup_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `trip_booking_deadline` date DEFAULT NULL,
  `trip_booking_method` varchar(30) NOT NULL DEFAULT '',
  `trip_booking_code` varchar(60) NOT NULL DEFAULT '',
  `trip_lodging_location_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_locations.id',
  `trip_lodging_phone` varchar(40) NOT NULL DEFAULT '',
  `trip_lodging_notes` text,
  `trip_lead_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `trip_sweep_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `qr_token` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL COMMENT 'Random check-in token',
  `published` tinyint NOT NULL DEFAULT 1,
  `completed_at` datetime NULL DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `ksu_location_custom` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksu_address` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksu_city` varchar(100) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksu_state` varchar(2) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksu_zip` varchar(10) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksd_location_custom` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksd_address` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksd_city` varchar(100) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksd_state` varchar(2) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `ksd_zip` varchar(10) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `event_location_id` int unsigned DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  `event_location_custom` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `event_address` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `event_city` varchar(100) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `event_state` varchar(2) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `event_zip` varchar(10) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `trip_lodging_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `trip_lodging_address` varchar(255) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `trip_lodging_city` varchar(100) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `trip_lodging_state` varchar(2) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `trip_lodging_zip` varchar(10) NOT NULL DEFAULT '' COMMENT 'Legacy column still used by current code.',
  `completed_date` datetime DEFAULT NULL COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`),
  UNIQUE KEY `idx_qr_token` (`qr_token`),
  KEY `idx_listing` (`published`, `event_date`, `show_in_event_list`, `status`),
  KEY `idx_type_date` (`event_type_id`, `event_date`),
  KEY `idx_parent` (`parent_event_id`),
  KEY `idx_road_captain` (`road_captain_id`),
  KEY `idx_sweep` (`sweep_id`),
  KEY `idx_checkout` (`checked_out`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `check_in_time` datetime NOT NULL COMMENT 'UTC instant, set in PHP',
  `check_in_method` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 QR self, 2 officer, 3 admin, 4 import',
  `checked_in_by` int unsigned NOT NULL DEFAULT 0 COMMENT 'FK to #__users.id',
  `notes` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_profile` (`event_id`, `profile_id`),
  KEY `idx_profile_event` (`profile_id`, `event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_rsvps` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `response` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 going, 2 maybe, 3 not going, 4 waitlisted',
  `guests` smallint unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_profile` (`event_id`, `profile_id`),
  KEY `idx_profile_event` (`profile_id`, `event_id`),
  KEY `idx_event_response` (`event_id`, `response`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `title` varchar(255) NOT NULL DEFAULT '',
  `group_order` smallint unsigned NOT NULL DEFAULT 1,
  `road_captain_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `sweep_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `staging_hint` varchar(255) NOT NULL DEFAULT '',
  `order_hint` varchar(255) NOT NULL DEFAULT '',
  `notes` text,
  `published` tinyint NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_group_order` (`event_id`, `group_order`),
  KEY `idx_road_captain` (`road_captain_id`),
  KEY `idx_sweep` (`sweep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_group_members` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_group_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_event_groups.id',
  `event_id` int unsigned NOT NULL COMMENT 'Copy of event_groups.event_id; exists only to enforce one group per member per event',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `assignment_order` smallint unsigned NOT NULL DEFAULT 0,
  `assigned_at` datetime NOT NULL,
  `assigned_by` int unsigned NOT NULL DEFAULT 0,
  `notes` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_profile` (`event_id`, `profile_id`),
  KEY `idx_group_order` (`event_group_id`, `assignment_order`),
  KEY `idx_profile` (`profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_volunteer_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `title` varchar(150) NOT NULL DEFAULT '',
  `description` text,
  `slot_count` smallint unsigned NOT NULL DEFAULT 1,
  `shift_label` varchar(80) NOT NULL DEFAULT '',
  `start_time` time DEFAULT NULL COMMENT 'Local wall-clock',
  `end_time` time DEFAULT NULL COMMENT 'Local wall-clock',
  `requires_road_captain` tinyint unsigned NOT NULL DEFAULT 0,
  `requires_road_crew` tinyint unsigned NOT NULL DEFAULT 0,
  `members_only` tinyint unsigned NOT NULL DEFAULT 1,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `sort_order` int NOT NULL DEFAULT 0 COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  KEY `idx_event_ordering` (`event_id`, `published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_volunteers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `volunteer_role_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_event_volunteer_roles.id',
  `event_id` int unsigned NOT NULL COMMENT 'Copy of the role event_id, for per-event lookups',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `assignment_status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 signed up, 2 confirmed, 3 waitlisted, 4 withdrawn',
  `notes` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_role_profile` (`volunteer_role_id`, `profile_id`),
  KEY `idx_role_status` (`volunteer_role_id`, `assignment_status`),
  KEY `idx_event_profile` (`event_id`, `profile_id`, `assignment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_trip_participants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `lodging_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Hard-coded, see LodgingStatus enum',
  `lodging_notes` text,
  `arrival_notes` varchar(255) NOT NULL DEFAULT '',
  `departure_notes` varchar(255) NOT NULL DEFAULT '',
  `booked_at` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_event_profile` (`event_id`, `profile_id`),
  KEY `idx_event_lodging` (`event_id`, `lodging_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Participation metrics (replaces rides.mileage, events.mileage, events.points_value, miles)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_metric_definitions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `metric_key` varchar(50) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT 'Stable machine key, e.g. distance',
  `title` varchar(255) NOT NULL COMMENT 'Plain text or a language key',
  `unit_label` varchar(100) NOT NULL DEFAULT '' COMMENT 'Display unit, plain text or a language key; a label only, values are never converted',
  `decimals` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '0-3, display and input rounding',
  `aggregation` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 sum, 2 count, 3 max',
  `allow_event_credit` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Events can grant this metric to attendees',
  `default_event_credit` decimal(12,3) DEFAULT NULL COMMENT 'Prefilled per-event credit for new events; NULL = none',
  `allow_member_entry` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Members can log their own entries',
  `member_entry_needs_approval` tinyint unsigned NOT NULL DEFAULT 0,
  `show_on_dashboard` tinyint unsigned NOT NULL DEFAULT 1,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_metric_key` (`metric_key`),
  KEY `idx_published_ordering` (`published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_event_metric_credits` (
  `event_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_events.id',
  `metric_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_metric_definitions.id',
  `value` decimal(12,3) NOT NULL DEFAULT 0 COMMENT 'Credited to each attendee',
  PRIMARY KEY (`event_id`, `metric_id`),
  KEY `idx_metric` (`metric_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_activities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `activity_date` date NOT NULL COMMENT 'Local wall-clock date',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` text,
  `motorcycle_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_motorcycles.id',
  `start_location` varchar(255) NOT NULL DEFAULT '',
  `end_location` varchar(255) NOT NULL DEFAULT '',
  `published` tinyint NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_profile_date` (`profile_id`, `activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_metric_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `metric_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_metric_definitions.id',
  `entry_date` date NOT NULL COMMENT 'Local date the value counts toward; copied from the event or activity',
  `value` decimal(12,3) NOT NULL DEFAULT 0,
  `event_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_events.id',
  `source_type` tinyint unsigned NOT NULL COMMENT 'Hard-coded: 1 attendance, 2 activity, 3 manual, 4 import, 5 volunteer',
  `source_id` int unsigned DEFAULT NULL COMMENT 'attendance.id / activities.id / event_volunteers.id; NULL for manual',
  `published` tinyint NOT NULL DEFAULT 1 COMMENT '1 counted, 0 pending approval, -2 trashed',
  `note` varchar(255) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_source` (`source_type`, `source_id`, `metric_id`),
  KEY `idx_metric_period` (`metric_id`, `published`, `entry_date`, `profile_id`, `value`),
  KEY `idx_profile_metric` (`profile_id`, `metric_id`, `published`, `entry_date`, `value`),
  KEY `idx_event` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Awards (replaces milestone_definitions / milestone_awards)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_award_definitions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Chapter-editable label, plain text or a language key',
  `description` text,
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT 'Optional badge image path',
  `period_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 lifetime, 2 calendar year, 3 rolling window, 4 custom date range',
  `rolling_days` smallint unsigned DEFAULT NULL COMMENT 'period_type 3 only',
  `period_start` date DEFAULT NULL COMMENT 'period_type 4 only',
  `period_end` date DEFAULT NULL COMMENT 'period_type 4 only',
  `match_mode` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 all criteria, 2 any criterion',
  `repeat_mode` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Hard-coded: 0 once per period, 1 tiered (threshold + n * tier_step)',
  `max_tiers` smallint unsigned DEFAULT NULL COMMENT 'NULL = unlimited',
  `streak_periods` smallint unsigned DEFAULT NULL COMMENT 'Consecutive calendar years required (period_type 2); NULL = no streak',
  `revoke_policy` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 revoke automatically, 2 flag for review',
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_published_ordering` (`published`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_award_criteria` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `award_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_award_definitions.id',
  `criterion_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 metric total in period, 2 membership years, 3 holds another award',
  `metric_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_metric_definitions.id (type 1)',
  `required_award_id` int unsigned DEFAULT NULL COMMENT 'FK to #__wendfold_award_definitions.id (type 3)',
  `threshold` decimal(14,3) NOT NULL COMMENT 'Minimum value; application requires >= 1',
  `tier_step` decimal(14,3) DEFAULT NULL COMMENT 'Extra amount per tier when repeat_mode = 1',
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_award` (`award_id`, `ordering`),
  KEY `idx_metric` (`metric_id`),
  KEY `idx_required_award` (`required_award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_member_awards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `award_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_award_definitions.id',
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `period_key` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'Empty = lifetime/rolling/custom; YYYY for calendar-year awards',
  `tier` smallint unsigned NOT NULL DEFAULT 1,
  `achieved_value` decimal(14,3) DEFAULT NULL COMMENT 'Metric total when granted (single-criterion awards)',
  `state` tinyint NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 granted, 0 revoked, 2 needs review',
  `grant_source` tinyint unsigned NOT NULL DEFAULT 1 COMMENT 'Hard-coded: 1 automatic, 2 manual',
  `is_locked` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1 = recompute never changes this row',
  `earned_at` datetime NOT NULL COMMENT 'UTC',
  `revoked_at` datetime NULL DEFAULT NULL,
  `presented_on` date DEFAULT NULL,
  `notes` varchar(255) NOT NULL DEFAULT '',
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_award_member_period_tier` (`award_id`, `profile_id`, `period_key`, `tier`),
  KEY `idx_profile_state` (`profile_id`, `state`),
  KEY `idx_award_state` (`award_id`, `state`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Dues, reminders, import log
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__wendfold_dues_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `period_start` date NOT NULL COMMENT 'First day the payment covers',
  `period_end` date NOT NULL COMMENT 'Last day the payment covers',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Site currency from component options',
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT '',
  `receipt_number` varchar(50) NOT NULL DEFAULT '',
  `notes` varchar(255) NOT NULL DEFAULT '',
  `published` tinyint NOT NULL DEFAULT 1 COMMENT '-2 = voided',
  `checked_out` int unsigned DEFAULT NULL,
  `checked_out_time` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `year` smallint unsigned NOT NULL COMMENT 'Legacy column still used by current code.',
  `amount_paid` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT 'Legacy column still used by current code.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_profile_year` (`profile_id`, `year`),
  KEY `idx_profile_period` (`profile_id`, `period_end`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_reminder_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `profile_id` int unsigned NOT NULL COMMENT 'FK to #__wendfold_profiles.id',
  `reminder_key` varchar(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'e.g. renewal-60, event-123',
  `reference_date` date NOT NULL COMMENT 'Renewal or event date the reminder was for',
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_once` (`profile_id`, `reminder_key`, `reference_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__wendfold_sync_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sync_type` tinyint unsigned NOT NULL COMMENT 'Hard-coded, see SyncType enum',
  `sync_date` datetime NOT NULL,
  `performed_by` int unsigned NOT NULL DEFAULT 0,
  `records_processed` int unsigned NOT NULL DEFAULT 0,
  `records_updated` int unsigned NOT NULL DEFAULT 0,
  `records_added` int unsigned NOT NULL DEFAULT 0,
  `records_skipped` int unsigned NOT NULL DEFAULT 0,
  `records_errored` int unsigned NOT NULL DEFAULT 0,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint unsigned NOT NULL DEFAULT 1,
  `log_detail` mediumtext COMMENT 'May name members; purge with retention option',
  PRIMARY KEY (`id`),
  KEY `idx_type_date` (`sync_type`, `sync_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- Editable defaults seeded on install (chapters may edit, unpublish or delete).
-- Titles are language keys so a fresh site shows them in its own language.
-- ---------------------------------------------------------------------------

INSERT INTO `#__wendfold_membership_types` (`title`, `import_code`, `exempt_from_renewal`, `ordering`, `created`, `modified`) VALUES
('COM_WENDFOLD_MEMBERSHIP_FULL', 'Full', 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_MEMBERSHIP_ASSOCIATE', 'Associate', 0, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_MEMBERSHIP_LIFE', 'Life', 1, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP());

-- volunteer_officer is not seeded. None of these titles is Volunteer Officer,
-- which is the title the event screen used to match. Set that system key on
-- the role that should manage volunteers.
INSERT INTO `#__wendfold_officer_roles` (`title`, `system_key`, `ordering`, `created`, `modified`) VALUES
('COM_WENDFOLD_OFFICER_ROLE_DIRECTOR', NULL, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_ASSISTANT_DIRECTOR', NULL, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_SECRETARY', NULL, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_TREASURER', NULL, 4, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_ACTIVITIES', NULL, 5, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_SAFETY', NULL, 6, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_ROAD_CAPTAIN', 'head_road_captain', 7, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_MEMBERSHIP', NULL, 8, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_EDITOR', NULL, 9, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_WEBMASTER', NULL, 10, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_HISTORIAN', NULL, 11, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_OFFICER_ROLE_PHOTOGRAPHER', NULL, 12, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO `#__wendfold_event_types` (`title`, `behavior`, `default_riding`, `default_workflow_mode`, `ordering`, `created`, `modified`) VALUES
('COM_WENDFOLD_EVENT_TYPE_RIDE', 1, 1, 2, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_MEETING_SUBTYPE_CHAPTER', 2, 0, 3, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_MEETING_SUBTYPE_OFFICERS', 2, 0, 3, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_MEETING_SUBTYPE_ROAD_CAPTAINS', 2, 0, 3, 4, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_EVENT_TYPE_RALLY', 1, 1, 2, 5, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_EVENT_TYPE_SOCIAL', 4, 0, 1, 6, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_EVENT_TYPE_OVERNIGHT', 3, 1, 5, 7, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_EVENT_TYPE_OTHER', 4, 0, 1, 8, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO `#__wendfold_metric_definitions` (`metric_key`, `title`, `unit_label`, `decimals`, `aggregation`, `allow_event_credit`, `default_event_credit`, `allow_member_entry`, `member_entry_needs_approval`, `ordering`, `created`, `modified`) VALUES
('distance', 'COM_WENDFOLD_METRIC_DISTANCE', 'COM_WENDFOLD_UNIT_MILES', 1, 1, 1, NULL, 1, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('events_attended', 'COM_WENDFOLD_METRIC_EVENTS_ATTENDED', 'COM_WENDFOLD_UNIT_EVENTS', 0, 1, 1, 1.000, 0, 0, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('points', 'COM_WENDFOLD_METRIC_POINTS', 'COM_WENDFOLD_UNIT_POINTS', 0, 1, 1, NULL, 0, 0, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('volunteer_hours', 'COM_WENDFOLD_METRIC_VOLUNTEER_HOURS', 'COM_WENDFOLD_UNIT_HOURS', 2, 1, 1, NULL, 1, 1, 4, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO `#__wendfold_award_definitions` (`title`, `period_type`, `repeat_mode`, `ordering`, `created`, `modified`) VALUES
('COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_DISTANCE', 2, 0, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_AWARD_DEFAULT_LIFETIME_DISTANCE', 1, 1, 2, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_EVENTS', 2, 0, 3, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_VOLUNTEER', 2, 0, 4, UTC_TIMESTAMP(), UTC_TIMESTAMP()),
('COM_WENDFOLD_AWARD_DEFAULT_MEMBERSHIP_YEARS', 1, 1, 5, UTC_TIMESTAMP(), UTC_TIMESTAMP());

INSERT INTO `#__wendfold_award_criteria` (`award_id`, `criterion_type`, `metric_id`, `threshold`, `tier_step`)
SELECT a.`id`, 1, m.`id`, 5000, NULL FROM `#__wendfold_award_definitions` a, `#__wendfold_metric_definitions` m
 WHERE a.`title` = 'COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_DISTANCE' AND m.`metric_key` = 'distance';
INSERT INTO `#__wendfold_award_criteria` (`award_id`, `criterion_type`, `metric_id`, `threshold`, `tier_step`)
SELECT a.`id`, 1, m.`id`, 10000, 10000 FROM `#__wendfold_award_definitions` a, `#__wendfold_metric_definitions` m
 WHERE a.`title` = 'COM_WENDFOLD_AWARD_DEFAULT_LIFETIME_DISTANCE' AND m.`metric_key` = 'distance';
INSERT INTO `#__wendfold_award_criteria` (`award_id`, `criterion_type`, `metric_id`, `threshold`, `tier_step`)
SELECT a.`id`, 1, m.`id`, 20, NULL FROM `#__wendfold_award_definitions` a, `#__wendfold_metric_definitions` m
 WHERE a.`title` = 'COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_EVENTS' AND m.`metric_key` = 'events_attended';
INSERT INTO `#__wendfold_award_criteria` (`award_id`, `criterion_type`, `metric_id`, `threshold`, `tier_step`)
SELECT a.`id`, 1, m.`id`, 25, NULL FROM `#__wendfold_award_definitions` a, `#__wendfold_metric_definitions` m
 WHERE a.`title` = 'COM_WENDFOLD_AWARD_DEFAULT_ANNUAL_VOLUNTEER' AND m.`metric_key` = 'volunteer_hours';
INSERT INTO `#__wendfold_award_criteria` (`award_id`, `criterion_type`, `metric_id`, `threshold`, `tier_step`)
SELECT a.`id`, 2, NULL, 5, 5 FROM `#__wendfold_award_definitions` a
 WHERE a.`title` = 'COM_WENDFOLD_AWARD_DEFAULT_MEMBERSHIP_YEARS';
