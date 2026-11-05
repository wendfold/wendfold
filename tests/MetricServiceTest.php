<?php

/**
 * Wendfold - MetricService write rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Administrator\Service\SqlMetricEntryStore;
use Wendfold\Tests\Support\CountingAwardRecomputer;
use Wendfold\Tests\Support\DatabaseStub;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/SqlMetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRecomputer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';
require_once __DIR__ . '/support/CountingAwardRecomputer.php';

/**
 * Idempotent credits, trashed attendance, approval, and manual rows.
 *
 * @since  1.0.0
 */
final class MetricServiceTest extends TestCase
{
	/**
	 * Completing the same attendance twice leaves one row per metric.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCompletingAnEventTwiceLeavesOneRowPerMetric(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);
		$service->creditAttendance(4, 10);

		$this->assertCount(2, $store->entries);
		$this->assertSame('182.500', $store->entries[0]['value']);
		$this->assertSame('1.000', $store->entries[1]['value']);
		$this->assertSame('2026-06-15', $store->entries[0]['entry_date']);
		$this->assertSame(1, $store->entries[0]['published']);
	}

	/**
	 * Forced credit on a trashed event writes entries as published -2.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreditAttendanceOnTrashedEventWritesExcludedEntries(): void
	{
		$store = $this->eventStore();
		$store->eventStates[4] = -2;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertCount(2, $store->entries);
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
		$this->assertSame(0.0, $this->countedTotal($store->entries));
	}

	/**
	 * Credit on an unpublished event writes pending entries (published = 0).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreditAttendanceOnUnpublishedEventWritesPendingEntries(): void
	{
		$store = $this->eventStore();
		$store->eventStates[4] = 0;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertCount(2, $store->entries);
		$this->assertSame(0, $store->entries[0]['published']);
		$this->assertSame(0, $store->entries[1]['published']);
		$this->assertSame(0.0, $this->countedTotal($store->entries));

		$store->eventStates[4] = 1;
		$service->resyncEvent(4);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(183.5, $this->countedTotal($store->entries));
	}

	/**
	 * Credit on an archived event stays counted (published = 1).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreditAttendanceOnArchivedEventWritesCountedEntries(): void
	{
		$store = $this->eventStore();
		$store->eventStates[4] = 2;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(183.5, $this->countedTotal($store->entries));
	}

	/**
	 * A credit or date change rewrites the existing rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreditChangeReupsertsTheSameRows(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);

		$store->credits[4][0]->value = '190.000';
		$store->eventDates[4] = '2026-06-16';
		$store->attendances[10]->event_date = '2026-06-16';
		$service->resyncEvent(4);

		$this->assertCount(2, $store->entries);
		$this->assertSame('190.000', $store->entries[0]['value']);
		$this->assertSame('2026-06-16', $store->entries[0]['entry_date']);
		$this->assertSame('2026-06-16', $store->entries[1]['entry_date']);
		$this->assertSame(1, $store->entries[0]['published']);
	}

	/**
	 * Removing a credit trashes that metric and keeps the others.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovedCreditIsTrashed(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);
		array_pop($store->credits[4]);
		$service->resyncEvent(4);

		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
	}

	/**
	 * Resync of a still-trashed event keeps attendance entries at -2.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResyncOfTrashedEventKeepsEntriesExcluded(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);
		$service->trashEventAttendance(4);
		$store->eventStates[4] = -2;
		$store->credits[4][0]->value = '200.000';
		$service->resyncEvent(4);

		$this->assertCount(2, $store->entries);
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
		$this->assertSame('200.000', $store->entries[0]['value']);
		$this->assertSame(0.0, $this->countedTotal($store->entries));

		$store->eventStates[4] = 1;
		$service->resyncEvent(4);

		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(1, $store->entries[1]['published']);
		$this->assertSame(201.0, $this->countedTotal($store->entries));
	}

	/**
	 * Deleting attendance trashes its entries for that event only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAttendanceDeleteTrashesEntries(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);
		$this->assertTrue($service->trashAttendance(4, 10, 9));

		$this->assertCount(2, $store->entries);
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
	}

	/**
	 * A mismatched event id leaves attendance entries untouched.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashAttendanceRequiresMatchingEventScope(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);

		$this->assertFalse($service->trashAttendance(99, 10, 9));
		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(1, $store->entries[1]['published']);
		$this->assertSame(183.5, $this->countedTotal($store->entries));

		$this->assertTrue($service->trashAttendance(4, 10, 9));
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
		$this->assertSame(0.0, $this->countedTotal($store->entries));
	}

	/**
	 * trashAttendance queues the passed profile even when the attendance row is gone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashAttendanceQueuesProfileWithoutAttendanceRow(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->creditAttendance(4, 10);
		unset($store->attendances[10]);
		$awards->calls = 0;
		$awards->profileArgs = [];
		$service->deferAwardRecompute(true);

		$this->assertTrue($service->trashAttendance(4, 10, 9));
		$this->assertSame(0, $awards->calls);
		$this->assertSame([9], $service->deferredAwardProfileIds());

		$service->flushAwardRecompute();

		$this->assertSame(1, $awards->calls);
		$this->assertSame([9], $awards->profileArgs[0]);
	}

	/**
	 * Crediting requires the attendance row to belong to the given event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreditAttendanceRejectsMismatchedEvent(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);

		try
		{
			$service->creditAttendance(99, 10);
			$this->fail('Mismatched event credit should fail.');
		}
		catch (\RuntimeException $error)
		{
			$this->assertStringContainsString('event date is missing', $error->getMessage());
		}

		$this->assertSame([], $store->entries);
	}

	/**
	 * Mixed approval metrics store every entry pending until approve.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedApprovalActivityStoresEveryEntryPending(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [1 => false, 2 => true]);

		$this->assertSame([0, 0], array_column($store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($store->entries));

		$service->approveActivity(3);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(43.5, $this->countedTotal($store->entries));
		$this->assertCount(2, $store->entries);

		$service->recordActivity(3, 9, '2026-07-02', [1 => '43.000', 2 => '2.000'], [1 => false, 2 => true]);
		$this->assertCount(2, $store->entries);
		$this->assertSame('2026-07-02', $store->entries[0]['entry_date']);
		$this->assertSame([0, 0], array_column($store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($store->entries));
	}

	/**
	 * All auto-count metrics store every entry published.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllAutoActivityStoresEveryEntryPublished(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [1 => false, 2 => false]);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(43.5, $this->countedTotal($store->entries));
	}

	/**
	 * All approval metrics store every entry pending.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllApprovalActivityStoresEveryEntryPending(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [1 => true, 2 => true]);

		$this->assertSame([0, 0], array_column($store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($store->entries));
	}

	/**
	 * Reject trashes every entry for the activity. Re-approve restores them.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRejectTrashesEntriesAndReapproveRestoresThem(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [2 => true]);

		$this->assertSame([0, 0], array_column($store->entries, 'published'));
		$this->assertTrue($service->rejectActivity(3));
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
		$this->assertFalse($service->rejectActivity(3));

		$this->assertTrue($service->approveActivity(3));
		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(1, $store->entries[1]['published']);
		$this->assertFalse($service->approveActivity(3));
	}

	/**
	 * Unapprove returns counted entries to pending. Reject then trashes them.
	 * Unapprove after reject moves trashed entries back to pending.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnapproveReturnsCountedEntriesToPending(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [2 => true]);
		$service->approveActivity(3);

		$this->assertTrue($service->unapproveActivity(3));
		$this->assertSame(0, $store->entries[0]['published']);
		$this->assertSame(0, $store->entries[1]['published']);
		$this->assertFalse($service->unapproveActivity(3));

		$this->assertTrue($service->rejectActivity(3));
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);

		$this->assertTrue($service->unapproveActivity(3));
		$this->assertSame(0, $store->entries[0]['published']);
		$this->assertSame(0, $store->entries[1]['published']);
	}

	/**
	 * Every activity transition sets entry published to the same value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActivityEntryStateMirrorsEachTransition(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->recordActivity(3, 9, '2026-07-01', [1 => '42.000', 2 => '1.500'], [2 => true]);

		$this->assertSame([0, 0], array_column($store->entries, 'published'));

		$this->assertTrue($service->setActivityEntriesPublished(3, -2));
		$this->assertSame([-2, -2], array_column($store->entries, 'published'));

		$this->assertTrue($service->setActivityEntriesPublished(3, 1));
		$this->assertSame([1, 1], array_column($store->entries, 'published'));

		$this->assertTrue($service->setActivityEntriesPublished(3, 0));
		$this->assertSame([0, 0], array_column($store->entries, 'published'));

		$this->assertTrue($service->setActivityEntriesPublished(3, -2));
		$this->assertSame([-2, -2], array_column($store->entries, 'published'));

		$this->assertTrue($service->setActivityEntriesPublished(3, 0));
		$this->assertSame([0, 0], array_column($store->entries, 'published'));
	}

	/**
	 * Trashing an event trashes every attendance entry for it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashEventAttendanceSetsEntriesToTrashed(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);

		$service->trashEventAttendance(4);

		$this->assertCount(2, $store->entries);
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
	}

	/**
	 * Deleting an event hard-deletes every metric entry for it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteEventEntriesRemovesRowsForThatEventOnly(): void
	{
		$store = $this->eventStore();
		$store->eventDates[5] = '2026-06-16';
		$store->eventStates[5] = 1;
		$store->attendances[11] = (object) [
			'id'         => 11,
			'profile_id' => 8,
			'event_id'   => 5,
			'event_date' => '2026-06-16',
		];
		$store->credits[5] = [
			(object) ['metric_id' => 1, 'value' => '1.000'],
		];
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);

		$service->deleteEventEntries(4);

		$this->assertCount(1, $store->entries);
		$this->assertSame(5, $store->entries[0]['event_id']);
		$this->assertSame(1.0, $this->countedTotal($store->entries));
	}

	/**
	 * Deleting an event's metric entries queues award recompute for those profiles.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteEventEntriesRecomputesAffectedProfiles(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->creditAttendance(4, 10);
		$awards->calls = 0;
		$awards->profileArgs = [];

		$service->deleteEventEntries(4);

		$this->assertSame([], $store->entries);
		$this->assertSame(1, $awards->calls);
		$this->assertSame([9], $awards->profileArgs[0]);
	}

	/**
	 * Deferred delete still flushes one recompute for the deleted event's profiles.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeferredDeleteEventEntriesFlushesRecompute(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->creditAttendance(4, 10);
		$awards->calls = 0;
		$awards->profileArgs = [];
		$service->deferAwardRecompute(true);

		$service->deleteEventEntries(4);
		$this->assertSame(0, $awards->calls);

		$service->flushAwardRecompute();

		$this->assertSame(1, $awards->calls);
		$this->assertSame([9], $awards->profileArgs[0]);
	}

	/**
	 * Orphaned attendance entries for a missing event_id are hard-deleted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteOrphanedAttendanceEntriesRemovesMissingEventsOnly(): void
	{
		$store = $this->eventStore();
		$service = new MetricService($store, 7);
		$service->creditAttendance(4, 10);
		$store->entries[] = [
			'profile_id'  => 8,
			'metric_id'   => 1,
			'entry_date'  => '2026-05-01',
			'value'       => '5.000',
			'event_id'    => 99,
			'source_type' => 1,
			'source_id'   => 50,
			'published'   => -2,
			'note'        => '',
		];

		$this->assertSame(1, $service->deleteOrphanedAttendanceEntries());
		$this->assertCount(2, $store->entries);
		$this->assertSame(4, $store->entries[0]['event_id']);
		$this->assertSame(0, $service->deleteOrphanedAttendanceEntries());
	}

	/**
	 * Manual adjustments with a null source id each insert a row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testManualAdjustmentsAllowMultipleNullSourceRows(): void
	{
		$store = new MemoryMetricEntryStore;
		$service = new MetricService($store, 7);
		$service->addManual(9, 1, '2026-08-01', '5.000', 'first');
		$service->addManual(9, 1, '2026-08-02', '6.000', 'second');

		$this->assertCount(2, $store->entries);
		$this->assertNull($store->entries[0]['source_id']);
		$this->assertNull($store->entries[1]['source_id']);
		$this->assertSame(3, $store->entries[0]['source_type']);
		$this->assertSame('first', $store->entries[0]['note']);
		$this->assertSame('second', $store->entries[1]['note']);
	}

	/**
	 * A failed multi-row write rolls back and the error is not swallowed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFailedWriteRollsBackAndSurfacesTheError(): void
	{
		$store = $this->eventStore();
		$store->failOnUpsert = 2;
		$service = new MetricService($store, 7);

		try
		{
			$service->creditAttendance(4, 10);
			$this->fail('The metric write should have failed.');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('Metric write failed.', $error->getMessage());
		}

		$this->assertSame([], $store->entries);
	}

	/**
	 * A recompute failure is logged and does not undo a successful credit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAwardRecomputeFailureDoesNotFailTheCredit(): void
	{
		\Joomla\CMS\Log\Log::$messages = [];
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$awards->throw = true;
		$service = new MetricService($store, 7, $awards);

		$service->creditAttendance(4, 10);

		$this->assertCount(2, $store->entries);
		$this->assertSame(1, $awards->calls);
		$this->assertSame([9], $awards->profileArgs[0]);
		$this->assertNotSame([], \Joomla\CMS\Log\Log::$messages);
		$this->assertSame('com_wendfold', \Joomla\CMS\Log\Log::$messages[0][2]);
		$this->assertSame(\Joomla\CMS\Log\Log::ERROR, \Joomla\CMS\Log\Log::$messages[0][1]);
		$this->assertStringContainsString(
			'Award recompute after metric write failed',
			\Joomla\CMS\Log\Log::$messages[0][0]
		);
		$this->assertGreaterThanOrEqual(2, \count(\Joomla\CMS\Log\Log::$messages));
		$this->assertSame(\Joomla\CMS\Log\Log::DEBUG, \Joomla\CMS\Log\Log::$messages[1][1]);
	}

	/**
	 * Deferred recomputes flush once for a batch of writes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeferredAwardRecomputeFlushesOnce(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->deferAwardRecompute(true);

		$service->addManual(9, 1, '2026-08-01', '5.000', 'a');
		$service->addManual(11, 1, '2026-08-02', '6.000', 'b');
		$this->assertSame(0, $awards->calls);

		$service->flushAwardRecompute();

		$this->assertSame(1, $awards->calls);
		$profiles = $awards->profileArgs[0];
		sort($profiles);
		$this->assertSame([9, 11], $profiles);
	}

	/**
	 * Check-in style: credit inside an outer txn queues recompute until flush after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeferredCreditDoesNotRecomputeBeforeCallerCommit(): void
	{
		$store = $this->eventStore();
		$order = [];
		$awards = new CountingAwardRecomputer;
		$awards->onRecompute = static function () use (&$order): void {
			$order[] = 'recompute';
		};
		$service = new MetricService($store, 7, $awards);
		$service->deferAwardRecompute(true);

		$order[] = 'outer_begin';
		$service->creditAttendance(4, 10);
		$order[] = 'outer_commit';

		$this->assertSame(['outer_begin', 'outer_commit'], $order);
		$this->assertSame(0, $awards->calls);
		$this->assertSame([9], $service->deferredAwardProfileIds());
		$this->assertCount(2, $store->entries);

		$service->flushAwardRecompute();

		$this->assertSame(['outer_begin', 'outer_commit', 'recompute'], $order);
		$this->assertSame(1, $awards->calls);
		$this->assertSame([9], $awards->profileArgs[0]);
		$this->assertSame([], $service->deferredAwardProfileIds());
	}

	/**
	 * Rollback discards queued recomputes without running them.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDiscardDeferredAwardRecomputeLeavesNoRecompute(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->deferAwardRecompute(true);
		$service->creditAttendance(4, 10);

		$this->assertSame([9], $service->deferredAwardProfileIds());

		$service->discardDeferredAwardRecompute();

		$this->assertSame([], $service->deferredAwardProfileIds());
		$this->assertSame(0, $awards->calls);
		$service->flushAwardRecompute();
		$this->assertSame(0, $awards->calls);
	}

	/**
	 * Immediate recompute refuses to run while a caller transaction is open.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRecomputeGuardRefusesOpenCallerTransaction(): void
	{
		$store = $this->eventStore();
		$store->simulatedOuterTxnDepth = 1;
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('deferAwardRecompute');
		$service->creditAttendance(4, 10);
	}

	/**
	 * Flush refuses when the caller transaction is still open.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFlushGuardRefusesOpenCallerTransaction(): void
	{
		$store = $this->eventStore();
		$awards = new CountingAwardRecomputer;
		$service = new MetricService($store, 7, $awards);
		$service->deferAwardRecompute(true);
		$service->creditAttendance(4, 10);
		$store->simulatedOuterTxnDepth = 1;

		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('deferAwardRecompute');
		$service->flushAwardRecompute();
	}

	/**
	 * The upsert statement uses a select alias and not VALUES() or INSERT IGNORE.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUpsertSqlUsesASelectAlias(): void
	{
		$db = new DatabaseStub([]);
		$sql = SqlMetricEntryStore::upsertSql($db, true, false);

		$this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
		$this->assertStringContainsString('AS src', $sql);
		$this->assertStringContainsString('src.profile_id', $sql);
		$this->assertStringContainsString('NULL AS source_id', $sql);
		$this->assertStringNotContainsString('VALUES(', $sql);
		$this->assertStringNotContainsString('INSERT IGNORE', $sql);
		$this->assertStringNotContainsString('CURDATE()', $sql);
		$this->assertStringNotContainsString('NOW()', $sql);
	}

	/**
	 * Two credits on one attendance.
	 *
	 * @return  MemoryMetricEntryStore
	 *
	 * @since   1.0.0
	 */
	private function eventStore(): MemoryMetricEntryStore
	{
		$store = new MemoryMetricEntryStore;
		$store->eventDates[4] = '2026-06-15';
		$store->eventStates[4] = 1;
		$store->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 9,
			'event_id'   => 4,
			'event_date' => '2026-06-15',
		];
		$store->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '182.500'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];

		return $store;
	}

	/**
	 * SUM(value) over published = 1.
	 *
	 * @param   list<array<string, mixed>>  $entries  Stored rows.
	 *
	 * @return  float
	 *
	 * @since   1.0.0
	 */
	private function countedTotal(array $entries): float
	{
		$total = 0.0;

		foreach ($entries as $entry)
		{
			if ((int) $entry['published'] === 1)
			{
				$total += (float) $entry['value'];
			}
		}

		return $total;
	}
}
