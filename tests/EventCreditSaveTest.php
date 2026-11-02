<?php

/**
 * Wendfold - event credits validate before the event row is saved.
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
use Wendfold\Tests\Double\EventApplication;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/MetricServiceRequires.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';

/**
 * A credit error must not leave a half-saved event.
 *
 * @since  1.0.0
 */
final class EventCreditSaveTest extends TestCase
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
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInvalidCreditsRefuseBeforeParentSave(): void
	{
		$jform = [
			'title'          => 'Credit check',
			'event_type_id'  => 1,
			'event_date'     => '2026-07-01',
			'status'         => 1,
			'published'      => 1,
			'audience'       => 1,
			'allow_rsvp'     => 1,
			'workflow_mode'  => 2,
			'metric_credits' => ['1' => '1.5'],
		];
		Factory::$application = new EventApplication($jform);

		$model = new class extends FixtureEventModel
		{
			/**
			 * Always refuses so parent::save is never reached.
			 *
			 * @param   array<string, mixed>  $posted  Posted credits.
			 *
			 * @return  array<int, string>
			 *
			 * @throws  \InvalidArgumentException
			 */
			protected function parsePostedMetricCredits(array $posted): array
			{
				throw new \InvalidArgumentException('Metric value has too many decimal places.');

				return [];
			}

			/**
			 * @param   array  $data  Event fields.
			 *
			 * @return  void
			 */
			protected function saveInlineLocations(array $data): void
			{
			}

			/**
			 * @param   integer  $eventId   Event id.
			 * @param   array    $roleRows  Role rows.
			 *
			 * @return  void
			 */
			protected function saveVolunteerRoles(int $eventId, array $roleRows): void
			{
			}
		};

		$result = $model->save($jform);

		$this->assertFalse($result);
		$this->assertSame('COM_WENDFOLD_METRIC_CREDIT_INVALID', $model->getError());
		$this->assertSame([], $model->storedData);
	}

	/**
	 * A resync failure rolls back the event row, roles, and credits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResyncFailureRollsBackEventRolesAndCredits(): void
	{
		\Joomla\CMS\Log\Log::$messages = [];
		$model = $this->atomicModel(true);
		$result = $model->save($this->validJform());

		$this->assertFalse($result);
		$this->assertSame('COM_WENDFOLD_ERROR_SAVE_FAILED', $model->getError());
		$this->assertSame([], $model->storedData);
		$this->assertSame([], $model->roleSaves);
		$this->assertSame([], $model->getDatabase()->creditOps);
		$this->assertNotSame([], \Joomla\CMS\Log\Log::$messages);
		$this->assertSame('com_wendfold', \Joomla\CMS\Log\Log::$messages[0][2]);
		$this->assertStringContainsString(
			'Event credits could not be applied because the event date is missing.',
			\Joomla\CMS\Log\Log::$messages[0][0]
		);
	}

	/**
	 * A trashed event keeps published = -2 when the form is saved again.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashedEventFormRoundTripKeepsPublishedState(): void
	{
		$xml = simplexml_load_file(dirname(__DIR__) . '/com_wendfold/admin/forms/event.xml');
		$this->assertNotFalse($xml);
		$published = $xml->xpath('//field[@name="published"]')[0] ?? null;
		$this->assertNotNull($published);

		$values = [];

		foreach ($published->option as $option)
		{
			$values[] = (string) $option['value'];
		}

		$this->assertContains('1', $values);
		$this->assertContains('0', $values);
		$this->assertContains('2', $values);
		$this->assertContains('-2', $values);
		$this->assertSame('options', (string) $published['validate']);

		$jform = [
			'id'             => 15,
			'title'          => 'Trashed ride',
			'event_type_id'  => 1,
			'event_date'     => '2026-07-01',
			'status'         => 1,
			'published'      => -2,
			'audience'       => 1,
			'allow_rsvp'     => 1,
			'workflow_mode'  => 2,
			'metric_credits' => [],
			'gpx_file'       => 'media/com_wendfold/gpx/keep.gpx',
		];
		Factory::$application = new EventApplication($jform);
		$model = $this->atomicModel(false);

		$this->assertTrue($model->validate(null, $jform) !== false);
		$this->assertTrue($model->save($jform));
		$this->assertSame(-2, (int) $model->storedData['published']);
	}

	/**
	 * A successful save keeps the event row, roles, and credits together.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSuccessfulSaveWritesEventRolesAndCredits(): void
	{
		$model = $this->atomicModel(false);
		$result = $model->save($this->validJform());

		$this->assertTrue($result);
		$this->assertNotSame([], $model->storedData);
		$this->assertSame([15], $model->roleSaves);
		$this->assertNotSame([], $model->getDatabase()->creditOps);
	}

	/**
	 * Posted fields that pass credit validation.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	private function validJform(): array
	{
		$jform = [
			'title'          => 'Atomic save',
			'event_type_id'  => 1,
			'event_date'     => '2026-07-01',
			'status'         => 1,
			'published'      => 1,
			'audience'       => 1,
			'allow_rsvp'     => 1,
			'workflow_mode'  => 2,
			'metric_credits' => ['1' => '10'],
			'volunteer_roles' => [
				['title' => 'Sweep'],
			],
		];
		Factory::$application = new EventApplication($jform);

		return $jform;
	}

	/**
	 * Event save that tracks row, roles, and credits under one transaction.
	 *
	 * @param   boolean  $failResync  When true, resync sees a missing event date.
	 *
	 * @return  AtomicEventModel
	 *
	 * @since   1.0.0
	 */
	private function atomicModel(bool $failResync): AtomicEventModel
	{
		$model = new AtomicEventModel;
		$model->database = new AtomicEventDatabase($model, $failResync);

		return $model;
	}
}

