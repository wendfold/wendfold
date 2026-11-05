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

// Database column names are snake_case. Closures hold the multi-row writes.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
// phpcs:disable Joomla.ControlStructures.ControlStructuresBrackets.SpaceBeforeBrace
// phpcs:disable PEAR.Functions.FunctionCallSignature.CloseBracketLine
// phpcs:disable PEAR.Functions.FunctionCallSignature.Indent

use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;

/**
 * The only writer of metric_entries.
 *
 * Attendance, activities, manual adjustments, imports, and volunteer credits
 * all go through this class. Repeating a keyed write updates the same row.
 * A failure is thrown after the transaction rolls back.
 *
 * @since  1.0.0
 */
final class MetricService
{
	/**
	 * When true, award recomputes are queued until flushAwardRecompute().
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $deferAwards = false;

	/**
	 * Profile ids queued while deferAwards is true.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	private array $deferredProfiles = [];

	/**
	 * @param   MetricEntryStore      $store    Persistence.
	 * @param   integer               $actorId  User id stored on created_by and modified_by.
	 * @param   AwardRecomputer|null  $awards   Optional awards recompute hook.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private MetricEntryStore $store,
		private int $actorId,
		private ?AwardRecomputer $awards = null
	)
	{
	}

	/**
	 * Queue award recomputes across many writes, then flush once.
	 *
	 * Use around CSV import, sync, and batch attendance/activity loops.
	 *
	 * @param   boolean  $defer  True to queue; false leaves queued ids until flush.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deferAwardRecompute(bool $defer = true): void
	{
		$this->deferAwards = $defer;
	}

	/**
	 * Run one recompute for every profile queued while deferred.
	 *
	 * Call this only after the surrounding database transaction has committed
	 * so AwardEngine opens its own transaction where lockAward is first.
	 *
	 * @return  void
	 *
	 * @throws  \LogicException  When the metric store still has an open txn.
	 *
	 * @since   1.0.0
	 */
	public function flushAwardRecompute(): void
	{
		$this->assertAwardRecomputeOutsideCallerTransaction();
		$profiles = array_values($this->deferredProfiles);
		$this->deferredProfiles = [];
		$this->deferAwards = false;
		$this->runAwardRecompute($profiles);
	}

