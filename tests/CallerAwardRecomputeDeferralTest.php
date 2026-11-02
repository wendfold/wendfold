<?php

/**
 * Wendfold - Event and activity callers defer award recompute past outer commit.
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
use Wendfold\Component\Wendfold\Administrator\Model\ActivitiesModel;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricSource;
use Wendfold\Component\Wendfold\Site\Model\ActivitylogModel;
use Wendfold\Tests\Double\EventApplication;
use Wendfold\Tests\Support\CountingAwardRecomputer;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/ActivitiesModelDouble.php';
require_once __DIR__ . '/support/BaseDatabaseModelStub.php';
require_once __DIR__ . '/support/MetricServiceRequires.php';
require_once __DIR__ . '/support/CountingAwardRecomputer.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventDeleteCascade.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventRowLock.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/ActivitiesModel.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Model/ActivitylogModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';

/**
 * Callers that open a DB txn around metric writes flush awards only after commit.
 *
 * @since  1.0.0
 */
final class CallerAwardRecomputeDeferralTest extends TestCase
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
	 * EventModel::save recomputes after commit, never while the txn is open.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventSaveRecomputesAfterCommit(): void
	{
		$model = $this->deferralEventModel(15);
		$result = $model->save($this->validEventJform());

		$this->assertTrue($result);
		$this->assertSame(['commit', 'recompute'], $model->order);
		$this->assertSame(1, $model->awards->calls);
		$this->assertSame([9], $model->awards->profileArgs[0]);
	}

	/**
	 * EventModel::save discards the queue when the txn rolls back.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventSaveRollbackDiscardsRecompute(): void
	{
		$model = $this->deferralEventModel(15);
		unset($model->store->eventDates[15]);
		$result = $model->save($this->validEventJform());

		$this->assertFalse($result);
		$this->assertSame(0, $model->awards->calls);
		$this->assertNotContains('recompute', $model->order);
	}

	/**
	 * EventModel::publish recomputes after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventPublishRecomputesAfterCommit(): void
	{
		$model = $this->deferralEventModel(4);
		$model->service()->creditAttendance(4, 10);
		$model->awards->calls = 0;
		$model->awards->profileArgs = [];
		$model->order = [];

		$pks = [4];
		$this->assertTrue($model->publish($pks, 0));
		$this->assertSame(['commit', 'recompute'], $model->order);
		$this->assertSame(1, $model->awards->calls);
	}

	/**
	 * EventModel::publish rollback restores the deferred queue and skips recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventPublishRollbackDiscardsRecompute(): void
	{
		$model = $this->deferralEventModel(4);
		$model->service()->creditAttendance(4, 10);
		$model->awards->calls = 0;
		$model->awards->profileArgs = [];
		$model->order = [];
		$model->failPublish = true;

		$pks = [4];
		$this->assertFalse($model->publish($pks, -2));
		$this->assertSame(0, $model->awards->calls);
		$this->assertNotContains('recompute', $model->order);
	}

	/**
	 * EventModel::delete recomputes after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventDeleteRecomputesAfterCommit(): void
	{
		$model = $this->deferralEventModel(4);
		$model->service()->creditAttendance(4, 10);
		$model->awards->calls = 0;
		$model->awards->profileArgs = [];
		$model->order = [];
		$model->database->eventStates[4] = -2;
		$model->store->eventStates[4] = -2;

		$pks = [4];
		$this->assertTrue($model->delete($pks));
		$this->assertSame(['commit', 'recompute'], $model->order);
		$this->assertSame(1, $model->awards->calls);
		$this->assertSame([9], $model->awards->profileArgs[0]);
	}

	/**
	 * EventModel::delete rollback discards that event's queued profiles.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventDeleteRollbackDiscardsRecompute(): void
	{
		$model = $this->deferralEventModel(4);
		$model->service()->creditAttendance(4, 10);
		$model->awards->calls = 0;
		$model->awards->profileArgs = [];
		$model->order = [];
		$model->database->eventStates[4] = -2;
		$model->store->eventStates[4] = -2;
		$model->vetoDeleteEventIds = [4];

		$pks = [4];
		$this->assertFalse($model->delete($pks));
		$this->assertSame(0, $model->awards->calls);
		$this->assertNotContains('recompute', $model->order);
	}

	/**
	 * ActivitylogModel::storeActivity recomputes after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActivityLogSaveRecomputesAfterCommit(): void
	{
		$model = $this->activityLogModel();
		$result = $model->storeActivity(
			9,
			[
				'activity_date' => '2026-07-01',
				'title'         => 'Day ride',
				'metrics'       => [1 => '10'],
			]
		);

		$this->assertTrue($result);
		$this->assertSame(['commit', 'recompute'], $model->order);
		$this->assertSame(1, $model->awards->calls);
		$this->assertSame([9], $model->awards->profileArgs[0]);
	}

	/**
	 * ActivitylogModel::storeActivity discards recompute on rollback.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActivityLogSaveRollbackDiscardsRecompute(): void
	{
		$model = $this->activityLogModel();
		$model->failInsert = true;
		$result = $model->storeActivity(
			9,
			[
				'activity_date' => '2026-07-01',
				'title'         => 'Day ride',
				'metrics'       => [1 => '10'],
			]
		);

		$this->assertFalse($result);
		$this->assertSame(0, $model->awards->calls);
		$this->assertNotContains('recompute', $model->order);
	}

	/**
	 * ActivitiesModel approve path recomputes after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActivitiesDecideRecomputesAfterCommit(): void
	{
		$model = $this->activitiesModel(3, 9);
		$changed = $model->approve([3]);

		$this->assertSame(1, $changed);
		$this->assertSame(['commit', 'recompute'], $model->order);
		$this->assertSame(1, $model->awards->calls);
		$this->assertSame([9], $model->awards->profileArgs[0]);
	}

	/**
	 * ActivitiesModel decide discards recompute when the txn rolls back.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActivitiesDecideRollbackDiscardsRecompute(): void
	{
		$model = $this->activitiesModel(3, 9);
		$model->failDecide = true;

		try
		{
			$model->approve([3]);
			$this->fail('Expected decide failure.');
		}
		catch (\RuntimeException $error)
		{
			$this->assertSame('Simulated decide failure.', $error->getMessage());
		}

		$this->assertSame(0, $model->awards->calls);
		$this->assertNotContains('recompute', $model->order);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  DeferralEventModel
	 *
	 * @since   1.0.0
	 */
	private function deferralEventModel(int $eventId): DeferralEventModel
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
			(object) ['metric_id' => 1, 'value' => '10.000'],
		];

		$awards = new CountingAwardRecomputer;
		$model = new DeferralEventModel;
		$model->store = $store;
		$model->awards = $awards;
		$model->database = new DeferralEventDatabase($model);
		$model->database->eventStates[$eventId] = 1;
		$model->database->eventRows[$eventId] = (object) [
			'id'         => $eventId,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-06-15',
		];
		$model->onPublish = static function ($pks, int $value) use ($model): void {
			foreach ((array) $pks as $pk)
			{
				$pk = (int) $pk;
				$model->database->eventStates[$pk] = $value;
				$model->store->eventStates[$pk] = $value;
			}
		};
		$awards->onRecompute = static function () use ($model): void {
			$model->order[] = 'recompute';
		};

		return $model;
	}

	/**
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	private function validEventJform(): array
	{
		$jform = [
			'title'           => 'Deferred save',
			'event_type_id'   => 1,
			'event_date'      => '2026-06-15',
			'status'          => 1,
			'published'       => 1,
			'audience'        => 1,
			'allow_rsvp'      => 1,
			'workflow_mode'   => 2,
			'metric_credits'  => ['1' => '10'],
			'volunteer_roles' => [
				['title' => 'Sweep'],
			],
		];
		Factory::$application = new EventApplication($jform);

		return $jform;
	}

	/**
	 * @return  DeferralActivitylogModel
	 *
	 * @since   1.0.0
	 */
	private function activityLogModel(): DeferralActivitylogModel
	{
		Factory::$application = new EventApplication([]);
		$store = new MemoryMetricEntryStore;
		$awards = new CountingAwardRecomputer;
		$model = new DeferralActivitylogModel;
		$model->store = $store;
		$model->awards = $awards;
		$model->database = new DeferralActivityDatabase($model);
		$awards->onRecompute = static function () use ($model): void {
			$model->order[] = 'recompute';
		};

		return $model;
	}

	/**
	 * @param   integer  $activityId  Activity id.
	 * @param   integer  $profileId   Profile id.
	 *
	 * @return  DeferralActivitiesModel
	 *
	 * @since   1.0.0
	 */
	private function activitiesModel(int $activityId, int $profileId): DeferralActivitiesModel
	{
		Factory::$application = new EventApplication([]);
		$store = new MemoryMetricEntryStore;
		$store->entries[] = [
			'profile_id'  => $profileId,
			'metric_id'   => 1,
			'entry_date'  => '2026-07-01',
			'value'       => '10.000',
			'event_id'    => null,
			'source_type' => MetricSource::Activity->value,
			'source_id'   => $activityId,
			'published'   => 0,
			'note'        => '',
		];
		$awards = new CountingAwardRecomputer;
		$model = new DeferralActivitiesModel;
		$model->store = $store;
		$model->awards = $awards;
		$model->activityIds = [$activityId];
		$model->database = new DeferralActivityDatabase($model);
		$awards->onRecompute = static function () use ($model): void {
			$model->order[] = 'recompute';
		};

		return $model;
	}
}

