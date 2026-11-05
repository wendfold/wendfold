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

/**
 * Group event counts by the event type row, not by a fixed list of ids.
 *
 * A chapter can add a type. That type is its own bucket, labeled with the
 * title stored on event_types. Two types that share a behavior stay separate.
 *
 * @since  1.0.0
 */
final class EventTypeBuckets
{
	/**
	 * Counts keyed by event_types.id.
	 *
	 * Each input row uses id, title, behavior, and count. A missing id is
	 * left out. Later rows for the same id add to the count and keep the
	 * first title.
	 *
	 * @param   array  $rows  Count rows from an event type query.
	 *
	 * @return  array<int, array{id: int, title: string, behavior: int, count: int}>
	 *
	 * @since   1.0.0
	 */
	public static function counts(array $rows): array
	{
		$buckets = [];

		foreach ($rows as $row)
		{
			$id = (int) ($row['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			if (!isset($buckets[$id]))
			{
				$buckets[$id] = [
					'id'       => $id,
					'title'    => trim((string) ($row['title'] ?? '')),
					'behavior' => (int) ($row['behavior'] ?? 0),
					'count'    => 0,
				];
			}

			$buckets[$id]['count'] += (int) ($row['count'] ?? 0);
		}

		ksort($buckets);

		return $buckets;
	}

	/**
	 * Sum of bucket counts.
	 *
	 * @param   array  $buckets  Result of counts().
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function total(array $buckets): int
	{
		$total = 0;

		foreach ($buckets as $bucket)
		{
			$total += (int) ($bucket['count'] ?? 0);
		}

		return $total;
	}

	/**
	 * Month buckets keyed by YYYY-MM, then by event_types.id.
	 *
	 * Each input row uses month, id, title, behavior, and count.
	 *
	 * @param   array  $rows  Monthly count rows.
	 *
	 * @return  array<string, array<int, array{id: int, title: string, behavior: int, count: int}>>
	 *
	 * @since   1.0.0
	 */
	public static function monthly(array $rows): array
	{
		$byMonth = [];

		foreach ($rows as $row)
		{
			$month = (string) ($row['month'] ?? '');

			if ($month === '')
			{
				continue;
			}

			if (!isset($byMonth[$month]))
			{
				$byMonth[$month] = [];
			}

			$folded = array_values($byMonth[$month]);
			$folded[] = $row;
			$byMonth[$month] = self::counts($folded);
		}

		ksort($byMonth);

		return $byMonth;
	}

	/**
	 * Event types that appear in a monthly result, keyed by id.
	 *
	 * @param   array  $monthly  Result of monthly().
	 *
	 * @return  array<int, array{id: int, title: string, behavior: int}>
	 *
	 * @since   1.0.0
	 */
	public static function columns(array $monthly): array
	{
		$columns = [];

		foreach ($monthly as $types)
		{
			foreach ($types as $id => $bucket)
			{
				$columns[(int) $id] = [
					'id'       => (int) $id,
					'title'    => (string) ($bucket['title'] ?? ''),
					'behavior' => (int) ($bucket['behavior'] ?? 0),
				];
			}
		}

		ksort($columns);

		return $columns;
	}

	/**
	 * Overall average and one average per event type.
	 *
	 * Each input row uses id, title, behavior, and attendance. Types with
	 * no events are omitted, including the old fixed ids 1 to 5.
	 *
	 * @param   array  $events  One row per event that has attendance.
	 *
	 * @return  array{overall: float, by_type: array<int, array{id: int, title: string, behavior: int, average: float}>}
	 *
	 * @since   1.0.0
	 */
	public static function averages(array $events): array
	{
		if ($events === [])
		{
			return [
				'overall' => 0.0,
				'by_type' => [],
			];
		}

		$totalAttendance = 0;
		$sums            = [];
		$counts          = [];

		foreach ($events as $event)
		{
			$id = (int) ($event['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			$attendance = (int) ($event['attendance'] ?? 0);
			$totalAttendance += $attendance;

			if (!isset($sums[$id]))
			{
				$sums[$id] = [
					'id'       => $id,
					'title'    => trim((string) ($event['title'] ?? '')),
					'behavior' => (int) ($event['behavior'] ?? 0),
					'sum'      => 0,
				];
				$counts[$id] = 0;
			}

			$sums[$id]['sum'] += $attendance;
			$counts[$id] += 1;
		}

		$counted = array_sum($counts);
		$byType  = [];

		foreach ($sums as $id => $sum)
		{
			$byType[$id] = [
				'id'       => $sum['id'],
				'title'    => $sum['title'],
				'behavior' => $sum['behavior'],
				'average'  => $counts[$id] > 0 ? round($sum['sum'] / $counts[$id], 1) : 0.0,
			];
		}

		ksort($byType);

		return [
			'overall' => $counted > 0 ? round($totalAttendance / $counted, 1) : 0.0,
			'by_type' => $byType,
		];
	}
}