	/**
	 * Drop queued award recomputes without running them (after a rollback).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function discardDeferredAwardRecompute(): void
	{
		$this->deferredProfiles = [];
		$this->deferAwards = false;
	}

	/**
	 * Profile ids queued while deferred, for restore after a rolled-back write.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	public function deferredAwardProfileIds(): array
	{
		return array_values($this->deferredProfiles);
	}

	/**
	 * Replace the deferred queue (e.g. after rolling back a write that queued ids).
	 *
	 * @param   integer[]  $profileIds  Ids to keep queued.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function replaceDeferredAwardProfiles(array $profileIds): void
	{
		$this->deferredProfiles = [];

		foreach ($profileIds as $profileId)
		{
			$profileId = (int) $profileId;

			if ($profileId > 0)
			{
				$this->deferredProfiles[$profileId] = $profileId;
			}
		}
	}

	/**
	 * Writer backed by the component database.
	 *
	 * @param   DatabaseInterface  $db                Database driver.
	 * @param   integer            $actorId           User id.
	 * @param   boolean            $recomputeAwards   Whether to recompute awards after writes.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromDatabase(
		DatabaseInterface $db,
		int $actorId,
		bool $recomputeAwards = true
	): self
	{
		$awards = $recomputeAwards ? AwardEngine::fromDatabase($db) : null;

		return new self(new SqlMetricEntryStore($db, $actorId), $actorId, $awards);
	}

	/**
	 * One entry per event credit for a new attendance row.
	 *
	 * entry_date is the event's local wall-clock date. Calling this again for
	 * the same attendance updates those rows instead of adding more. The
	 * attendance row must belong to $eventId.
	 *
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $attendanceId  Attendance id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function creditAttendance(int $eventId, int $attendanceId): void
	{
		$profileIds = [];
		$this->transaction(function () use ($eventId, $attendanceId, &$profileIds): void {
			$attendance = $this->store->attendance($attendanceId);

			if ($attendance === null
				|| (int) $attendance->event_id !== $eventId
				|| !MetricPeriod::isDay((string) $attendance->event_date)
			)
			{
				throw new \RuntimeException('Attendance could not be credited because the event date is missing.');
			}

			$profileIds[] = (int) $attendance->profile_id;
			$entryPublished = $this->attendanceEntryPublished($eventId);

			foreach ($this->store->eventCredits($eventId) as $credit)
			{
				$this->write([
					'profile_id'  => (int) $attendance->profile_id,
					'metric_id'   => (int) $credit->metric_id,
					'entry_date'  => substr((string) $attendance->event_date, 0, 10),
					'value'       => (string) $credit->value,
					'event_id'    => $eventId,
					'source_type' => MetricSource::Attendance->value,
					'source_id'   => $attendanceId,
					'published'   => $entryPublished,
					'note'        => '',
				]);
			}
		});
		$this->recomputeAwards($profileIds);
	}

	/**
	 * Rewrite every attendance entry for an event from its current credits and date.
	 *
	 * Credits that were removed are trashed. The same call can run again.
	 * Entry published follows the event via attendanceEntryPublished().
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function resyncEvent(int $eventId): void
	{
		$profileIds = [];
		$this->transaction(function () use ($eventId, &$profileIds): void {
			$date = $this->store->eventDate($eventId);

			if ($date === null)
			{
				throw new \RuntimeException('Event credits could not be applied because the event date is missing.');
			}

			$entryPublished = $this->attendanceEntryPublished($eventId);
			$credits = $this->store->eventCredits($eventId);
			$metricIds = [];

			foreach ($this->store->eventAttendances($eventId) as $attendance)
			{
				$profileIds[] = (int) $attendance->profile_id;

				foreach ($credits as $credit)
				{
					$metricId = (int) $credit->metric_id;
					$metricIds[$metricId] = $metricId;
					$this->write([
						'profile_id'  => (int) $attendance->profile_id,
						'metric_id'   => $metricId,
						'entry_date'  => $date,
						'value'       => (string) $credit->value,
						'event_id'    => $eventId,
						'source_type' => MetricSource::Attendance->value,
						'source_id'   => (int) $attendance->id,
						'published'   => $entryPublished,
						'note'        => '',
					]);
				}
			}

			$this->store->trashAttendanceMetricsExcept($eventId, array_values($metricIds));
		});
		$this->recomputeAwards($profileIds);
	}

	/**
	 * Entry published for attendance credits on an event.
	 *
	 * Cancelled events (status 3) yield 0 so totals skip check-in credits,
	 * matching unpublished. Published (1) and Archived (2) yield 1.
	 * Unpublished (0) yields 0. Trashed (-2) yields -2. Used by
	 * creditAttendance and resyncEvent; keep this the only map.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function attendanceEntryPublished(int $eventId): int
	{
		if ($this->store->eventStatus($eventId) === 3)
		{
			return 0;
		}

		$published = $this->store->eventPublished($eventId);

		if ($published === -2)
		{
			return -2;
		}

		if ($published === 0)
		{
			return 0;
		}

		return 1;
	}

	/**
	 * Trash the entries that came from one attendance row on one event.
	 *
	 * Event and attendance ids must match an entry's event_id and source_id.
	 * A mismatched pair changes nothing and returns false. Callers pass
	 * $profileId explicitly because the attendance row is often already
	 * deleted before this runs; looking it up would miss the award recompute.
	 *
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $attendanceId  Attendance id.
	 * @param   integer  $profileId     Member profile id to recompute.
	 *
	 * @return  boolean  True when at least one entry changed.
	 *
	 * @since   1.0.0
	 */
	public function trashAttendance(int $eventId, int $attendanceId, int $profileId): bool
	{
		$changed = false;

		$this->transaction(function () use ($eventId, $attendanceId, &$changed): void {
			$changed = $this->store->trashAttendanceSource($eventId, $attendanceId);
		});

		if ($changed && $profileId > 0)
		{
			$this->recomputeAwards([$profileId]);
		}

		return $changed;
	}

