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

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Service\MetricAggregation;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;

/**
 * Reads on metric_entries.
 *
 * Totals are one table. Names are loaded afterwards, not joined into the total.
 *
 * @since  1.0.0
 */
final class MetricQuery
{
	/**
	 * Published dashboard metrics, in ordering.
	 *
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public static function dashboardDefinitions(DatabaseInterface $db): array
	{
		$published = 1;
		$shown = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('metric_key'),
				$db->quoteName('title'),
				$db->quoteName('unit_label'),
				$db->quoteName('decimals'),
				$db->quoteName('aggregation'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('show_on_dashboard') . ' = :shown')
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':shown', $shown, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Metric id for a stable key, or 0.
	 *
	 * @param   DatabaseInterface  $db   Database driver.
	 * @param   string             $key  metric_key.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function idForKey(DatabaseInterface $db, string $key): int
	{
		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('metric_key') . ' = :metricKey')
			->bind(':metricKey', $key);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Sum, count, and max for one member in a date range, keyed by metric id.
	 *
	 * @param   DatabaseInterface  $db         Database driver.
	 * @param   integer            $profileId  Profile id.
	 * @param   string             $start      Inclusive local date.
	 * @param   string             $end        Inclusive local date.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	public static function profileAggregate(DatabaseInterface $db, int $profileId, string $start, string $end): array
	{
		self::requireDay($start);
		self::requireDay($end);
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('metric_id'),
				'SUM(' . $db->quoteName('value') . ') AS total_sum',
				'COUNT(*) AS total_count',
				'MAX(' . $db->quoteName('value') . ') AS total_max',
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('profile_id') . ' = :profileId')
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('entry_date') . ' >= :rangeStart')
			->where($db->quoteName('entry_date') . ' <= :rangeEnd')
			->group($db->quoteName('metric_id'))
			->bind(':profileId', $profileId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':rangeStart', $start)
			->bind(':rangeEnd', $end);

		return $db->setQuery($query)->loadObjectList('metric_id') ?: [];
	}

	/**
	 * One member's total for one metric.
	 *
	 * @param   DatabaseInterface  $db           Database driver.
	 * @param   integer            $profileId    Profile id.
	 * @param   integer            $metricId     Metric id.
	 * @param   string             $start        Inclusive local date.
	 * @param   string             $end          Inclusive local date.
	 * @param   integer            $aggregation  MetricAggregation value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function totalForProfile(
		DatabaseInterface $db,
		int $profileId,
		int $metricId,
		string $start,
		string $end,
		int $aggregation
	): string {
		$rows = self::profileAggregate($db, $profileId, $start, $end);
		$row = $rows[$metricId] ?? null;

		if ($row === null)
		{
			return '0';
		}

		return self::pickedTotal($row, $aggregation);
	}

	/**
	 * Leaderboard rows: profile_id and total. No profile join.
	 *
	 * @param   DatabaseInterface  $db           Database driver.
	 * @param   integer            $metricId     Metric id.
	 * @param   string             $start        Inclusive local date.
	 * @param   string             $end          Inclusive local date.
	 * @param   integer            $aggregation  MetricAggregation value.
	 * @param   integer            $limit        Maximum rows. 0 means no limit.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public static function leaderboard(
		DatabaseInterface $db,
		int $metricId,
		string $start,
		string $end,
		int $aggregation,
		int $limit = 0
	): array {
		self::requireDay($start);
		self::requireDay($end);
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('profile_id'),
				self::aggregateSql($db, $aggregation) . ' AS ' . $db->quoteName('total'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('metric_id') . ' = :metricId')
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('entry_date') . ' >= :rangeStart')
			->where($db->quoteName('entry_date') . ' <= :rangeEnd')
			->group($db->quoteName('profile_id'))
			->order($db->quoteName('total') . ' DESC')
			->bind(':metricId', $metricId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':rangeStart', $start)
			->bind(':rangeEnd', $end);

		if ($limit > 0)
		{
			$query->setLimit($limit);
		}

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Chapter totals for one metric: summed amount and distinct members.
	 *
	 * @param   DatabaseInterface  $db           Database driver.
	 * @param   integer            $metricId     Metric id.
	 * @param   string             $start        Inclusive local date.
	 * @param   string             $end          Inclusive local date.
	 * @param   integer            $aggregation  MetricAggregation value.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public static function chapterTotal(
		DatabaseInterface $db,
		int $metricId,
		string $start,
		string $end,
		int $aggregation
	): object {
		self::requireDay($start);
		self::requireDay($end);
		$published = 1;
		$query = $db->createQuery()
			->select([
				self::aggregateSql($db, $aggregation) . ' AS ' . $db->quoteName('total'),
				'COUNT(DISTINCT ' . $db->quoteName('profile_id') . ') AS ' . $db->quoteName('members'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('metric_id') . ' = :metricId')
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('entry_date') . ' >= :rangeStart')
			->where($db->quoteName('entry_date') . ' <= :rangeEnd')
			->bind(':metricId', $metricId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':rangeStart', $start)
			->bind(':rangeEnd', $end);
		$row = $db->setQuery($query)->loadObject();

		return $row ?: (object) ['total' => 0, 'members' => 0];
	}

	/**
	 * Latest counted entry date for the profiles in a range, keyed by profile id.
	 *
	 * @param   DatabaseInterface  $db          Database driver.
	 * @param   string             $start       Inclusive local date.
	 * @param   string             $end         Inclusive local date.
	 * @param   integer[]          $profileIds  Profiles to include. Empty means none.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public static function latestDates(DatabaseInterface $db, string $start, string $end, array $profileIds): array
	{
		$profileIds = self::ids($profileIds);

		if ($profileIds === [])
		{
			return [];
		}

		self::requireDay($start);
		self::requireDay($end);
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('profile_id'),
				'MAX(' . $db->quoteName('entry_date') . ') AS ' . $db->quoteName('latest'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('entry_date') . ' >= :rangeStart')
			->where($db->quoteName('entry_date') . ' <= :rangeEnd')
			->whereIn($db->quoteName('profile_id'), $profileIds, ParameterType::INTEGER)
			->group($db->quoteName('profile_id'))
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':rangeStart', $start)
			->bind(':rangeEnd', $end);
		$rows = $db->setQuery($query)->loadObjectList() ?: [];
		$dates = [];

		foreach ($rows as $row)
		{
			$dates[(int) $row->profile_id] = (string) $row->latest;
		}

		return $dates;
	}

	/**
	 * Attach member name and number to rows that already have profile_id.
	 *
	 * @param   DatabaseInterface  $db    Database driver.
	 * @param   object[]           $rows  Leaderboard rows.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public static function withNames(DatabaseInterface $db, array $rows): array
	{
		$ids = [];

		foreach ($rows as $row)
		{
			$ids[] = (int) ($row->profile_id ?? 0);
		}

		$ids = self::ids($ids);

		if ($ids === [])
		{
			return $rows;
		}

		$query = $db->createQuery()
			->select([
				$db->quoteName('p.id'),
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'INNER',
				$db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->whereIn($db->quoteName('p.id'), $ids, ParameterType::INTEGER);
		$people = $db->setQuery($query)->loadObjectList('id') ?: [];

		foreach ($rows as $row)
		{
			$person = $people[(int) $row->profile_id] ?? null;
			$row->member_name = $person->member_name ?? '';
			$row->member_number = $person->member_number ?? '';
		}

		return $rows;
	}

	/**
	 * Choose the aggregate column the definition asks for.
	 *
	 * @param   object   $row          Row with total_sum, total_count, and total_max.
	 * @param   integer  $aggregation  MetricAggregation value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function pickedTotal(object $row, int $aggregation): string
	{
		$field = match (MetricAggregation::fromStored($aggregation)) {
			MetricAggregation::Count => 'total_count',
			MetricAggregation::Max => 'total_max',
			default => 'total_sum',
		};

		return (string) ($row->$field ?? '0');
	}

	/**
	 * SQL aggregate expression. The function name is not taken from input.
	 *
	 * @param   DatabaseInterface  $db           Database driver.
	 * @param   integer            $aggregation  MetricAggregation value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function aggregateSql(DatabaseInterface $db, int $aggregation): string
	{
		return match (MetricAggregation::fromStored($aggregation)) {
			MetricAggregation::Count => 'COUNT(*)',
			MetricAggregation::Max => 'MAX(' . $db->quoteName('value') . ')',
			default => 'SUM(' . $db->quoteName('value') . ')',
		};
	}

	/**
	 * Positive unique ids.
	 *
	 * @param   array  $ids  Raw ids.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	private static function ids(array $ids): array
	{
		$clean = [];

		foreach ($ids as $id)
		{
			$id = (int) $id;

			if ($id > 0)
			{
				$clean[$id] = $id;
			}
		}

		return array_values($clean);
	}

	/**
	 * Refuse a date that is not Y-m-d.
	 *
	 * @param   string  $day  Candidate day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private static function requireDay(string $day): void
	{
		if (!MetricPeriod::isDay($day))
		{
			throw new \InvalidArgumentException('Metric period bound must be a calendar day.');
		}
	}
}
