<?php

/**
 * Wendfold - event delete hard-removes every child row (#93).
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventDeleteCascade;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRecomputer;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Double\EventApplication;
use Wendfold\Tests\Support\CountingAwardRecomputer;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRecomputer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventDeleteCascade.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';
require_once __DIR__ . '/support/CountingAwardRecomputer.php';

/**
 * Single, batch, and rollback delete paths leave no child rows and keep totals.
 *
 * @since  1.0.0
 */
final class EventDeleteCascadeTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function tearDown(): void
	{
		Factory::$application = null;

		parent::tearDown();
	}

	/**
	 * Install SQL has eight event_id child tables plus metric_entries (nine).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testChildTableInventoryHasEightNonMetricTables(): void
	{
		$tables = EventDeleteCascade::childTables();

		$this->assertCount(8, $tables);
		$this->assertSame(
			[
				'attendance',
				'rsvps',
				'event_group_members',
				'event_groups',
				'event_volunteers',
				'event_volunteer_roles',
				'event_trip_participants',
				'event_metric_credits',
			],
			array_keys($tables)
		);
	}

	/**
	 * Deleting one event removes every child row and its metric entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSingleDeleteRemovesEveryChildTableAndMetricEntries(): void
	{
		$model = $this->cascadeModel([4]);
		$this->seedFullEvent($model, 4, 10, 9);
		$model->service()->creditAttendance(4, 10);
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));
		$this->assertSame(8, $model->database->childCount(4));
		$this->assertCount(2, $model->store->entries);

		$this->markTrashed($model, 4);
		$pks = [4];
		$this->assertTrue($model->delete($pks));
		$this->assertSame([4], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame([], $model->store->entries);
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));

		foreach (array_keys(EventDeleteCascade::childTables()) as $table)
		{
			$this->assertSame(0, $model->database->childCountFor($table, 4), $table);
		}
	}

	/**
	 * Batch delete clears child rows for every selected event and keeps others.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteRemovesChildrenForEachEventAndPreservesOthers(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->assertSame(33.0, $this->countedTotal($model->store->entries));

		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$pks = [4, 5];
		$this->assertTrue($model->delete($pks));
		$this->assertSame([4, 5], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(0, $model->database->childCount(5));
		$this->assertSame(8, $model->database->childCount(6));
		$this->assertCount(2, $model->store->entries);
		$this->assertSame(6, $model->store->entries[0]['event_id']);
		$this->assertSame(6, $model->store->entries[1]['event_id']);
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * A failure mid-cascade rolls back child deletes, metric deletes, and the event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollbackLeavesEveryChildRowAndMetricEntryIntact(): void
	{
		$model = $this->cascadeModel([4]);
		$model->failPurgeAfterTables = 3;
		$this->seedFullEvent($model, 4, 10, 9);
		$model->service()->creditAttendance(4, 10);
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));
		$this->markTrashed($model, 4);
		$entriesBefore = $model->store->entries;
		$childrenBefore = $model->database->children;

		$pks = [4];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([], $model->deletedIds);
		$this->assertSame($childrenBefore, $model->database->children);
		$this->assertSame($entriesBefore, $model->store->entries);
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));
		$this->assertCount(2, $model->store->entries);
		$this->assertSame(8, $model->database->childCount(4));
		$this->assertSame(
			'Could not delete event "Saturday Ride": The event could not be deleted.',
			$model->getError()
		);
	}

	/**
	 * A mid-batch failure keeps earlier deletes, continues later pks, and names the failed title.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteFailureKeepsEarlierDeletesAndReportsPartial(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Beta Meeting',
			6 => 'Gamma Trip',
		];
		$model->failPurgeOnEventId = 5;
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 6], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(8, $model->database->childCount(5));
		$this->assertSame(0, $model->database->childCount(6));
		$this->assertCount(2, $model->store->entries);
		$this->assertSame(5, $model->store->entries[0]['event_id']);
		$this->assertSame(5, $model->store->entries[1]['event_id']);
		$this->assertSame(
			"Could not delete event \"Beta Meeting\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * A failure on the first pk still deletes later events in the batch.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteFailureInFirstPositionContinuesLaterPks(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Beta Meeting',
			6 => 'Gamma Trip',
		];
		$model->failPurgeOnEventId = 4;
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([5, 6], $model->deletedIds);
		$this->assertSame(8, $model->database->childCount(4));
		$this->assertSame(0, $model->database->childCount(5));
		$this->assertSame(0, $model->database->childCount(6));
		$this->assertSame(
			"Could not delete event \"Alpha Ride\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * A plugin veto in the middle of a batch rolls that event back and continues.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeletePluginVetoInMiddleContinuesLaterPks(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'B109 VETO me',
			6 => 'Gamma Trip',
		];
		$model->vetoDeleteEventIds = [5];
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 6], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(8, $model->database->childCount(5));
		$this->assertSame(0, $model->database->childCount(6));
		$this->assertCount(2, $model->store->entries);
		$this->assertSame(
			"Could not delete event \"B109 VETO me\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * A mid-batch veto still flushes award recompute for deleted events' members only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteWithVetoRecomputesOnlyDeletedEventsMembers(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$awards = new CountingAwardRecomputer;
		$model->awards = $awards;
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Vetoed Meeting',
			6 => 'Gamma Trip',
		];
		$model->vetoDeleteEventIds = [5];
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$awards->calls = 0;
		$awards->profileArgs = [];
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 6], $model->deletedIds);
		$this->assertSame(1, $awards->calls);
		$profiles = $awards->profileArgs[0] ?? [];
		sort($profiles);
		$this->assertSame([7, 9], $profiles);
		$this->assertNotContains(8, $profiles);
	}

	/**
	 * Multiple failures in one batch each get a line; successes still count.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteMultipleFailuresReportsEachAndContinues(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Beta Meeting',
			6 => 'Gamma Trip',
		];
		$model->missingEventIds = [4];
		$model->vetoDeleteEventIds = [6];
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([5], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(5));
		$this->assertSame(8, $model->database->childCount(6));
		$this->assertSame(
			"Could not delete event \"#4\": The event could not be deleted.\n"
			. "Could not delete event \"Gamma Trip\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * ACL-denied pks add one permission line while other pks still run.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteMixedAclAndVetoContinuesPermittedPks(): void
	{
		$model = $this->cascadeModel([4, 5, 6, 7]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Denied Meeting',
			6 => 'Veto Trip',
			7 => 'Delta Ride',
		];
		$model->denyDeleteEventIds = [5];
		$model->vetoDeleteEventIds = [6];
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$this->seedFullEvent($model, 7, 13, 6);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$service->creditAttendance(7, 13);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);
		$this->markTrashed($model, 7);

		$pks = [4, 5, 6, 7];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 7], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(8, $model->database->childCount(5));
		$this->assertSame(8, $model->database->childCount(6));
		$this->assertSame(0, $model->database->childCount(7));
		$this->assertSame(
			"Could not delete event \"Veto Trip\": The event could not be deleted.\n"
			. 'Delete not permitted.',
			$model->getError()
		);
	}

	/**
	 * An ACL denial shows only the permission message, not a per-event wrapper.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeletePermissionDenialShowsOnlyPermissionMessage(): void
	{
		$model = $this->cascadeModel([4]);
		$model->denyDelete = true;
		$this->seedFullEvent($model, 4, 10, 9);
		$model->service()->creditAttendance(4, 10);
		$this->markTrashed($model, 4);
		$entriesBefore = $model->store->entries;
		$childrenBefore = $model->database->children;

		$pks = [4];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([], $model->deletedIds);
		$this->assertSame($childrenBefore, $model->database->children);
		$this->assertSame($entriesBefore, $model->store->entries);
		$this->assertSame('Delete not permitted.', $model->getError());
		$this->assertStringNotContainsString('Could not delete event', (string) $model->getError());
		$this->assertStringNotContainsString('Operation failed', (string) $model->getError());
	}

	/**
	 * A missing event row uses the delete-failed message, not the permission message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteMissingRowUsesFailDeleteNotPermissionMessage(): void
	{
		$model = $this->cascadeModel([4]);
		$model->missingEventIds = [4];
		$model->titles[4] = 'Ghost Ride';

		$pks = [4];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([], $model->deletedIds);
		$this->assertSame(
			'Could not delete event "#4": The event could not be deleted.',
			$model->getError()
		);
	}

	/**
	 * A stale id at the front of a batch does not stop later deletes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteStaleIdFirstContinuesLaterPks(): void
	{
		$model = $this->cascadeModel([5, 6]);
		$model->titles = [
			4 => 'Ghost Ride',
			5 => 'Beta Meeting',
			6 => 'Gamma Trip',
		];
		$model->missingEventIds = [4];
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([5, 6], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(5));
		$this->assertSame(0, $model->database->childCount(6));
		$this->assertSame(
			"Could not delete event \"#4\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * A before-delete plugin veto uses the generic reason, not the permission message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeletePluginVetoUsesFailDeleteNotPermissionMessage(): void
	{
		$model = $this->cascadeModel([4]);
		$model->vetoDelete = true;
		$this->seedFullEvent($model, 4, 10, 9);
		$model->service()->creditAttendance(4, 10);
		$this->markTrashed($model, 4);
		$entriesBefore = $model->store->entries;
		$childrenBefore = $model->database->children;

		$pks = [4];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([], $model->deletedIds);
		$this->assertSame($childrenBefore, $model->database->children);
		$this->assertSame($entriesBefore, $model->store->entries);
		$this->assertSame(
			'Could not delete event "Saturday Ride": The event could not be deleted.',
			$model->getError()
		);
	}

	/**
	 * A mixed batch keeps earlier deletes, continues after a missing later row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedBatchDeleteKeepsSuccessesAndReportsMissingRow(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Missing Meeting',
			6 => 'Gamma Trip',
		];
		$model->missingEventIds = [5];
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 6, 12, 7);
		$model->service()->creditAttendance(4, 10);
		$model->service()->creditAttendance(6, 12);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 6], $model->deletedIds);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(0, $model->database->childCount(6));
		$this->assertSame(
			"Could not delete event \"#5\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * Partial batch with two successes and a last-position failure uses the plural prefix.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchDeleteFailureAfterTwoUsesPluralDeletedPrefix(): void
	{
		$model = $this->cascadeModel([4, 5, 6]);
		$model->titles = [
			4 => 'Alpha Ride',
			5 => 'Beta Meeting',
			6 => 'Gamma Trip',
		];
		$model->failPurgeOnEventId = 6;
		$this->seedFullEvent($model, 4, 10, 9);
		$this->seedFullEvent($model, 5, 11, 8);
		$this->seedFullEvent($model, 6, 12, 7);
		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 11);
		$service->creditAttendance(6, 12);
		$this->markTrashed($model, 4);
		$this->markTrashed($model, 5);
		$this->markTrashed($model, 6);

		$pks = [4, 5, 6];
		$this->assertFalse($model->delete($pks));
		$this->assertSame([4, 5], $model->deletedIds);
		$this->assertSame(8, $model->database->childCount(6));
		$this->assertSame(
			"Could not delete event \"Gamma Trip\": The event could not be deleted.",
			$model->getError()
		);
	}

	/**
	 * Build a model that drives delete against an in-memory ledger and child map.
	 *
	 * @param   list<int>  $eventIds  Event ids that exist.
	 *
	 * @return  CascadeEventModel
	 *
	 * @since   1.0.0
	 */
	private function cascadeModel(array $eventIds): CascadeEventModel
	{
		Factory::$application = new EventApplication([]);

		$store = new MemoryMetricEntryStore;
		$model = new CascadeEventModel;
		$model->store = $store;
		$model->database = new CascadeEventDatabase($model);

		foreach ($eventIds as $eventId)
		{
			$store->eventDates[$eventId] = '2026-06-15';
			$store->eventStates[$eventId] = 1;
			$model->database->eventStates[$eventId] = 1;
			$model->titles[$eventId] = $model->titles[$eventId]
				?? ($eventId === 4 ? 'Saturday Ride' : ('Event ' . $eventId));
		}

		return $model;
	}

	/**
	 * Mark an event trashed the way Empty Trash expects before delete.
	 *
	 * @param   CascadeEventModel  $model    Model under test.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function markTrashed(CascadeEventModel $model, int $eventId): void
	{
		$model->store->eventStates[$eventId] = -2;
		$model->database->eventStates[$eventId] = -2;

		foreach ($model->store->entries as $index => $entry)
		{
			if ((int) $entry['event_id'] === $eventId && (int) $entry['source_type'] === 1)
			{
				$model->store->entries[$index]['published'] = -2;
			}
		}
	}

	/**
	 * Seed one attendance credit set and one row in every non-metric child table.
	 *
	 * @param   CascadeEventModel  $model        Model under test.
	 * @param   integer            $eventId      Event id.
	 * @param   integer            $attendanceId Attendance id.
	 * @param   integer            $profileId    Profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedFullEvent(
		CascadeEventModel $model,
		int $eventId,
		int $attendanceId,
		int $profileId
	): void {
		$model->store->attendances[$attendanceId] = (object) [
			'id'         => $attendanceId,
			'profile_id' => $profileId,
			'event_id'   => $eventId,
			'event_date' => '2026-06-15',
		];
		$model->store->credits[$eventId] = [
			(object) ['metric_id' => 1, 'value' => '1.000'],
			(object) ['metric_id' => 2, 'value' => '10.000'],
		];

		foreach (array_keys(EventDeleteCascade::childTables()) as $table)
		{
			$model->database->seedChild($table, $eventId);
		}
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

/**
 * EventModel that can fail mid-cascade for the rollback test.
 *
 * @since  1.0.0
 */