	/**
	 * Upsert one entry per metric for an activity.
	 *
	 * Entry published always mirrors the activity: if any metric needs
	 * approval, every entry is pending (0); otherwise every entry is 1.
	 *
	 * @param   integer              $activityId      Activity id.
	 * @param   integer              $profileId       Member profile id.
	 * @param   string               $entryDate       Local activity date.
	 * @param   array<int, string>   $values          Metric id to stored decimal.
	 * @param   array<int, boolean>  $needsApproval   Metric id to approval flag.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function recordActivity(
		int $activityId,
		int $profileId,
		string $entryDate,
		array $values,
		array $needsApproval
	): void {
		if (!MetricPeriod::isDay($entryDate))
		{
			throw new \InvalidArgumentException('Activity date must be a calendar day.');
		}

		$pending = false;

		foreach ($needsApproval as $needs)
		{
			if ($needs)
			{
				$pending = true;
				break;
			}
		}

		$published = $pending ? 0 : 1;

		$this->transaction(function () use ($activityId, $profileId, $entryDate, $values, $published): void {
			foreach ($values as $metricId => $value)
			{
				$metricId = (int) $metricId;
				$this->write([
					'profile_id'  => $profileId,
					'metric_id'   => $metricId,
					'entry_date'  => $entryDate,
					'value'       => (string) $value,
					'event_id'    => null,
					'source_type' => MetricSource::Activity->value,
					'source_id'   => $activityId,
					'published'   => $published,
					'note'        => '',
				]);
			}
		});
		$this->recomputeAwards([$profileId]);
	}

	/**
	 * Set every metric entry for an activity to the activity's published state.
	 *
	 * pending 0, approved 1, rejected -2. Every transition uses this path so
	 * entry state always mirrors the activity, including reject to unapprove.
	 *
	 * @param   integer  $activityId  Activity id.
	 * @param   integer  $published   0, 1, or -2.
	 *
	 * @return  boolean  True when at least one entry changed.
	 *
	 * @since   1.0.0
	 */
	public function setActivityEntriesPublished(int $activityId, int $published): bool
	{
		$changed = false;
		$profileIds = $this->store->profileIdsForSource(MetricSource::Activity->value, $activityId);
		$this->transaction(function () use ($activityId, $published, &$changed): void {
			$changed = $this->store->setSourcePublished(
				MetricSource::Activity->value,
				$activityId,
				$published
			);
		});

		if ($changed)
		{
			$this->recomputeAwards($profileIds);
		}

		return $changed;
	}

	/**
	 * Count an activity by publishing its entries (0 or -2 becomes 1).
	 *
	 * @param   integer  $activityId  Activity id.
	 *
	 * @return  boolean  True when at least one entry changed.
	 *
	 * @since   1.0.0
	 */
	public function approveActivity(int $activityId): bool
	{
		return $this->setActivityEntriesPublished($activityId, 1);
	}

	/**
	 * Return an activity's entries to pending (1 or -2 becomes 0).
	 *
	 * @param   integer  $activityId  Activity id.
	 *
	 * @return  boolean  True when at least one entry changed.
	 *
	 * @since   1.0.0
	 */
	public function unapproveActivity(int $activityId): bool
	{
		return $this->setActivityEntriesPublished($activityId, 0);
	}

	/**
	 * Reject an activity by setting every entry to -2.
	 *
	 * Totals stay equal to SUM(value) over published = 1.
	 *
	 * @param   integer  $activityId  Activity id.
	 *
	 * @return  boolean  True when at least one entry changed.
	 *
	 * @since   1.0.0
	 */
	public function rejectActivity(int $activityId): bool
	{
		return $this->setActivityEntriesPublished($activityId, -2);
	}

	/**
	 * Trash every attendance entry for an event.
	 *
	 * Used when the event is trashed. Attendance rows are left alone.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function trashEventAttendance(int $eventId): void
	{
		$profileIds = [];

		foreach ($this->store->eventAttendances($eventId) as $attendance)
		{
			$profileIds[] = (int) $attendance->profile_id;
		}

		$this->transaction(function () use ($eventId): void {
			$this->store->trashAttendanceMetricsExcept($eventId, []);
		});
		$this->recomputeAwards($profileIds);
	}

	/**
	 * Hard-delete every metric entry for an event.
	 *
	 * Used when the event row itself is deleted so attendance and volunteer
	 * ledger rows do not outlive the event. Runs in its own transaction; when
	 * the caller already holds a transaction, use deleteEventEntriesJoining().
	 * Affected profiles are queued for award recompute (including under
	 * deferAwardRecompute).
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteEventEntries(int $eventId): void
	{
		$profileIds = [];
		$this->transaction(function () use ($eventId, &$profileIds): void {
			$profileIds = $this->store->profileIdsForEvent($eventId);
			$this->store->deleteByEvent($eventId);
		});
		$this->recomputeAwards($profileIds);
	}

	/**
	 * Hard-delete every metric entry for an event inside the caller's transaction.
	 *
	 * EventDeleteCascade uses this so metric rows, other child rows, and the
	 * event row share one outer transaction with no nested savepoint. Profile
	 * ids are collected before the delete so award recompute (or a deferred
	 * flush) still refreshes those members.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteEventEntriesJoining(int $eventId): void
	{
		$profileIds = $this->store->profileIdsForEvent($eventId);
		$this->store->deleteByEvent($eventId);
		$this->recomputeAwards($profileIds);
	}

	/**
	 * Hard-delete attendance metric entries whose event row is gone.
	 *
	 * Used by the one-time update cleanup for deletes made before #93.
	 *
	 * @return  integer  Number of rows removed.
	 *
	 * @since   1.0.0
	 */
	public function deleteOrphanedAttendanceEntries(): int
	{
		$count = 0;
		$this->transaction(function () use (&$count): void {
			$count = $this->store->deleteOrphanedAttendanceEntries();
		});

		return $count;
	}

