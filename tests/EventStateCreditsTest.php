<?php

/**
 * Wendfold - event state changes keep attendance credits in sync (#96, #93, #94).
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
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Double\EventApplication;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/SqlMetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventDeleteCascade.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventRowLock.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/PublishRowState.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';

/**
 * Publish, unpublish, archive, trash, restore, delete, and batch publish paths.
 *
 * @since  1.0.0
 */
final class EventStateCreditsTest extends TestCase
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
	 * Unpublishing a published event drops counted credits to zero.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishThenUnpublishDropsCountedToZero(): void
	{
		$model = $this->lifecycleModel(4);
		$model->service()->creditAttendance(4, 10);
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));

		$pks = [4];
		$this->assertTrue($model->publish($pks, 0));
		$this->assertSame([0, 0], array_column($model->store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Archiving keeps attendance credits counted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testArchiveKeepsCreditsCounted(): void
	{
		$model = $this->lifecycleModel(4);
		$model->service()->creditAttendance(4, 10);

		$pks = [4];
		$this->assertTrue($model->publish($pks, 2));
		$this->assertSame(2, $model->publishedValues[4]);
		$this->assertSame([1, 1], array_column($model->store->entries, 'published'));
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Trash then restore to Published, Unpublished, and Archived yields 1, 0, 1.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashThenRestoreToPublishedUnpublishedAndArchived(): void
	{
		foreach ([1 => 1, 0 => 0, 2 => 1] as $restoreTo => $entryPublished)
		{
			$model = $this->lifecycleModel(4);
			$model->service()->creditAttendance(4, 10);

			$pks = [4];
			$this->assertTrue($model->publish($pks, -2));
			$this->assertSame([-2, -2], array_column($model->store->entries, 'published'));
			$this->assertSame(0.0, $this->countedTotal($model->store->entries));

			$pks = [4];
			$this->assertTrue($model->publish($pks, $restoreTo));
			$this->assertSame($restoreTo, $model->publishedValues[4]);
			$this->assertSame(
				[$entryPublished, $entryPublished],
				array_column($model->store->entries, 'published')
			);
			$this->assertSame(
				$entryPublished === 1 ? 11.0 : 0.0,
				$this->countedTotal($model->store->entries)
			);
		}
	}

	/**
	 * An officer add on an unpublished event writes pending entries; publish counts them.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOfficerAddOnUnpublishedWritesZeroThenPublishCounts(): void
	{
		$model = $this->lifecycleModel(4);
		unset($model->store->attendances[10]);
		$pks = [4];
		$this->assertTrue($model->publish($pks, 0));
		$this->assertSame([], $model->store->entries);

		$model->store->attendances[11] = (object) [
			'id'         => 11,
			'profile_id' => 8,
			'event_id'   => 4,
			'event_date' => '2026-06-15',
		];
		$model->service()->creditAttendance(4, 11);

		$this->assertSame([0, 0], array_column($model->store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));

		$pks = [4];
		$this->assertTrue($model->publish($pks, 1));
		$this->assertSame([1, 1], array_column($model->store->entries, 'published'));
		$this->assertSame(11.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Trash then restore to Unpublished recounts attendees alike after publish.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashThenUnpublishThenLaterMemberAlignsAfterPublish(): void
	{
		$model = $this->lifecycleModel(4);
		$model->service()->creditAttendance(4, 10);

		$pks = [4];
		$this->assertTrue($model->publish($pks, -2));
		$pks = [4];
		$this->assertTrue($model->publish($pks, 0));
		$this->assertSame([0, 0], array_column($model->store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));

		$model->store->attendances[11] = (object) [
			'id'         => 11,
			'profile_id' => 8,
			'event_id'   => 4,
			'event_date' => '2026-06-15',
		];
		$model->service()->creditAttendance(4, 11);
		$this->assertSame([0, 0, 0, 0], array_column($model->store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));

		$pks = [4];
		$this->assertTrue($model->publish($pks, 1));
		$this->assertSame([1, 1, 1, 1], array_column($model->store->entries, 'published'));
		$this->assertSame(22.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Deleting an event removes attendance rows and metric entries for it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashThenDeleteRemovesAttendanceAndMetricRows(): void
	{
		$model = $this->lifecycleModel(4);
		$model->service()->creditAttendance(4, 10);
		$model->database->seedChild('attendance', 4);
		$model->database->seedChild('rsvps', 4);
		$model->database->seedChild('event_group_members', 4);
		$model->database->seedChild('event_groups', 4);
		$model->database->seedChild('event_volunteers', 4);
		$model->database->seedChild('event_volunteer_roles', 4);
		$model->database->seedChild('event_trip_participants', 4);
		$model->database->seedChild('event_metric_credits', 4);

		$pks = [4];
		$this->assertTrue($model->publish($pks, -2));
		$this->assertCount(2, $model->store->entries);

		$pks = [4];
		$this->assertTrue($model->delete($pks));
		$this->assertSame([4], $model->deletedIds);
		$this->assertSame([], $model->store->entries);
		$this->assertSame(0, $model->database->childCount(4));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Batch publish of two events re-syncs each event's attendance credits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBatchPublishResyncsEachEvent(): void
	{
		$model = $this->lifecycleModel(4);
		$model->store->eventDates[5] = '2026-06-16';
		$model->store->eventStates[5] = 1;
		$model->store->attendances[12] = (object) [
			'id'         => 12,
			'profile_id' => 7,
			'event_id'   => 5,
			'event_date' => '2026-06-16',
		];
		$model->store->credits[5] = [
			(object) ['metric_id' => 1, 'value' => '1.000'],
			(object) ['metric_id' => 2, 'value' => '10.000'],
		];
		$model->database->eventStates[5] = 1;
		$model->database->eventRows[5] = (object) [
			'id'          => 5,
			'published'   => 1,
			'status'      => 1,
			'event_date'  => '2026-06-16',
			'checked_out' => 0,
		];

		$service = $model->service();
		$service->creditAttendance(4, 10);
		$service->creditAttendance(5, 12);

		$pks = [4, 5];
		$this->assertTrue($model->publish($pks, -2));
		$this->assertSame(0.0, $this->countedTotal($model->store->entries));

		$pks = [4, 5];
		$this->assertTrue($model->publish($pks, 1));
		$this->assertSame(1, $model->publishedValues[4]);
		$this->assertSame(1, $model->publishedValues[5]);
		$this->assertSame([1, 1, 1, 1], array_column($model->store->entries, 'published'));
		$this->assertSame(22.0, $this->countedTotal($model->store->entries));
	}

	/**
	 * Already-at-target events are omitted from the success primary-key list.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAlreadyAtTargetDoesNotCountAsChanged(): void
	{
		$model = $this->lifecycleModel(4);
		$model->store->eventDates[5] = '2026-06-16';
		$model->store->eventStates[5] = 0;
		$model->database->eventStates[5] = 0;
		$model->database->eventRows[5] = (object) [
			'id'          => 5,
			'published'   => 0,
			'status'      => 1,
			'event_date'  => '2026-06-16',
			'checked_out' => 0,
		];
		$model->publishedValues = [];

		$pks = [4, 5];
		$this->assertTrue($model->publish($pks, 1));
		$this->assertSame([5], array_values($pks));
		$this->assertArrayNotHasKey(4, $model->publishedValues);
		$this->assertSame(1, $model->publishedValues[5]);
	}

	/**
	 * Mixed changed, unchanged, and checked-out events give exact counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishMixedChangedUnchangedAndCheckedOut(): void
	{
		$model = $this->lifecycleModel(4);
		$model->database->eventRows[4]->published = 0;
		$model->database->eventStates[4] = 0;
		$model->store->eventStates[4] = 0;

		$model->database->eventRows[5] = (object) [
			'id'          => 5,
			'published'   => 1,
			'status'      => 1,
			'event_date'  => '2026-06-16',
			'checked_out' => 0,
		];
		$model->database->eventStates[5] = 1;
		$model->store->eventStates[5] = 1;
		$model->store->eventDates[5] = '2026-06-16';

		$model->database->eventRows[6] = (object) [
			'id'          => 6,
			'published'   => 0,
			'status'      => 1,
			'event_date'  => '2026-06-17',
			'checked_out' => 99,
		];
		$model->database->eventStates[6] = 0;
		$model->store->eventStates[6] = 0;
		$model->store->eventDates[6] = '2026-06-17';
		$model->publishedValues = [];

		$pks = [4, 5, 6];
		$this->assertFalse($model->publish($pks, 1));
		$this->assertSame([4], array_values($pks));
		$this->assertSame(1, $model->publishedValues[4]);
		$this->assertArrayNotHasKey(5, $model->publishedValues);
		$this->assertArrayNotHasKey(6, $model->publishedValues);
	}

	/**
	 * All ACL-style publish prunes (true + empty pks) count as failed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllAclPruneCountsAsFailed(): void
	{
		$model = $this->lifecycleModel(4);
		$model->database->eventRows[4]->published = 0;
		$model->database->eventStates[4] = 0;
		$model->store->eventStates[4] = 0;
		$model->database->eventRows[5] = (object) [
			'id'          => 5,
			'published'   => 0,
			'status'      => 1,
			'event_date'  => '2026-06-16',
			'checked_out' => 0,
		];
		$model->database->eventStates[5] = 0;
		$model->store->eventStates[5] = 0;
		$model->store->eventDates[5] = '2026-06-16';
		$model->prunePublishIds = [4, 5];
		$model->publishedValues = [];

		$pks = [4, 5];
		$this->assertFalse($model->publish($pks, 1));
		$this->assertSame([], array_values($pks));
		$this->assertSame([], $model->publishedValues);
	}

	/**
	 * Mixed changed, ACL-pruned, already-at-target, and checked-out events.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishMixedChangedAclUnchangedAndCheckedOut(): void
	{
		$model = $this->lifecycleModel(4);
		$model->database->eventRows[4]->published = 0;
		$model->database->eventStates[4] = 0;
		$model->store->eventStates[4] = 0;

		$model->database->eventRows[5] = (object) [
			'id'          => 5,
			'published'   => 0,
			'status'      => 1,
			'event_date'  => '2026-06-16',
			'checked_out' => 0,
		];
		$model->database->eventStates[5] = 0;
		$model->store->eventStates[5] = 0;
		$model->store->eventDates[5] = '2026-06-16';

		$model->database->eventRows[6] = (object) [
			'id'          => 6,
			'published'   => 1,
			'status'      => 1,
			'event_date'  => '2026-06-17',
			'checked_out' => 0,
		];
		$model->database->eventStates[6] = 1;
		$model->store->eventStates[6] = 1;
		$model->store->eventDates[6] = '2026-06-17';

		$model->database->eventRows[7] = (object) [
			'id'          => 7,
			'published'   => 0,
			'status'      => 1,
			'event_date'  => '2026-06-18',
			'checked_out' => 99,
		];
		$model->database->eventStates[7] = 0;
		$model->store->eventStates[7] = 0;
		$model->store->eventDates[7] = '2026-06-18';

		$model->prunePublishIds = [5];
		$model->publishedValues = [];

		$pks = [4, 5, 6, 7];
		$this->assertFalse($model->publish($pks, 1));
		$this->assertSame([4], array_values($pks));
		$this->assertSame(1, $model->publishedValues[4]);
		$this->assertArrayNotHasKey(5, $model->publishedValues);
		$this->assertArrayNotHasKey(6, $model->publishedValues);
		$this->assertArrayNotHasKey(7, $model->publishedValues);
	}

	/**
	 * Build a model with one published event and one attendance credit set.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  LifecycleEventModel
	 *
	 * @since   1.0.0
	 */
	private function lifecycleModel(int $eventId): LifecycleEventModel
	{
		Factory::$application = new EventApplication([]);

		$store = new MemoryMetricEntryStore;
		$store->eventDates[$eventId] = '2026-06-15';
		$store->eventStates[$eventId] = 1;
		$store->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 9,
			'event_id'   => $eventId,
			'event_date' => '2026-06-15',
		];
		$store->credits[$eventId] = [
			(object) ['metric_id' => 1, 'value' => '1.000'],
			(object) ['metric_id' => 2, 'value' => '10.000'],
		];

		$model = new LifecycleEventModel;
		$model->store = $store;
		$model->database = new LifecycleEventDatabase($model);
		$model->database->eventStates[$eventId] = 1;
		$model->database->eventRows[$eventId] = (object) [
			'id'          => $eventId,
			'published'   => 1,
			'status'      => 1,
			'event_date'  => '2026-06-15',
			'checked_out' => 0,
		];
		$model->onPublish = static function ($pks, int $value) use ($model): void {
			foreach ((array) $pks as $pk)
			{
				$pk = (int) $pk;
				$model->database->eventStates[$pk] = $value;
				$model->store->eventStates[$pk] = $value;

				if (isset($model->database->eventRows[$pk]))
				{
					$model->database->eventRows[$pk]->published = $value;
				}
			}
		};

		return $model;
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
 * EventModel that drives publish and delete against an in-memory metric store.
 *
 * @since  1.0.0
 */
final class LifecycleEventModel extends FixtureEventModel
{
	/**
	 * In-memory ledger.
	 *
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * Database stand-in that tracks child-row deletes.
	 *
	 * @var    LifecycleEventDatabase
	 * @since  1.0.0
	 */
	public LifecycleEventDatabase $database;

	/**
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService(): MetricService
	{
		return new MetricService($this->store, 1);
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
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	protected function loadEventForDelete(int $eventId): ?object
	{
		return (object) [
			'id'    => $eventId,
			'title' => 'Event ' . $eventId,
		];
	}

	/**
	 * @return  LifecycleEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Nested-transaction stand-in that records child-row deletes by event id.
 *
 * @since  1.0.0
 */
final class LifecycleEventDatabase implements DatabaseInterface
{
	/**
	 * Event published states keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStates = [];

	/**
	 * Locked event rows returned by EventRowLock::lock().
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

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
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   LifecycleEventModel  $model  Model whose parent maps roll back.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private LifecycleEventModel $model)
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

		if (isset($this->eventRows[$this->boundEventId]))
		{
			return $this->eventRows[$this->boundEventId];
		}

		if (!isset($this->eventStates[$this->boundEventId]))
		{
			return null;
		}

		return (object) [
			'id'          => $this->boundEventId,
			'published'   => (int) $this->eventStates[$this->boundEventId],
			'status'      => 1,
			'event_date'  => '2026-06-15',
			'checked_out' => 0,
		];
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
		$clonedRows = [];

		foreach ($this->eventRows as $id => $row)
		{
			$clonedRows[$id] = clone $row;
		}

		$this->snapshots[] = [
			'children'         => $this->children,
			'entries'          => $this->model->store->entries,
			'eventStates'      => $this->eventStates,
			'eventRows'        => $clonedRows,
			'publishedValues'  => $this->model->publishedValues,
			'deletedIds'       => $this->model->deletedIds,
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
		$this->eventRows = $snapshot['eventRows'];
		$this->model->store->eventStates = $snapshot['eventStates'];
		$this->model->publishedValues = $snapshot['publishedValues'];
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