final class CascadeEventModel extends FixtureEventModel
{
	/**
	 * In-memory ledger.
	 *
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * Optional awards hook for delete-path recompute assertions.
	 *
	 * @var    AwardRecomputer|null
	 * @since  1.0.0
	 */
	public ?AwardRecomputer $awards = null;

	/**
	 * Cached MetricService so defer/flush spans credit and delete.
	 *
	 * @var    MetricService|null
	 * @since  1.0.0
	 */
	private ?MetricService $metricServiceInstance = null;

	/**
	 * Database stand-in that tracks child-row deletes.
	 *
	 * @var    CascadeEventDatabase
	 * @since  1.0.0
	 */
	public CascadeEventDatabase $database;

	/**
	 * Throw after this many child-table deletes. 0 disables the failure.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $failPurgeAfterTables = 0;

	/**
	 * Throw when purge starts for this event id. 0 disables the failure.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $failPurgeOnEventId = 0;

	/**
	 * Titles keyed by event id for delete error messages.
	 *
	 * @var    array<int, string>
	 * @since  1.0.0
	 */
	public array $titles = [];

	/**
	 * Event ids treated as missing rows for loadEventForDelete.
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $missingEventIds = [];

	/**
	 * Child-table deletes seen during the current purge.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $purgeTableDeletes = 0;

	/**
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService(): MetricService
	{
		if ($this->metricServiceInstance === null)
		{
			$this->metricServiceInstance = new MetricService($this->store, 1, $this->awards);
		}

		return $this->metricServiceInstance;
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	protected function loadEventForDelete(int $eventId): ?object
	{
		if (\in_array($eventId, $this->missingEventIds, true))
		{
			return null;
		}

		return (object) [
			'id'    => $eventId,
			'title' => $this->titles[$eventId] ?? ('#' . $eventId),
		];
	}

	/**
	 * Exposed service for check-in style credits in the test.
	 *
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	public function service(): MetricService
	{
		return $this->metricService();
	}

	/**
	 * @return  CascadeEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   MetricService      $service  Metric writer.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function purgeEventChildren(DatabaseInterface $db, MetricService $service, int $eventId): void
	{
		if ($this->failPurgeOnEventId > 0 && $eventId === $this->failPurgeOnEventId)
		{
			throw new \RuntimeException('Simulated cascade failure.');
		}

		$this->purgeTableDeletes = 0;
		$service->deleteEventEntriesJoining($eventId);

		foreach (EventDeleteCascade::childTables() as $table)
		{
			$query = $db->createQuery()
				->delete($db->quoteName($table))
				->where($db->quoteName('event_id') . ' = :eventId')
				->bind(':eventId', $eventId);
			$db->setQuery($query)->execute();
			$this->purgeTableDeletes++;

			if ($this->failPurgeAfterTables > 0
				&& $this->purgeTableDeletes >= $this->failPurgeAfterTables)
			{
				throw new \RuntimeException('Simulated cascade failure.');
			}
		}
	}
}

/**
 * Nested-transaction stand-in that records child-row deletes by event id.
 *
 * @since  1.0.0
 */
final class CascadeEventDatabase implements DatabaseInterface
{
	/**
	 * Event published states keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStates = [];

	/**
	 * Child rows keyed by short table name, then event id.
	 *
	 * @var    array<string, array<int, int>>
	 * @since  1.0.0
	 */
	public array $children = [];