/**
 * EventModel that records commit/recompute order around deferred awards.
 *
 * @since  1.0.0
 */
final class DeferralEventModel extends FixtureEventModel
{
	/**
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * @var    CountingAwardRecomputer
	 * @since  1.0.0
	 */
	public CountingAwardRecomputer $awards;

	/**
	 * @var    DeferralEventDatabase
	 * @since  1.0.0
	 */
	public DeferralEventDatabase $database;

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $order = [];

	/**
	 * @var    MetricService|null
	 * @since  1.0.0
	 */
	private ?MetricService $metricServiceInstance = null;

	/**
	 * @param   array<string, mixed>  $posted  Posted credits.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	protected function parsePostedMetricCredits(array $posted): array
	{
		return [1 => '10.000'];
	}

	/**
	 * @param   array  $data  Event fields.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveInlineLocations(array $data): void
	{
	}

	/**
	 * @param   integer  $eventId   Event id.
	 * @param   array    $roleRows  Role rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveVolunteerRoles(int $eventId, array $roleRows): void
	{
	}

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
		$service->deleteEventEntriesJoining($eventId);
	}

	/**
	 * @return  DeferralEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Nested-transaction stand-in that mirrors outer depth onto the metric store.
 *
 * @since  1.0.0
 */
final class DeferralEventDatabase implements DatabaseInterface
{
	/**
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStates = [];

	/**
	 * Event rows returned by EventRowLock::lock().
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

	/**
	 * Snapshots taken by transactionStart for rollback restore.
	 *
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * Bound event id for the current query.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundId = null;

	/**
	 * @param   DeferralEventModel  $model  Model under test.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private DeferralEventModel $model)
	{
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
		$this->boundId = null;

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
	 * @param   mixed  $columns  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		return $this;
	}

	/**
	 * @param   mixed  $table  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table)
	{
		return $this;
	}

	/**
	 * @param   mixed  $condition  Unused.
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
	 * @param   mixed  $value          Value.
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
		if ((string) $key === ':id' || (string) $key === ':eventId')
		{
			$this->boundId = (int) $value;
		}

		return $this;
	}

	/**
	 * @param   mixed  $table  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function delete($table = null)
	{
		return $this;
	}

	/**
	 * @param   mixed  $table  Unused.
	 * @param   mixed  $key    Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function insert($table, $key = null)
	{
		return $this;
	}

	/**
	 * @param   mixed  $columns  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function columns($columns)
	{
		return $this;
	}

	/**
	 * @param   mixed  $values  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function values($values)
	{
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
	public function setQuery($query, $offset = 0, $limit = 0)
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
		return 'SELECT id,published,status,event_date FROM #__wendfold_events WHERE id = :id';
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
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
			'entries'         => $this->model->store->entries,
			'eventStates'     => $this->eventStates,
			'eventRows'       => $this->eventRows,
			'publishedValues' => $this->model->publishedValues,
			'deletedIds'      => $this->model->deletedIds,
			'storedData'      => $this->model->storedData,
		];
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);
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
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);
		$this->model->order[] = 'commit';
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
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);

		if ($snapshot === null)
		{
			return;
		}

		$this->model->store->entries = $snapshot['entries'];
		$this->eventStates = $snapshot['eventStates'];
		$this->eventRows = $snapshot['eventRows'];
		$this->model->publishedValues = $snapshot['publishedValues'];
		$this->model->deletedIds = $snapshot['deletedIds'];
		$this->model->storedData = $snapshot['storedData'];
		$this->model->order[] = 'rollback';
	}

	/**
	 * @param   mixed  $name  Name.
	 * @param   mixed  $as    Alias.
	 *
	 * @return  string|array
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
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		return 1;
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		if ($this->boundId === null)
		{
			return null;
		}

		return $this->eventRows[$this->boundId] ?? null;
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
	 * @param   string  $name       Method.
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

/**
 * ActivitylogModel that injects an in-memory metric service.
 *
 * @since  1.0.0
 */
final class DeferralActivitylogModel extends ActivitylogModel
{
	/**
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * @var    CountingAwardRecomputer
	 * @since  1.0.0
	 */
	public CountingAwardRecomputer $awards;

