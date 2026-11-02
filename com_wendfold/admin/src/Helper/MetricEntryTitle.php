<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Service\MetricSource;

/**
 * Resolves a display title for a metric_entries row from its source.
 *
 * Shared by site My activities, the admin Activities list, and the site
 * activity log so source titles cannot drift apart. Joins and CASE live here;
 * callers select the aliased column and escape it in the template.
 *
 * @since  1.0.0
 */
final class MetricEntryTitle
{
	/**
	 * Default SELECT alias for the resolved title.
	 *
	 * @since  1.0.0
	 */
	public const TITLE_ALIAS = 'activity_title';

	/**
	 * LEFT JOIN source tables and select a CASE/COALESCE title expression.
	 *
	 * Expects the query to already FROM #__wendfold_metric_entries aliased as
	 * $entryAlias. Source-type binds are attached to JOIN conditions so
	 * ListModel count queries that clear SELECT keep working. Translated
	 * labels are quoted constants (not SELECT-only binds).
	 *
	 * @param   object  $query       List or feed query builder.
	 * @param   object  $db          Database driver (quoteName / quote / bind host).
	 * @param   string  $entryAlias  Alias of metric_entries.
	 * @param   string  $titleAlias  SELECT alias for the title.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function applyToQuery(
		object $query,
		object $db,
		string $entryAlias = 'me',
		string $titleAlias = self::TITLE_ALIAS
	): void
	{
		$activitySource = MetricSource::Activity->value;
		$attendanceSource = MetricSource::Attendance->value;
		$volunteerSource = MetricSource::Volunteer->value;
		$entry = $db->quoteName($entryAlias);

		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_activities', 'a')
			. ' ON ' . $db->quoteName('a.id') . ' = ' . $entry . '.' . $db->quoteName('source_id')
			. ' AND ' . $entry . '.' . $db->quoteName('source_type') . ' = :activitySourceTitle'
		);
		$query->bind(':activitySourceTitle', $activitySource, ParameterType::INTEGER);

		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_events', 'e_src')
			. ' ON ' . $db->quoteName('e_src.id') . ' = ' . $entry . '.' . $db->quoteName('event_id')
			. ' AND ' . $entry . '.' . $db->quoteName('source_type') . ' IN ('
			. ':attendanceSourceTitle, :volunteerSourceTitleEvent)'
		);
		$query->bind(':attendanceSourceTitle', $attendanceSource, ParameterType::INTEGER);
		$query->bind(':volunteerSourceTitleEvent', $volunteerSource, ParameterType::INTEGER);

		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_event_volunteers', 'ev')
			. ' ON ' . $db->quoteName('ev.id') . ' = ' . $entry . '.' . $db->quoteName('source_id')
			. ' AND ' . $entry . '.' . $db->quoteName('source_type') . ' = :volunteerSourceTitle'
		);
		$query->bind(':volunteerSourceTitle', $volunteerSource, ParameterType::INTEGER);

		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_event_volunteer_roles', 'vr')
			. ' ON ' . $db->quoteName('vr.id') . ' = ' . $db->quoteName('ev.volunteer_role_id')
		);

		$query->select(self::titleExpression($db, $entryAlias) . ' AS ' . $db->quoteName($titleAlias));
	}

	/**
	 * CASE expression that picks the title for each source_type.
	 *
	 * @param   object  $db          Database driver (quoteName / quote host).
	 * @param   string  $entryAlias  Alias of metric_entries.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function titleExpression(object $db, string $entryAlias = 'me'): string
	{
		$entry = $db->quoteName($entryAlias);
		$activity = (string) MetricSource::Activity->value;
		$attendance = (string) MetricSource::Attendance->value;
		$volunteer = (string) MetricSource::Volunteer->value;
		$manual = (string) MetricSource::Manual->value;
		$import = (string) MetricSource::Import->value;

		$activityTitle = 'NULLIF(TRIM(' . $db->quoteName('a.title') . '), ' . $db->quote('') . ')';
		$eventTitle = 'NULLIF(TRIM(' . $db->quoteName('e_src.title') . '), ' . $db->quote('') . ')';
		$roleTitle = 'NULLIF(TRIM(' . $db->quoteName('vr.title') . '), ' . $db->quote('') . ')';
		$volunteerPrefix = $db->quote(Text::_('COM_WENDFOLD_METRIC_ENTRY_TITLE_VOLUNTEER_PREFIX'));
		$manualLabel = $db->quote(Text::_('COM_WENDFOLD_METRIC_ENTRY_TITLE_MANUAL_ENTRY'));
		$importLabel = $db->quote(Text::_('COM_WENDFOLD_METRIC_ENTRY_TITLE_IMPORT'));
		$otherLabel = $db->quote(Text::_('COM_WENDFOLD_METRIC_ENTRY_TITLE_OTHER'));
		$noteTitle = 'NULLIF(TRIM(' . $entry . '.' . $db->quoteName('note') . '), ' . $db->quote('') . ')';
		$manualTitle = 'COALESCE(' . $noteTitle . ', ' . $manualLabel . ')';
		$sourceType = $entry . '.' . $db->quoteName('source_type');

		return 'CASE'
			. ' WHEN ' . $sourceType . ' IS NULL OR ' . $sourceType . ' = ' . $db->quote('0')
			. ' OR ' . $sourceType . ' = ' . $db->quote($manual) . ' THEN ' . $manualTitle
			. ' WHEN ' . $sourceType . ' = ' . $db->quote($activity) . ' THEN ' . $activityTitle
			. ' WHEN ' . $sourceType . ' = ' . $db->quote($attendance) . ' THEN ' . $eventTitle
			. ' WHEN ' . $sourceType . ' = ' . $db->quote($volunteer) . ' THEN CASE'
			. ' WHEN ' . $roleTitle . ' IS NOT NULL THEN CONCAT(' . $volunteerPrefix . ', ' . $roleTitle . ')'
			. ' ELSE ' . $eventTitle
			. ' END'
			. ' WHEN ' . $sourceType . ' = ' . $db->quote($import) . ' THEN ' . $importLabel
			. ' ELSE ' . $otherLabel
			. ' END';
	}

	/**
	 * Trimmed title for templates, or empty when nothing resolved.
	 *
	 * Templates render COM_WENDFOLD_MYACTIVITIES_NOTE_NONE ("—") for empty.
	 *
	 * @param   string|null  $title  Resolved title from the query.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function display(?string $title): string
	{
		return trim((string) $title);
	}
}