	/**
	 * Table targeted by the current delete builder.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $deleteTable = '';

	/**
	 * Bound event id for the current delete.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundEventId = null;

	/**
	 * Snapshots for nested transactions.
	 *
	 * @var    list<array{
	 *   children: array<string, array<int, int>>,
	 *   entries: list<array<string, mixed>>,
	 *   eventStates: array<int, int>,
	 *   deletedIds: list<int>
	 * }>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   CascadeEventModel  $model  Model whose parent maps roll back.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private CascadeEventModel $model)
	{
	}

	/**
	 * Seed one child row for an event under a short table name.
	 *
	 * @param   string   $table    Short table name without the prefix.
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function seedChild(string $table, int $eventId): void
	{
		$this->children[$table][$eventId] = ($this->children[$table][$eventId] ?? 0) + 1;
	}

	/**
	 * Count remaining child rows for an event across every tracked table.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function childCount(int $eventId): int
	{
		$total = 0;

		foreach ($this->children as $byEvent)
		{
			$total += (int) ($byEvent[$eventId] ?? 0);
		}

		return $total;
	}

	/**
	 * Count remaining rows for one short table name and event.
	 *
	 * @param   string   $table    Short table name.
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function childCountFor(string $table, int $eventId): int
	{
		return (int) ($this->children[$table][$eventId] ?? 0);
	}

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		$this->deleteTable = '';
		$this->boundEventId = null;

		return $this;
	}

	/**
	 * Create a new query object.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function createQuery()
	{
		return $this->getQuery(true);
	}

	/**
	 * @param   mixed  $table  Table name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function delete($table = null)
	{
		$this->deleteTable = (string) $table;

		return $this;
	}

	/**
	 * @param   mixed  $condition  Unused SQL fragment.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($condition)
	{
		return $this;
	}

	/**
	 * @param   mixed  $key            Placeholder.
	 * @param   mixed  $value          Bound value.
	 * @param   mixed  $dataType       Unused.
	 * @param   mixed  $length         Unused.
	 * @param   mixed  $driverOptions  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function bind($key, &$value = null, $dataType = 'string', $length = 0, $driverOptions = [])
	{
		if ((string) $key === ':eventId' || (string) $key === ':id')
		{
			$this->boundEventId = (int) $value;
		}

		return $this;
	}

	/**
	 * @param   mixed    $query   Query object or SQL.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		return $this;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function __toString(): string
	{
		return 'SELECT id FROM #__wendfold_events WHERE id = :id';
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		if ($this->boundEventId === null)
		{
			return null;
		}

		return (object) [
			'id'         => $this->boundEventId,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-06-15',
		];
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
		if ($this->deleteTable !== '' && $this->boundEventId !== null)
		{
			$short = str_replace('#__wendfold_', '', $this->deleteTable);
			unset($this->children[$short][$this->boundEventId]);
		}

		return true;
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getAffectedRows()
	{
		return 0;
	}

	/**
	 * @param   boolean  $asSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionStart($asSavepoint = false): void
	{
		$this->snapshots[] = [
			'children'    => $this->children,
			'entries'     => $this->model->store->entries,
			'eventStates' => $this->eventStates,
			'deletedIds'  => $this->model->deletedIds,
		];
	}

	/**
	 * @param   boolean  $toSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionCommit($toSavepoint = false): void
	{
		array_pop($this->snapshots);
	}

	/**
	 * @param   boolean  $toSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionRollback($toSavepoint = false): void
	{
		$snapshot = array_pop($this->snapshots);

		if ($snapshot === null)
		{
			return;
		}

		$this->children = $snapshot['children'];
		$this->model->store->entries = $snapshot['entries'];
		$this->eventStates = $snapshot['eventStates'];
		$this->model->store->eventStates = $snapshot['eventStates'];
		$this->model->deletedIds = $snapshot['deletedIds'];
	}

	/**
	 * @param   mixed  $name  Name.
	 * @param   mixed  $as    Alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		if (\is_array($name))
		{
			return array_map(static fn($part): string => (string) $part, $name);
		}

		return (string) $name;
	}

	/**
	 * @param   mixed  $key  Unused.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null)
	{
		return [];
	}

	/**
	 * Query builder methods the delete path does not need beyond the above.
	 *
	 * @param   string  $name       Method name.
	 * @param   array   $arguments  Arguments.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function __call(string $name, array $arguments)
	{
		return $this;
	}
}