	/**
	 * @var    DeferralActivityDatabase
	 * @since  1.0.0
	 */
	public DeferralActivityDatabase $database;

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $order = [];

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failInsert = false;

	/**
	 * @var    MetricService|null
	 * @since  1.0.0
	 */
	private ?MetricService $metricServiceInstance = null;

	/**
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getMemberMetrics(): array
	{
		return [
			(object) [
				'id'                          => 1,
				'title'                       => 'Distance',
				'unit_label'                  => 'mi',
				'decimals'                    => 0,
				'member_entry_needs_approval' => 0,
			],
		];
	}

	/**
	 * @param   integer  $profileId     Profile id.
	 * @param   integer  $motorcycleId  Motorcycle id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	protected function ownsMotorcycle(int $profileId, int $motorcycleId): bool
	{
		return false;
	}

	/**
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $actorId  Actor id.
	 *
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService($db, int $actorId): MetricService
	{
		if ($this->metricServiceInstance === null)
		{
			$this->metricServiceInstance = new MetricService($this->store, $actorId, $this->awards);
		}

		return $this->metricServiceInstance;
	}

	/**
	 * @return  DeferralActivityDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * ActivitiesModel decide harness with an in-memory metric service.
 *
 * @since  1.0.0
 */
final class DeferralActivitiesModel extends ActivitiesModel
{
	/**
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * @var    CountingAwardRecomputer
	 * @since  1.0.0
	 */
	public CountingAwardRecomputer $awards;

