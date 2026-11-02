-- Wendfold 1.0.0 uninstall: drops every table install.mysql.utf8.sql creates.
-- Joomla removes the #__schemas row itself; user groups are handled in script.php.
-- Drop order is the reverse of the 1.0.0 install baseline. Pre-release tables
-- that this baseline never creates (rides, miles, milestone_*, member stats views)
-- are not listed here.
DROP TABLE IF EXISTS `#__wendfold_sync_log`;
DROP TABLE IF EXISTS `#__wendfold_reminder_log`;
DROP TABLE IF EXISTS `#__wendfold_dues_payments`;
DROP TABLE IF EXISTS `#__wendfold_member_awards`;
DROP TABLE IF EXISTS `#__wendfold_award_criteria`;
DROP TABLE IF EXISTS `#__wendfold_award_definitions`;
DROP TABLE IF EXISTS `#__wendfold_metric_entries`;
DROP TABLE IF EXISTS `#__wendfold_activities`;
DROP TABLE IF EXISTS `#__wendfold_event_metric_credits`;
DROP TABLE IF EXISTS `#__wendfold_metric_definitions`;
DROP TABLE IF EXISTS `#__wendfold_event_trip_participants`;
DROP TABLE IF EXISTS `#__wendfold_event_volunteers`;
DROP TABLE IF EXISTS `#__wendfold_event_volunteer_roles`;
DROP TABLE IF EXISTS `#__wendfold_event_group_members`;
DROP TABLE IF EXISTS `#__wendfold_event_groups`;
DROP TABLE IF EXISTS `#__wendfold_rsvps`;
DROP TABLE IF EXISTS `#__wendfold_attendance`;
DROP TABLE IF EXISTS `#__wendfold_events`;
DROP TABLE IF EXISTS `#__wendfold_locations`;
DROP TABLE IF EXISTS `#__wendfold_officers`;
DROP TABLE IF EXISTS `#__wendfold_motorcycles`;
DROP TABLE IF EXISTS `#__wendfold_profiles`;
DROP TABLE IF EXISTS `#__wendfold_event_types`;
DROP TABLE IF EXISTS `#__wendfold_officer_roles`;
DROP TABLE IF EXISTS `#__wendfold_membership_types`;
