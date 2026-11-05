<?php

/**
 * Wendfold - in-memory metric ledger for MetricService tests.
 *
 * NULL source ids do not match, matching a MySQL unique index.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Service\MetricEntryStore;

/**
 * Ledger that applies the metric entry unique key in PHP.
 *
 * @since  1.0.0
 */
final class MemoryMetricEntryStore implements MetricEntryStore
{
	/**
	 * Stored entries.
	 *
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	public array $entries = [];

	/**
	 * Attendance fixtures, keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $attendances = [];

	/**
	 * Credits keyed by event id.
	 *
	 * @var    array<int, list<object>>
	 * @since  1.0.0
	 */
	public array $credits = [];

	/**
	 * Event dates keyed by event id.
	 *
	 * @var    array<int, string>
	 * @since  1.0.0
	 */
	public array $eventDates = [];

	/**
	 * Event published states keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStates = [];

	/**
	 * Event status values keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStatuses = [];

	/**
	 * Throw on this upsert number. 0 disables the failure.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $failOnUpsert = 0;

	/**
	 * Upserts seen in this store.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $upserts = 0;

	/**
	 * Snapshots taken by begin().
	 *
	 * @var    list<list<array<string, mixed>>>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * Extra depth tests use to simulate a caller's open outer transaction.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $simulatedOuterTxnDepth = 0;

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function openTransactionDepth(): int
	{
		return \count($this->snapshots) + $this->simulatedOuterTxnDepth;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void
	{
		$this->snapshots[] = $this->entries;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void
	{
		array_pop($this->snapshots);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void
	{
		$snapshot = array_pop($this->snapshots);
		$this->entries = $snapshot ?? [];
	}

	/**
	 * @param   array  $row  Entry columns.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upsert(array $row): void
	{
		$this->upserts++;

		if ($this->failOnUpsert > 0 && $this->upserts === $this->failOnUpsert)
		{
			throw new \RuntimeException('Metric write failed.');
		}

		$sourceId = $row['source_id'];
		$stored = [
			'profile_id'  => (int) $row['profile_id'],
			'metric_id'   => (int) $row['metric_id'],
			'entry_date'  => (string) $row['entry_date'],
			'value'       => (string) $row['value'],
			'event_id'    => $row['event_id'] === null ? null : (int) $row['event_id'],
			'source_type' => (int) $row['source_type'],
			'source_id'   => $sourceId === null ? null : (int) $sourceId,
			'published'   => (int) $row['published'],
			'note'        => (string) $row['note'],
		];

		if ($stored['source_id'] === null)
		{
			$this->entries[] = $stored;

			return;
		}

		foreach ($this->entries as $index => $existing)
		{
			if ($existing['source_type'] === $stored['source_type']
				&& $existing['source_id'] === $stored['source_id']
				&& $existing['metric_id'] === $stored['metric_id'])
			{
				$this->entries[$index] = $stored;

				return;
			}
		}

		$this->entries[] = $stored;
	}

	/**
	 * @param   integer  $sourceType  Source type.
	 * @param   integer  $sourceId    Source id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function trashSource(int $sourceType, int $sourceId): bool
	{
		$changed = false;

		foreach ($this->entries as $index => $existing)
		{
			if ($existing['source_type'] === $sourceType
				&& $existing['source_id'] === $sourceId
				&& $existing['published'] !== -2)
			{
				$this->entries[$index]['published'] = -2;
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceSource(int $eventId, int $attendanceId): bool
	{
		$changed = false;

		foreach ($this->entries as $index => $existing)
		{
			if ($existing['source_type'] === 1
				&& $existing['source_id'] === $attendanceId
				&& $existing['event_id'] === $eventId
				&& $existing['published'] !== -2)
			{
				$this->entries[$index]['published'] = -2;
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param   integer    $eventId    Event id.
	 * @param   integer[]  $metricIds  Metrics that remain.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceMetricsExcept(int $eventId, array $metricIds): void
	{
		$keep = [];

		foreach ($metricIds as $metricId)
		{
			$keep[(int) $metricId] = true;
		}

		foreach ($this->entries as $index => $existing)
		{
			if ($existing['source_type'] === 1
				&& $existing['event_id'] === $eventId
				&& !isset($keep[$existing['metric_id']]))
			{
				$this->entries[$index]['published'] = -2;
			}
		}
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteByEvent(int $eventId): void
	{
		$kept = [];

		foreach ($this->entries as $existing)
		{
			if ($existing['event_id'] !== $eventId)
			{
				$kept[] = $existing;
			}
		}

		$this->entries = $kept;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForEvent(int $eventId): array
	{
		$ids = [];

		foreach ($this->entries as $existing)
		{
			if ($existing['event_id'] === $eventId)
			{
				$profileId = (int) $existing['profile_id'];
				$ids[$profileId] = $profileId;
			}
		}

		return array_values($ids);
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function deleteOrphanedAttendanceEntries(): int
	{
		$known = array_unique(array_merge(array_keys($this->eventDates), array_keys($this->eventStates)));
		$knownLookup = [];

		foreach ($known as $eventId)
		{
			$knownLookup[(int) $eventId] = true;
		}

		$kept = [];
		$removed = 0;

		foreach ($this->entries as $existing)
		{
			$eventId = $existing['event_id'];

			if ($existing['source_type'] === 1
				&& $eventId !== null
				&& !isset($knownLookup[(int) $eventId]))
			{
				$removed++;
				continue;
			}

			$kept[] = $existing;
		}

		$this->entries = $kept;

		return $removed;
	}

	/**
	 * @param   integer  $sourceType  Source type.
	 * @param   integer  $sourceId    Source id.
	 * @param   integer  $published   Target published value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function setSourcePublished(int $sourceType, int $sourceId, int $published): bool
	{
		$changed = false;

		foreach ($this->entries as $index => $existing)
		{
			if ($existing['source_type'] === $sourceType
				&& $existing['source_id'] === $sourceId
				&& $existing['published'] !== $published)
			{
				$this->entries[$index]['published'] = $published;
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function attendance(int $attendanceId): ?object
	{
		return $this->attendances[$attendanceId] ?? null;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventCredits(int $eventId): array
	{
		return $this->credits[$eventId] ?? [];
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function eventDate(int $eventId): ?string
	{
		return $this->eventDates[$eventId] ?? null;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventPublished(int $eventId): ?int
	{
		return $this->eventStates[$eventId] ?? null;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventStatus(int $eventId): ?int
	{
		return $this->eventStatuses[$eventId] ?? null;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventAttendances(int $eventId): array
	{
		$rows = [];

		foreach ($this->attendances as $attendance)
		{
			if ((int) $attendance->event_id === $eventId)
			{
				$rows[] = $attendance;
			}
		}

		return $rows;
	}

	/**
	 * @param   integer  $sourceType  Source type.
	 * @param   integer  $sourceId    Source id.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForSource(int $sourceType, int $sourceId): array
	{
		$ids = [];

		foreach ($this->entries as $entry)
		{
			if ((int) $entry['source_type'] === $sourceType && (int) ($entry['source_id'] ?? 0) === $sourceId)
			{
				$ids[(int) $entry['profile_id']] = (int) $entry['profile_id'];
			}
		}

		return array_values($ids);
	}
}