	/**
	 * A manual adjustment. source_id is null, so each call adds a row.
	 *
	 * @param   integer  $profileId  Member profile id.
	 * @param   integer  $metricId   Metric id.
	 * @param   string   $entryDate  Local date.
	 * @param   string   $value      Stored decimal.
	 * @param   string   $note       Short note.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function addManual(int $profileId, int $metricId, string $entryDate, string $value, string $note): void
	{
		$this->addUnkeyed(MetricSource::Manual, $profileId, $metricId, $entryDate, $value, $note);
	}

	/**
	 * An imported amount. A null source id allows more than one row.
	 *
	 * @param   integer       $profileId  Member profile id.
	 * @param   integer       $metricId   Metric id.
	 * @param   string        $entryDate  Local date.
	 * @param   string        $value      Stored decimal.
	 * @param   integer|null  $sourceId   Import batch id, or null.
	 * @param   string        $note       Short note.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function addImport(
		int $profileId,
		int $metricId,
		string $entryDate,
		string $value,
		?int $sourceId,
		string $note
	): void {
		if ($sourceId === null)
		{
			$this->addUnkeyed(MetricSource::Import, $profileId, $metricId, $entryDate, $value, $note);

			return;
		}

		$this->writeKeyed(MetricSource::Import, $sourceId, $profileId, $metricId, $entryDate, $value, null, 1, $note);
	}

	/**
	 * Credit a volunteer shift when the caller already has the amount.
	 *
	 * @param   integer  $volunteerId  event_volunteers id.
	 * @param   integer  $profileId    Member profile id.
	 * @param   integer  $metricId     Metric id.
	 * @param   integer  $eventId      Event id.
	 * @param   string   $entryDate    Event local date.
	 * @param   string   $value        Stored decimal.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function creditVolunteer(
		int $volunteerId,
		int $profileId,
		int $metricId,
		int $eventId,
		string $entryDate,
		string $value
	): void {
		$this->writeKeyed(
			MetricSource::Volunteer,
			$volunteerId,
			$profileId,
			$metricId,
			$entryDate,
			$value,
			$eventId,
			1,
			''
		);
	}

	/**
	 * Write one null-source row inside a transaction.
	 *
	 * @param   MetricSource  $source     Manual or import.
	 * @param   integer       $profileId  Profile id.
	 * @param   integer       $metricId   Metric id.
	 * @param   string        $entryDate  Local date.
	 * @param   string        $value      Stored decimal.
	 * @param   string        $note       Short note.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function addUnkeyed(
		MetricSource $source,
		int $profileId,
		int $metricId,
		string $entryDate,
		string $value,
		string $note
	): void {
		if (!MetricPeriod::isDay($entryDate))
		{
			throw new \InvalidArgumentException('Metric entry date must be a calendar day.');
		}

		$this->transaction(function () use ($source, $profileId, $metricId, $entryDate, $value, $note): void {
			$this->write([
				'profile_id'  => $profileId,
				'metric_id'   => $metricId,
				'entry_date'  => $entryDate,
				'value'       => $value,
				'event_id'    => null,
				'source_type' => $source->value,
				'source_id'   => null,
				'published'   => 1,
				'note'        => $note,
			]);
		});
		$this->recomputeAwards([$profileId]);
	}

	/**
	 * Write one keyed row inside a transaction.
	 *
	 * @param   MetricSource  $source     Source kind.
	 * @param   integer       $sourceId   Source primary key.
	 * @param   integer       $profileId  Profile id.
	 * @param   integer       $metricId   Metric id.
	 * @param   string        $entryDate  Local date.
	 * @param   string        $value      Stored decimal.
	 * @param   integer|null  $eventId    Event id, or null.
	 * @param   integer       $published  1 counted, 0 pending, -2 trashed.
	 * @param   string        $note       Short note.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function writeKeyed(
		MetricSource $source,
		int $sourceId,
		int $profileId,
		int $metricId,
		string $entryDate,
		string $value,
		?int $eventId,
		int $published,
		string $note
	): void {
		if (!MetricPeriod::isDay($entryDate))
		{
			throw new \InvalidArgumentException('Metric entry date must be a calendar day.');
		}

		$this->transaction(function () use (
			$source,
			$sourceId,
			$profileId,
			$metricId,
			$entryDate,
			$value,
			$eventId,
			$published,
			$note
		): void {
			$this->write([
				'profile_id'  => $profileId,
				'metric_id'   => $metricId,
				'entry_date'  => $entryDate,
				'value'       => $value,
				'event_id'    => $eventId,
				'source_type' => $source->value,
				'source_id'   => $sourceId,
				'published'   => $published,
				'note'        => $note,
			]);
		});
		$this->recomputeAwards([$profileId]);
	}

	/**
	 * Stamp the actor and hand the row to the store.
	 *
	 * @param   array  $row  Entry columns without actor_id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function write(array $row): void
	{
		$row['actor_id'] = $this->actorId;
		$row['note'] = substr((string) ($row['note'] ?? ''), 0, 255);
		$this->store->upsert($row);
	}

	/**
	 * Run writes in one transaction and surface the error.
	 *
	 * @param   callable  $work  Writes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function transaction(callable $work): void
	{
		$this->store->begin();

		try
		{
			$work();
			$this->store->commit();
		}
		catch (\Throwable $error)
		{
			$this->store->rollback();

			throw $error;
		}
	}

	/**
	 * Ask the award engine to refresh grants for affected profiles.
	 *
	 * Recompute failures are logged and never undo a successful credit write.
	 * When deferAwardRecompute(true) is active, ids are queued for
	 * flushAwardRecompute() after the caller commits. Callers that wrap
	 * metric writes in their own transaction must defer and flush so the
	 * award recompute is not nested inside that outer snapshot.
	 *
	 * @param   integer[]  $profileIds  Profiles whose metrics changed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function recomputeAwards(array $profileIds): void
	{
		if ($this->awards === null || $profileIds === [])
		{
			return;
		}

		$unique = [];

		foreach ($profileIds as $profileId)
		{
			$profileId = (int) $profileId;

			if ($profileId > 0)
			{
				$unique[$profileId] = $profileId;
			}
		}

		if ($unique === [])
		{
			return;
		}

		if ($this->deferAwards)
		{
			foreach ($unique as $profileId)
			{
				$this->deferredProfiles[$profileId] = $profileId;
			}

			return;
		}

		$this->runAwardRecompute(array_values($unique));
	}

	/**
	 * Run recompute and swallow failures after logging.
	 *
	 * @param   integer[]  $profileIds  Profiles to refresh.
	 *
	 * @return  void
	 *
	 * @throws  \LogicException  When the metric store still has an open txn.
	 *
	 * @since   1.0.0
	 */
	private function runAwardRecompute(array $profileIds): void
	{
		if ($this->awards === null || $profileIds === [])
		{
			return;
		}

		$this->assertAwardRecomputeOutsideCallerTransaction();

		try
		{
			$this->awards->recompute(null, $profileIds);
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Award recompute after metric write failed', $error);
		}
	}

	/**
	 * Refuse award recompute while a caller (or nested store) transaction is open.
	 *
	 * InnoDB keeps FOR UPDATE locks from lockAward until that outer commit, so
	 * concurrent attendance flushes can hit 1205. Callers must defer, commit,
	 * then flush.
	 *
	 * @return  void
	 *
	 * @throws  \LogicException
	 *
	 * @since   1.0.0
	 */
	private function assertAwardRecomputeOutsideCallerTransaction(): void
	{
		if ($this->store->openTransactionDepth() > 0)
		{
			throw new \LogicException(
				'Award recompute must not run while a caller transaction is open;'
				. ' defer with deferAwardRecompute and flush after commit.'
			);
		}
	}
}
