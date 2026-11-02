<?php

/**
 * Wendfold - Event Attendance Trends Report Model
 *
 * Provides month-by-month attendance data, per-type breakdowns,
 * per-event detail rows, and averages for a configurable date range.
 *
 * Schema verified against install.mysql.utf8.sql:
 *   events:    id, title, event_date, event_type_id, published
 *   attendance: id, event_id, profile_id, check_in_time
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBuckets;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper;

class AttendancetrendsModel extends BaseDatabaseModel
{
	//-------------------------------------------------------------
	// Filters
	//-------------------------------------------------------------

	/**
	 * Return the current date-range filter, persisted in user state.
	 * Defaults to the current calendar year.
	 *
	 * @return  object  {date_from, date_to}
	 */
	public function getFilters(): object
	{
		$app = Factory::getApplication();

		$defaultFrom = date('Y') . '-01-01';
		$defaultTo   = date('Y') . '-12-31';

		$dateFrom = $app->getUserStateFromRequest(
			'com_wendfold.attendancetrends.date_from',
			'date_from',
			$defaultFrom,
			'string'
		);
		$dateTo = $app->getUserStateFromRequest(
			'com_wendfold.attendancetrends.date_to',
			'date_to',
			$defaultTo,
			'string'
		);

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

	//-------------------------------------------------------------
	// Data methods
	//-------------------------------------------------------------

	/**
	 * Month-by-month total attendance for all event types combined.
	 *
	 * Returns array keyed by "YYYY-MM", value = total attendance count.
	 *
	 * @return  array<string, int>
	 */
	public function getMonthlyTotals(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				'DATE_FORMAT(' . $db->quoteName('e.event_date') . ', \'%Y-%m\') AS ym',
				'COUNT(' . $db->quoteName('a.id') . ') AS attendance_count',
			])
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e') .
					' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->join(
				'LEFT',
				$db->quoteName('#__wendfold_event_types', 'et') .
					' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')
			)
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('e.event_date') . ' <= ' . $db->quote($filters->date_to))
			->group('DATE_FORMAT(' . $db->quoteName('e.event_date') . ', \'%Y-%m\')')
			->order('ym ASC');

		$rows   = $db->setQuery($query)->loadObjectList() ?: [];
		$result = [];

		foreach ($rows as $row)
		{
			$result[$row->ym] = (int) $row->attendance_count;
		}

		return $result;
	}

	/**
	 * Month-by-month attendance, one bucket per event type.
	 *
	 * Keyed by YYYY-MM, then by event_types.id. The title is the type's
	 * own title, including a type a chapter added.
	 *
	 * @return  array<string, array<int, array{id: int, title: string, behavior: int, count: int}>>
	 */
	public function getMonthlyByType(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				'DATE_FORMAT(' . $db->quoteName('e.event_date') . ', \'%Y-%m\') AS ym',
				$db->quoteName('e.event_type_id'),
				$db->quoteName('et.behavior', 'event_behavior'),
				$db->quoteName('et.title', 'event_type_title'),
				'COUNT(' . $db->quoteName('a.id') . ') AS attendance_count',
			])
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e') .
					' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->join(
				'LEFT',
				$db->quoteName('#__wendfold_event_types', 'et') .
					' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')
			)
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('e.event_date') . ' <= ' . $db->quote($filters->date_to))
			->group([
				'DATE_FORMAT(' . $db->quoteName('e.event_date') . ', \'%Y-%m\')',
				$db->quoteName('e.event_type_id'),
				$db->quoteName('et.behavior'),
				$db->quoteName('et.title'),
			])
			->order('ym ASC, e.event_type_id ASC');

		$rows  = $db->setQuery($query)->loadObjectList() ?: [];
		$input = [];
		$typeId = 'event_type_id';
		$typeTitle = 'event_type_title';
		$typeBehavior = 'event_behavior';
		$attendanceCount = 'attendance_count';

		foreach ($rows as $row)
		{
			$input[] = [
				'month'    => (string) $row->ym,
				'id'       => (int) $row->$typeId,
				'title'    => (string) ($row->$typeTitle ?? ''),
				'behavior' => (int) ($row->$typeBehavior ?? 0),
				'count'    => (int) $row->$attendanceCount,
			];
		}

		return EventTypeBuckets::monthly($input);
	}

	/**
	 * Per-event detail: title, date, type, attendance count.
	 * Sorted by event_date ascending.
	 *
	 * Only events with at least 1 attendee are included.
	 *
	 * @return  array  Each row: {event_id, title, event_date, event_type, attendance_count}
	 */
	public function getEventDetail(): array
	{
		$filters = $this->getFilters();
		$db      = $this->getDatabase();

		$query = $db->createQuery()
			->select([
				$db->quoteName('e.id', 'event_id'),
				$db->quoteName('e.title'),
				$db->quoteName('e.event_date'),
				$db->quoteName('e.event_type_id'),
				$db->quoteName('et.behavior', 'event_behavior'),
				$db->quoteName('et.title', 'event_type_title'),
				'COUNT(' . $db->quoteName('a.id') . ') AS attendance_count',
			])
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_events', 'e') .
					' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.event_id')
			)
			->join(
				'LEFT',
				$db->quoteName('#__wendfold_event_types', 'et') .
					' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')
			)
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.event_date') . ' >= ' . $db->quote($filters->date_from))
			->where($db->quoteName('e.event_date') . ' <= ' . $db->quote($filters->date_to))
			->group([
				$db->quoteName('e.id'),
				$db->quoteName('e.title'),
				$db->quoteName('e.event_date'),
				$db->quoteName('e.event_type_id'),
				$db->quoteName('et.behavior'),
				$db->quoteName('et.title'),
			])
			->order($db->quoteName('e.event_date') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Average attendance per event overall and per event type.
	 *
	 * Returns object:
	 *   {overall, by_type: buckets keyed by event_types.id}
	 *
	 * @return  object
	 */
	public function getAverages(): object
	{
		$input = [];

		$typeId = 'event_type_id';
		$typeTitle = 'event_type_title';
		$typeBehavior = 'event_behavior';
		$attendanceCount = 'attendance_count';

		foreach ($this->getEventDetail() as $row)
		{
			$input[] = [
				'id'         => (int) ($row->$typeId ?? 0),
				'title'      => (string) ($row->$typeTitle ?? ''),
				'behavior'   => (int) ($row->$typeBehavior ?? 0),
				'attendance' => (int) $row->$attendanceCount,
			];
		}

		return (object) EventTypeBuckets::averages($input);
	}

	/**
	 * Build a flat array for CSV export.
	 *
	 * @return  array  Array of arrays (rows)
	 */
	public function getExportData(): array
	{
		$filters      = $this->getFilters();
		$monthlyTots  = $this->getMonthlyTotals();
		$monthlyByTyp = $this->getMonthlyByType();
		$eventDetail  = $this->getEventDetail();
		$averages     = $this->getAverages();

		$rows = [];

		// Header block
		$rows[] = ['Wendfold - Event Attendance Trends Report'];
		$rows[] = ['Date Range', $filters->date_from . ' to ' . $filters->date_to];
		$rows[] = ['Generated', date('Y-m-d H:i:s')];
		$rows[] = [];

		// Average block
		$rows[] = ['AVERAGES'];
		$rows[] = ['Average Attendance (All Events)', $averages->overall];

		foreach ($averages->by_type as $bucket)
		{
			$rows[] = [
				'Average Attendance (' . EventTypeHelper::label((string) $bucket['title']) . ')',
				$bucket['average'],
			];
		}

		$rows[] = [];

		// Monthly totals with type breakdown
		$columns = EventTypeBuckets::columns($monthlyByTyp);
		$header  = ['Month', 'Total'];

		foreach ($columns as $column)
		{
			$header[] = EventTypeHelper::label((string) $column['title']);
		}

		$rows[] = ['MONTHLY ATTENDANCE'];
		$rows[] = $header;

		// Collect all months in the monthly data (union of totals and type keys)
		$allMonths = array_unique(array_merge(
			array_keys($monthlyTots),
			array_keys($monthlyByTyp)
		));
		sort($allMonths);

		foreach ($allMonths as $ym)
		{
			$typeCols = [];

			foreach ($columns as $column)
			{
				$typeCols[] = $monthlyByTyp[$ym][$column['id']]['count'] ?? 0;
			}

			$rows[] = array_merge(
				[$ym, $monthlyTots[$ym] ?? 0],
				$typeCols
			);
		}

		$rows[] = [];

		// Per-event detail
		$rows[] = ['EVENT DETAIL'];
		$rows[] = ['Date', 'Event Title', 'Type', 'Attendance'];

		foreach ($eventDetail as $ev)
		{
			$typeTitle = (string) ($ev->event_type_title ?? ''); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

			$rows[] = [
				$ev->event_date,
				$ev->title,
				EventTypeHelper::label($typeTitle),
				(int) $ev->attendance_count,
			];
		}

		return $rows;
	}
}
