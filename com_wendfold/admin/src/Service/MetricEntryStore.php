<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Persistence used only by MetricService.
 *
 * Application code does not call this. MetricService is the writer.
 *
 * @since  1.0.0
 */
interface MetricEntryStore
{
	/**
	 * How many transactions or savepoints are open on the backing connection.
	 *
	 * Award recompute must see 0 here so lockAward is not nested inside a
	 * caller's outer transaction.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function openTransactionDepth(): int;

	/**
	 * Start a transaction or a savepoint.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void;

	/**
	 * Commit the transaction or release the savepoint.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void;

	/**
	 * Roll the transaction or savepoint back.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void;

	/**
	 * Insert or update one entry.
	 *
	 * The key is (source_type, source_id, metric_id). A null source_id never
	 * matches an existing row, matching MySQL unique-index NULL handling.
	 *
	 * @param   array  $row  Entry columns, including actor_id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upsert(array $row): void;

	/**
	 * Set published to -2 for one source row.
	 *
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 *
	 * @return  boolean  True when at least one row changed.
	 *
	 * @since   1.0.0
	 */
	public function trashSource(int $sourceType, int $sourceId): bool;

	/**
	 * Trash attendance entries for one attendance row on one event.
	 *
	 * Both event_id and source_id must match. A mismatched pair changes nothing.
	 *
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $attendanceId  Attendance id (source_id).
	 *
	 * @return  boolean  True when at least one row changed.
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceSource(int $eventId, int $attendanceId): bool;

	/**
	 * Trash attendance entries for an event whose metric is no longer credited.
	 *
	 * An empty metric list trashes every attendance entry for the event.
	 *
	 * @param   integer    $eventId    Event id.
	 * @param   integer[]  $metricIds  Metric ids that are still credited.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function trashAttendanceMetricsExcept(int $eventId, array $metricIds): void;

	/**
	 * Hard-delete every metric entry for an event.
	 *
	 * Used when the event row itself is deleted so no orphan ledger rows remain.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteByEvent(int $eventId): void;

	/**
	 * Distinct profile ids on metric entries for one event.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForEvent(int $eventId): array;

	/**
	 * Hard-delete attendance metric entries whose event_id is gone.
	 *
	 * @return  integer  Number of rows removed.
	 *
	 * @since   1.0.0
	 */
	public function deleteOrphanedAttendanceEntries(): int;

	/**
	 * Set published for every entry of one source to $published.
	 *
	 * Used so activity entry state always mirrors the activity (0 pending,
	 * 1 approved, -2 rejected) on every transition.
	 *
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 * @param   integer  $published   Target published value.
	 *
	 * @return  boolean  True when at least one row changed.
	 *
	 * @since   1.0.0
	 */
	public function setSourcePublished(int $sourceType, int $sourceId, int $published): bool;

	/**
	 * Attendance row with the event's local date, or null.
	 *
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function attendance(int $attendanceId): ?object;

	/**
	 * Credit rows for an event.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventCredits(int $eventId): array;

	/**
	 * Event local date, or null when the event is missing.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function eventDate(int $eventId): ?string;

	/**
	 * Event published state, or null when the event is missing.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventPublished(int $eventId): ?int;

	/**
	 * Event status (1 Scheduled … 3 Cancelled …), or null when missing.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function eventStatus(int $eventId): ?int;

	/**
	 * Attendance rows for an event.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function eventAttendances(int $eventId): array;

	/**
	 * Distinct profile ids on entries for one source row.
	 *
	 * @param   integer  $sourceType  MetricSource value.
	 * @param   integer  $sourceId    Source primary key.
	 *
	 * @return  integer[]
	 *
	 * @since   1.0.0
	 */
	public function profileIdsForSource(int $sourceType, int $sourceId): array;
}