	/**
	 * @var    DeferralActivityDatabase
	 * @since  1.0.0
	 */
	public DeferralActivityDatabase $database;

	/**
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $activityIds = [];

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $order = [];

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failDecide = false;

	/**
	 * @var    MetricService|null
	 * @since  1.0.0
	 */
	private ?MetricService $metricServiceInstance = null;

	/**
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $actorId  Actor id.
	 *
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService($db, int $actorId): MetricService
	{
		if ($this->metricServiceInstance === null)
		{
			$this->metricServiceInstance = new MetricService($this->store, $actorId, $this->awards);
		}

		return $this->metricServiceInstance;
	}

	/**
	 * @return  DeferralActivityDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Shared activity txn stand-in for activity log and activities decide paths.
 *
 * @since  1.0.0
 */
final class DeferralActivityDatabase implements DatabaseInterface
{
	/**
	 * @var    list<array{entries: list<array<string, mixed>>}>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $mode = '';

	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $boundId = 0;

	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $nextActivityId = 30;

	/**
	 * @param   DeferralActivitylogModel|DeferralActivitiesModel  $model  Model under test.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private object $model)
	{
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
		$this->mode = '';
		$this->boundId = 0;

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
	 * @param   mixed  $columns  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->mode = 'count';

		return $this;
	}

	/**
	 * @param   mixed  $table  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table)
	{
		return $this;
	}

	/**
	 * @param   mixed  $table  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function update($table = null)
	{
		$this->mode = 'update';

		return $this;
	}

	/**
	 * @param   mixed  $conditions  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function set($conditions)
	{
		return $this;
	}

	/**
	 * @param   mixed  $condition  Unused.
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
	 * @param   mixed  $key            Bind key.
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
		if ((string) $key === ':activityId')
		{
			$this->boundId = (int) $value;
		}

		return $this;
	}

	/**
	 * @param   mixed    $query   Unused.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		return $this;
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
		return true;
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getAffectedRows()
	{
		return $this->mode === 'update' ? 1 : 0;
	}

	/**
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if ($this->model instanceof DeferralActivitiesModel)
		{
			return \in_array($this->boundId, $this->model->activityIds, true) ? 1 : 0;
		}

		return 0;
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		return null;
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
	 * @param   string  $table  Table.
	 * @param   object  $row    Row.
	 * @param   string  $key    Primary key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function insertObject($table, &$row, $key = null)
	{
		if ($this->model instanceof DeferralActivitylogModel && $this->model->failInsert)
		{
			throw new \RuntimeException('Simulated insert failure.');
		}

		if ($key !== null)
		{
			$row->$key = $this->nextActivityId++;
		}

		return true;
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
			'entries' => $this->model->store->entries,
		];
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);
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
		if ($this->model instanceof DeferralActivitiesModel && $this->model->failDecide)
		{
			throw new \RuntimeException('Simulated decide failure.');
		}

		array_pop($this->snapshots);
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);
		$this->model->order[] = 'commit';
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
		$this->model->store->simulatedOuterTxnDepth = \count($this->snapshots);

		if ($snapshot !== null)
		{
			$this->model->store->entries = $snapshot['entries'];
		}

		$this->model->order[] = 'rollback';
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
		return (string) $name;
	}

	/**
	 * @param   string  $name       Method.
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
