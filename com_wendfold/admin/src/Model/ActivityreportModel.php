<?php
/**
 * Wendfold - Chapter Activity Report Model
 *
 * Aggregates chapter-wide statistics for a configurable date range.
 * Default range: current calendar year (Jan 1 - Dec 31).
 *
 * Data provided:
 *   - Total events by event type title
 *   - Total attendance across all events in range
 *   - Top 10 members by attendance count
 *   - Top 10 members by the distance metric
 *   - Dues summary: distinct payers and total amount collected
 *   - New members added in the period (profiles.created)
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBuckets;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricQuery;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;

class ActivityreportModel extends BaseDatabaseModel
{
	/**
	 * Return the current filter state (date range).
	 *
	 * Reads from user state so filters survive page loads.
	 * Defaults to current calendar year if not set.
	 *
	 * @return  object  {date_from, date_to}
	 */
	public function getFilters(): object
	{
		$app = Factory::getApplication();

		$period = MetricPeriod::calendarYear(MetricPeriod::siteZone());
		$defaultFrom = $period['start'];
		$defaultTo   = $period['end'];

		$dateFrom = $app->getUserStateFromRequest(
			'com_wendfold.activityreport.date_from',
			'date_from',
			$defaultFrom,
			'string'
		);
		$dateTo = $app->getUserStateFromRequest(
			'com_wendfold.activityreport.date_to',
			'date_to',
			$defaultTo,
			'string'
		);

		// Validate date format (YYYY-MM-DD); fall back to defaults if invalid
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom))
		{
			$dateFrom = $defaultFrom;
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo))
		{
			$dateTo = $defaultTo;
		}

		return (object) [
			'date_from' => $dateFrom,
			'date_to'   => $dateTo,
		];
	}

	/**
	 * Published event counts, one bucket per event type.
	 *
	 * The key is event_types.id. The label is that row's title, so a custom
	 * type is not folded into ids 1 to 5.
	 *
	 * @return  array<int, array{id: int, title: string, behavior: int, count: int}>
	 */
	public function getEventsByType(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'),
				'COUNT(*) AS event_count',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('event_date') . ' <= ' . $db->quote($filters->date_to))
			->group($db->quoteName('e.event_type_id'))
			->order($db->quoteName('e.event_type_id') . ' ASC');

		$rows = $db->setQuery($query)->loadObjectList() ?: [];
		$input = [];
		$typeId = 'event_type_id';
		$typeTitle = 'event_type_title';
		$typeBehavior = 'event_behavior';
		$typeCount = 'event_count';

		foreach ($rows as $row)
		{
			$input[] = [
				'id'       => (int) $row->$typeId,
				'title'    => (string) ($row->$typeTitle ?? ''),
				'behavior' => (int) ($row->$typeBehavior ?? 0),
				'count'    => (int) $row->$typeCount,
			];
		}

		return EventTypeBuckets::counts($input);
	}

	/**
	 * Total attendance records for events within the date range.
	 *
	 * Joins attendance to events to filter by event_date.
	 *
	 * @return  integer
	 */
	public function getTotalAttendance(): int
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select('COUNT(a.id)')
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e') .
				' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('e.event_date') . ' <= ' . $db->quote($filters->date_to));

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Top 10 members by attendance count within the date range.
	 *
	 * @return  array  Each row: {member_name, member_number, attendance_count}
	 */
	public function getTopAttendees(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				'COUNT(' . $db->quoteName('a.id') . ') AS attendance_count',
				]
			)
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e') .
				' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__wendfold_profiles', 'p') .
				' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.profile_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__users', 'u') .
				' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('e.event_date') . ' <= ' . $db->quote($filters->date_to))
			->group([
				$db->quoteName('a.profile_id'),
				$db->quoteName('u.name'),
				$db->quoteName('p.member_number'),
				]
			)
			->order('attendance_count DESC');

		$query->setLimit(10, 0);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Top 10 members by the distance metric in the selected period.
	 *
	 * The total is read from metric_entries. Names are attached afterwards.
	 *
	 * @return  array  Each row: {member_name, member_number, total_miles}
	 */
	public function getTopRiders(): array
	{
		$filters = $this->getFilters();

		if (!MetricPeriod::isDay((string) $filters->date_from) || !MetricPeriod::isDay((string) $filters->date_to))
		{
			return [];
		}

		$db = $this->getDatabase();
		$metricId = MetricQuery::idForKey($db, 'distance');

		if ($metricId < 1)
		{
			return [];
		}

		$rows = MetricQuery::leaderboard($db, $metricId, (string) $filters->date_from, (string) $filters->date_to, 1, 10);
		$rows = MetricQuery::withNames($db, $rows);

		foreach ($rows as $row)
		{
			$row->total_miles = $row->total;
		}

		return $rows;
	}

	/**
	 * Dues collection summary for the date range.
	 *
	 * Filters by payment_date (DATE column on dues_payments).
	 * Returns: distinct member count and total amount collected.
	 *
	 * @return  object  {members_paid, total_collected}
	 */
	public function getDuesSummary(): object
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				'COUNT(DISTINCT ' . $db->quoteName('profile_id') . ') AS members_paid',
				'SUM(' . $db->quoteName('amount_paid') . ') AS total_collected',
				]
			)
			->from($db->quoteName('#__wendfold_dues_payments'))
			->where($db->quoteName('payment_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('payment_date') . ' <= ' . $db->quote($filters->date_to));

		$result = $db->setQuery($query)->loadObject();

		return (object) [
			'members_paid'    => (int) ($result->members_paid ?? 0),
			'total_collected' => number_format((float) ($result->total_collected ?? 0), 2),
		];
	}

	/**
	 * New members added during the date range.
	 *
	 * Filters by profiles.created (DATETIME).
	 * Only published profiles are included.
	 *
	 * @return  array  Each row: {member_name, member_number, join_date}
	 */
	public function getNewMembers(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		// Extend date_to to end of day so Dec 31 picks up all same-day joins
		$dateTo = $filters->date_to . ' 23:59:59';

		$query = $db->createQuery()
			->select([
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				$db->quoteName('p.created', 'join_date'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'INNER',
				$db->quoteName('#__users', 'u') .
				' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.published') . ' = 1')
			->where($db->quoteName('p.created') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('p.created') . ' <= ' . $db->quote($dateTo))
			->order($db->quoteName('p.created') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Build a flat array of all report data for CSV export.
	 *
	 * Sections are separated by blank rows for readability.
	 *
	 * @return  array  Array of arrays (rows)
	 */
	public function getExportData(): array
	{
		$filters       = $this->getFilters();
		$eventsByType  = $this->getEventsByType();
		$totalAttend   = $this->getTotalAttendance();
		$topAttendees  = $this->getTopAttendees();
		$topRiders     = $this->getTopRiders();
		$dues          = $this->getDuesSummary();
		$newMembers    = $this->getNewMembers();

		$totalEvents = EventTypeBuckets::total($eventsByType);

		$rows = [];

		// Header
		$rows[] = ['Wendfold - Chapter Activity Report'];
		$rows[] = ['Date Range', $filters->date_from . ' to ' . $filters->date_to];
		$rows[] = ['Generated', date('Y-m-d H:i:s')];
		$rows[] = [];

		// Events section
		$rows[] = ['EVENTS'];
		$rows[] = ['Type', 'Count'];

		foreach ($eventsByType as $bucket)
		{
			$rows[] = [EventTypeHelper::label((string) $bucket['title']), $bucket['count']];
		}

		$rows[] = ['TOTAL EVENTS', $totalEvents];
		$rows[] = ['TOTAL ATTENDANCE', $totalAttend];
		$rows[] = [];

		// Top attendees
		$rows[] = ['TOP 10 MEMBERS BY ATTENDANCE'];
		$rows[] = ['Rank', 'Member Name', 'Member Number', 'Events Attended'];

		foreach ($topAttendees as $rank => $row)
		{
			$rows[] = [$rank + 1, $row->member_name, $row->member_number, $row->attendance_count];
		}

		$rows[] = [];

		// Top riders
		$rows[] = ['TOP 10 MEMBERS BY MILES RIDDEN'];
		$rows[] = ['Rank', 'Member Name', 'Member Number', 'Total Miles'];

		foreach ($topRiders as $rank => $row)
		{
			$rows[] = [$rank + 1, $row->member_name, $row->member_number, $row->total_miles];
		}

		$rows[] = [];

		// Dues
		$rows[] = ['DUES COLLECTION'];
		$rows[] = ['Members Paid', $dues->members_paid];
		$rows[] = ['Total Collected', '$' . $dues->total_collected];
		$rows[] = [];

		// New members
		$rows[] = ['NEW MEMBERS ADDED'];
		$rows[] = ['Member Name', 'Member Number', 'Join Date'];

		foreach ($newMembers as $row)
		{
			$rows[] = [$row->member_name, $row->member_number, $row->join_date];
		}

		return $rows;
	}
}