/**
 * Event save that records volunteer roles for the atomicity assertions.
 *
 * @since  1.0.0
 */
final class AtomicEventModel extends FixtureEventModel
{
	/**
	 * Event ids passed to saveVolunteerRoles.
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $roleSaves = [];

	/**
	 * Database used by the save path.
	 *
	 * @var    AtomicEventDatabase
	 * @since  1.0.0
	 */
	public AtomicEventDatabase $database;

	/**
	 * Accept a fixed credit map so the save reaches the transaction.
	 *
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
		$this->roleSaves[] = $eventId;
	}

	/**
	 * @return  AtomicEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Nested-transaction stand-in that restores model state on rollback.
 *
 * @since  1.0.0
 */
final class AtomicEventDatabase implements DatabaseInterface
{
	/**
	 * Credit write ops recorded inside the open transaction.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $creditOps = [];

	/**
	 * Last select list.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * Snapshots taken by transactionStart.
	 *
	 * @var    list<array{storedData: array, roleSaves: list<int>, creditOps: list<string>}>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   AtomicEventModel  $model        Model whose in-memory writes roll back.
	 * @param   boolean           $failResync   When true, eventDate reads as missing.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private AtomicEventModel $model, private bool $failResync)
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
	 * @param   mixed  $columns  Selected columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(' ', $columns) : (string) $columns;

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
	 * @param   mixed  $key            Unused.
	 * @param   mixed  $value          Unused.
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
		$this->creditOps[] = 'delete';

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
		$this->creditOps[] = 'insert';

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
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'event_date'))
		{
			$this->selected = 'event_date';
		}

		if (\is_string($query) && str_contains($query, 'published'))
		{
			$this->selected = 'published';
		}

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
			'storedData' => $this->model->storedData,
			'roleSaves'  => $this->model->roleSaves,
			'creditOps'  => $this->creditOps,
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

		$this->model->storedData = $snapshot['storedData'];
		$this->model->roleSaves = $snapshot['roleSaves'];
		$this->creditOps = $snapshot['creditOps'];
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
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if (str_contains($this->selected, 'event_date'))
		{
			return $this->failResync ? null : '2026-07-01';
		}

		if (str_contains($this->selected, 'published'))
		{
			return 1;
		}

		return 1;
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		return (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => $this->failResync ? null : '2026-07-01',
		];
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
	 * Query builder methods the metric credit save calls.
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
