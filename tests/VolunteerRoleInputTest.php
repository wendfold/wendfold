<?php

/**
 * Wendfold - volunteer role subform text is string-filtered on save.
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
use Wendfold\Component\Wendfold\Administrator\Helper\VolunteerRoleInput;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Double\EventApplication;
use Wendfold\Tests\Support\CountingAwardRecomputer;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/MetricServiceRequires.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';
require_once __DIR__ . '/support/CountingAwardRecomputer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventCancelTransition.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/InputStringFilter.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/VolunteerRoleInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/EventCancelNoticeService.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';

/**
 * Proves role description tags are stripped while & and quotes remain.
 *
 * @since  1.0.0
 */
final class VolunteerRoleInputTest extends TestCase
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
	 * filterRows strips tags and keeps & / quotes / newlines.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFilterRowsStripsTagsAndKeepsAmpersandAndQuotes(): void
	{
		$rows = VolunteerRoleInput::filterRows([
			[
				'title'       => '<em>Greeter</em>',
				'description' => "<b>x</b> & \"quotes\"\r\nline2",
				'shift_label' => 'AM <script>bad</script>',
			],
			]
		);

		self::assertSame('Greeter', $rows[0]['title']);
		self::assertSame("x & \"quotes\"\r\nline2", $rows[0]['description']);
		self::assertSame('AM bad', $rows[0]['shift_label']);
		self::assertStringNotContainsString('<', $rows[0]['description']);
		self::assertStringNotContainsString('>', $rows[0]['description']);
	}

	/**
	 * EventModel prefers form data and runs VolunteerRoleInput before writing roles.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventModelPrefersFilteredFormDataAndRunsVolunteerRoleInput(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php'
		);

		self::assertStringContainsString(
			"\$data['volunteer_roles'] ?? (\$post['volunteer_roles'] ?? [])",
			$source
		);
		self::assertStringNotContainsString(
			"\$post['volunteer_roles'] ?? (\$data['volunteer_roles']",
			$source
		);
		self::assertStringContainsString('VolunteerRoleInput::filterRows', $source);
	}

	/**
	 * Event save string-filters a raw posted role description before persisting.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventSaveFiltersVolunteerRoleDescriptionFromRawPost(): void
	{
		$jform = [
			'title'           => 'Volunteer night',
			'event_type_id'   => 1,
			'event_date'      => '2026-07-01',
			'status'          => 1,
			'published'       => 1,
			'audience'        => 1,
			'allow_rsvp'      => 1,
			'workflow_mode'   => 4,
			'metric_credits'  => ['1' => '10'],
			'volunteer_roles' => [
				[
					'title'       => 'Door',
					'description' => "<b>x</b> & \"quotes\"\r\nline2",
				],
			],
		];
		Factory::$application = new EventApplication($jform);

		$model = new RecordingVolunteerRolesEventModel;
		$model->store = new MemoryMetricEntryStore;
		$model->awards = new CountingAwardRecomputer;
		$model->database = new RecordingVolunteerRolesDatabase;

		// AdminModel stub getState() returns event id 15 for a new save.
		$model->store->eventDates[15] = '2026-07-01';
		$model->store->eventStates[15] = 1;

		self::assertTrue($model->save($jform));
		self::assertCount(1, $model->savedRoles);
		self::assertSame('Door', $model->savedRoles[0]['title']);
		self::assertSame("x & \"quotes\"\r\nline2", $model->savedRoles[0]['description']);
		self::assertStringNotContainsString('<b>', $model->savedRoles[0]['description']);
	}
}

/**
 * Event save that records filtered volunteer role rows.
 *
 * @since  1.0.0
 */
final class RecordingVolunteerRolesEventModel extends FixtureEventModel
{
	/**
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	public array $savedRoles = [];

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
	 * @var    RecordingVolunteerRolesDatabase
	 * @since  1.0.0
	 */
	public RecordingVolunteerRolesDatabase $database;

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
	 * @param   integer            $eventId   Event id.
	 * @param   array<int, array>  $roleRows  Role rows after VolunteerRoleInput::filterRows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveVolunteerRoles(int $eventId, array $roleRows): void
	{
		$this->savedRoles = array_values($roleRows);
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
	 * @param   DatabaseInterface  $db                  Database.
	 * @param   integer            $eventId             Event id.
	 * @param   boolean            $cancelTransitioned  Whether cancel notices should send.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function dispatchEventCancelNotice($db, int $eventId, bool $cancelTransitioned): void
	{
	}

	/**
	 * @return  RecordingVolunteerRolesDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Database stand-in for a successful volunteer-role save path.
 *
 * @since  1.0.0
 */
final class RecordingVolunteerRolesDatabase implements DatabaseInterface
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * @param   boolean  $asSavepoint  Nested transaction flag.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionStart($asSavepoint = false): void
	{
	}

	/**
	 * @param   boolean  $asSavepoint  Nested transaction flag.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionCommit($asSavepoint = false): void
	{
	}

	/**
	 * @param   boolean  $asSavepoint  Nested transaction flag.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionRollback($asSavepoint = false): void
	{
	}

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  $this
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		return $this;
	}

	/**
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
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
	 * @return  $this
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'event_date'))
		{
			$this->selected = 'event_date';
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
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if (str_contains($this->selected, 'event_date'))
		{
			return '2026-07-01';
		}

		if (str_contains($this->selected, 'status') || str_contains($this->selected, 'published'))
		{
			return 1;
		}

		return 1;
	}

	/**
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		return (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
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
	 * @return  array<int, mixed>
	 *
	 * @since   1.0.0
	 */
	public function loadColumn()
	{
		return [];
	}

	/**
	 * @param   mixed  $name  Name.
	 * @param   mixed  $as    Alias.
	 *
	 * @return  mixed
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
	 * @param   mixed  $value  Quote value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quote($value)
	{
		return "'" . $value . "'";
	}

	/**
	 * @param   string  $name       Method name.
	 * @param   array   $arguments  Arguments.
	 *
	 * @return  $this
	 *
	 * @since   1.0.0
	 */
	public function __call(string $name, array $arguments)
	{
		return $this;
	}
}
