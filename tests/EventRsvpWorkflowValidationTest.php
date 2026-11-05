<?php

/**
 * Wendfold - Allow RSVP and Workflow Mode validation on event save.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable Joomla.Commenting.FunctionComment.Missing

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MeetingSaveInput;
use Wendfold\Component\Wendfold\Administrator\Model\EventModel;
use Wendfold\Tests\Double\EventApplication;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/MetricServiceRequires.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';

/**
 * A posted Allow RSVP or Workflow Mode is stored only when it is an allowed choice.
 */
final class EventRsvpWorkflowValidationTest extends TestCase
{
	protected function tearDown(): void
	{
		Factory::$application = null;

		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function eventShapeProvider(): array
	{
		$shapes = [
			'ride' => [1, 0],
			'rally' => [3, 0],
			'social' => [4, 0],
			'other' => [5, 0],
			'overnight' => [6, 0],
			'standard meeting' => [2, 0],
			'chapter meeting' => [2, 1],
			'auxiliary meeting' => [2, 2],
			'officers meeting' => [2, 3],
			'road captain meeting' => [2, 4],
		];

		return $shapes;
	}

	/**
	 * Values that are not exactly 0 or 1.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function badAllowRsvpProvider(): array
	{
		return [
			'int 2' => [2],
			'string 2' => ['2'],
			'int 9' => [9],
			'string 9' => ['9'],
			'int -1' => [-1],
			'string -1' => ['-1'],
			'int 6' => [6],
			'string 6' => ['6'],
			'blank' => [''],
			'abc' => ['abc'],
			'leading zero' => ['01'],
			'decimal' => ['1.0'],
			'padded 1' => [' 1'],
			'array of int' => [[1]],
			'array of string' => [['1']],
		];
	}

	/**
	 * Values that are not exactly 1, 2, 3, 4, or 5.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function badWorkflowProvider(): array
	{
		return [
			'int 0' => [0],
			'string 0' => ['0'],
			'int 6' => [6],
			'string 6' => ['6'],
			'int 9' => [9],
			'string 9' => ['9'],
			'int -1' => [-1],
			'string -1' => ['-1'],
			'blank' => [''],
			'abc' => ['abc'],
			'leading zero' => ['01'],
			'decimal' => ['1.0'],
			'padded 3' => [' 3'],
			'padded 1' => [' 1'],
			'array of int' => [[1]],
			'array of string' => [['3']],
		];
	}

	/**
	 * @return array<string, array{0: int|string}>
	 */
	public static function validAllowRsvpProvider(): array
	{
		return [
			'int 0' => [0],
			'int 1' => [1],
			'string 0' => ['0'],
			'string 1' => ['1'],
		];
	}

	/**
	 * @return array<string, array{0: int|string}>
	 */
	public static function validWorkflowProvider(): array
	{
		$cases = [];

		foreach ([1, 2, 3, 4, 5] as $mode)
		{
			$cases['int ' . $mode] = [$mode];
			$cases['string ' . $mode] = [(string) $mode];
		}

		return $cases;
	}

	/**
	 * A present non-blank value fails the form's option check. Removing
	 * validate="options" from allow_rsvp makes this fail. A blank is not
	 * required, so the form accepts it and the save helper refuses it.
	 * Removing that check makes the blank case fail.
	 */
	#[DataProvider('badAllowRsvpProvider')]
	public function testBadAllowRsvpIsRefusedOnNewAndEdit(mixed $value): void
	{
		foreach (self::eventShapeProvider() as $label => [$eventType, $subtype])
		{
			$this->assertBadChoiceIsRefused(
				'allow_rsvp',
				'Invalid field: Allow RSVP',
				$value,
				$eventType,
				$subtype,
				$label
			);
		}
	}

	/**
	 * A present non-blank value fails the form's option check. Removing
	 * validate="options" from workflow_mode makes this fail. A blank passes
	 * the form and the save helper refuses it.
	 */
	#[DataProvider('badWorkflowProvider')]
	public function testBadWorkflowModeIsRefusedOnNewAndEdit(mixed $value): void
	{
		foreach (self::eventShapeProvider() as $label => [$eventType, $subtype])
		{
			$this->assertBadChoiceIsRefused(
				'workflow_mode',
				'Invalid field: Workflow Mode',
				$value,
				$eventType,
				$subtype,
				$label
			);
		}
	}

	#[DataProvider('validAllowRsvpProvider')]
	public function testValidAllowRsvpIsStored(int|string $value): void
	{
		foreach (self::eventShapeProvider() as $label => [$eventType, $subtype])
		{
			foreach ([true, false] as $isNew)
			{
				$posted = self::posted($eventType, $subtype, $isNew);
				$posted['allow_rsvp'] = $value;
				$posted['workflow_mode'] = '4';
				$model = new EventModel;
				$validated = $model->validate(null, $posted);
				$where = $label . ' ' . ($isNew ? 'new' : 'edit');

				self::assertIsArray($validated, $where . ' ' . (string) $model->getError());

				$saved = MeetingSaveInput::prepareSaveData($validated, $posted, $isNew, ...self::typeArgs($eventType));

				self::assertNotNull($saved, $where);
				self::assertSame((int) $value, $saved['allow_rsvp'], $where);
				self::assertSame(4, $saved['workflow_mode'], $where);
			}
		}
	}

	#[DataProvider('validWorkflowProvider')]
	public function testValidWorkflowModeIsStored(int|string $value): void
	{
		foreach (self::eventShapeProvider() as $label => [$eventType, $subtype])
		{
			foreach ([true, false] as $isNew)
			{
				$posted = self::posted($eventType, $subtype, $isNew);
				$posted['workflow_mode'] = $value;
				$posted['allow_rsvp'] = '0';
				$model = new EventModel;
				$validated = $model->validate(null, $posted);
				$where = $label . ' ' . ($isNew ? 'new' : 'edit');

				self::assertIsArray($validated, $where . ' ' . (string) $model->getError());

				$saved = MeetingSaveInput::prepareSaveData($validated, $posted, $isNew, ...self::typeArgs($eventType));

				self::assertNotNull($saved, $where);
				self::assertSame((int) $value, $saved['workflow_mode'], $where);
				self::assertSame(0, $saved['allow_rsvp'], $where);
			}
		}
	}

	/**
	 * Form validation runs before save. An omitted field is not required, so
	 * validation passes and an edit keeps the value already on the record.
	 */
	#[DataProvider('eventShapeProvider')]
	public function testOmittedFieldsOnEditPassValidationAndKeepTheStoredValue(int $eventType, int $subtype): void
	{
		foreach ([1, 4, 5] as $workflow)
		{
			foreach ([0, 1] as $rsvp)
			{
				$posted = self::posted($eventType, $subtype, false);
				$model = new EventModel;
				$validated = $model->validate(null, $posted);

				self::assertIsArray($validated, (string) $model->getError());
				self::assertArrayNotHasKey('workflow_mode', $validated);
				self::assertArrayNotHasKey('allow_rsvp', $validated);

				$untouched = MeetingSaveInput::prepareSaveData($validated, $posted, false, ...self::typeArgs($eventType));

				self::assertNotNull($untouched);
				self::assertArrayNotHasKey('workflow_mode', $untouched);
				self::assertArrayNotHasKey('allow_rsvp', $untouched);

				$validated['workflow_mode'] = $workflow;
				$validated['allow_rsvp'] = $rsvp;
				$saved = MeetingSaveInput::prepareSaveData($validated, $posted, false, ...self::typeArgs($eventType));

				self::assertNotNull($saved);
				self::assertSame($workflow, $saved['workflow_mode']);
				self::assertSame($rsvp, $saved['allow_rsvp']);
			}
		}
	}

	/**
	 * An omitted field on a new record still uses today's default.
	 */
	public function testOmittedFieldsOnANewRecordPassValidationAndUseTheDefaults(): void
	{
		foreach ([0, 1, 2, 3, 4] as $subtype)
		{
			$posted = self::posted(2, $subtype, true);
			$model = new EventModel;
			$validated = $model->validate(null, $posted);

			self::assertIsArray($validated, (string) $model->getError());
			self::assertArrayNotHasKey('workflow_mode', $validated);
			self::assertArrayNotHasKey('allow_rsvp', $validated);

			$saved = MeetingSaveInput::prepareSaveData($validated, $posted, true, ...self::typeArgs(2));

			self::assertNotNull($saved);
			self::assertSame(3, $saved['workflow_mode'], 'meeting subtype ' . $subtype);
			self::assertSame(1, $saved['allow_rsvp']);
		}

		$expected = [
			1 => 2,
			3 => 2,
			4 => 1,
			5 => 1,
			6 => 5,
		];

		foreach ($expected as $eventType => $workflow)
		{
			$posted = self::posted($eventType, 0, true);
			$model = new EventModel;
			$validated = $model->validate(null, $posted);

			self::assertIsArray($validated, (string) $model->getError());

			$saved = MeetingSaveInput::prepareSaveData($validated, $posted, true, ...self::typeArgs($eventType));

			self::assertNotNull($saved);
			self::assertSame($workflow, $saved['workflow_mode'], 'event type ' . $eventType);
			self::assertSame(1, $saved['allow_rsvp']);
		}
	}

	/**
	 * The model itself must not fill an omitted field on edit, and a new
	 * record must still receive the default, including officers RSVP off.
	 */
	#[DataProvider('eventShapeProvider')]
	public function testModelSaveKeepsAnOmittedEditAndDefaultsANewRecord(int $eventType, int $subtype): void
	{
		$edit = self::posted($eventType, $subtype, false);
		$edit['gpx_file'] = 'media/com_wendfold/gpx/keep.gpx';
		Factory::$application = new EventApplication($edit);
		$editModel = new SavingEventModel;

		self::assertTrue($editModel->save($edit));
		self::assertArrayNotHasKey('workflow_mode', $editModel->storedData);
		self::assertArrayNotHasKey('allow_rsvp', $editModel->storedData);

		$kept = self::posted($eventType, $subtype, false);
		$kept['gpx_file'] = 'media/com_wendfold/gpx/keep.gpx';
		$kept['workflow_mode'] = 5;
		$kept['allow_rsvp'] = 0;
		$keptPost = self::posted($eventType, $subtype, false);
		Factory::$application = new EventApplication($keptPost);
		$keptModel = new SavingEventModel;

		self::assertTrue($keptModel->save($kept));
		self::assertSame(5, $keptModel->storedData['workflow_mode']);
		self::assertSame(0, $keptModel->storedData['allow_rsvp']);

		$created = self::posted($eventType, $subtype, true);
		Factory::$application = new EventApplication($created);
		$createModel = new SavingEventModel;

		self::assertTrue($createModel->save($created));

		if ($eventType === 2)
		{
			self::assertSame(3, $createModel->storedData['workflow_mode']);
			self::assertSame(1, $createModel->storedData['allow_rsvp']);

			return;
		}

		$expected = match ($eventType) {
			1, 3 => 2,
			6 => 5,
			default => 1,
		};

		self::assertSame($expected, $createModel->storedData['workflow_mode']);
		self::assertSame(1, $createModel->storedData['allow_rsvp']);
	}

	private function assertBadChoiceIsRefused(
		string $field,
		string $message,
		mixed $value,
		int $eventType,
		int $subtype,
		string $label
	): void {
		foreach ([true, false] as $isNew)
		{
			$where = $label . ' ' . ($isNew ? 'new' : 'edit');
			$posted = self::posted($eventType, $subtype, $isNew);
			$posted[$field] = $value;
			$model = new EventModel;
			$validated = $model->validate(null, $posted);

			if ($value === '')
			{
				self::assertIsArray($validated, $where . ' ' . (string) $model->getError());
			}
			else
			{
				self::assertFalse($validated, $where);
				self::assertSame($message, $model->getError(), $where);
			}

			$saved = MeetingSaveInput::prepareSaveData($posted, $posted, $isNew, ...self::typeArgs($eventType));

			self::assertNull($saved, $where);

			Factory::$application = new EventApplication($posted);
			$saver = new FixtureEventModel;

			self::assertFalse($saver->save($posted), $where);
			self::assertSame($message, $saver->getError(), $where);
		}
	}

	/**
	 * Behavior, default workflow, and default riding for a fixture type id.
	 *
	 * @param   integer  $eventType  1 ride, 2 meeting, 3 rally, 6 overnight, otherwise general.
	 *
	 * @return  array{0: int, 1: int, 2: int}
	 */
	private static function typeArgs(int $eventType): array
	{
		return match ($eventType) {
			1, 3 => [1, 2, 1],
			2 => [2, 3, 0],
			6 => [3, 5, 1],
			default => [4, 1, 0],
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function posted(int $eventType, int $subtype, bool $isNew): array
	{
		$posted = [
			'title' => 'Chapter night',
			'event_date' => '2026-11-02',
			'event_type_id' => $eventType,
			'audience_scope' => '2',
		];

		if (!$isNew)
		{
			$posted['id'] = 15;
		}

		return $posted;
	}
}

namespace Wendfold\Tests;

use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Administrator\Model\EventModel;

/**
 * Event save that stops before a database call.
 */
final class SavingEventModel extends FixtureEventModel
{
	protected function saveInlineLocations(array $data): void
	{
	}

	protected function saveVolunteerRoles(int $eventId, array $roleRows): void
	{
	}

	public function getDatabase()
	{
		return new FluentDatabase;
	}
}

/**
 * Query stand-in for the status read on an edit save.
 */
final class FluentDatabase implements DatabaseInterface
{
	/**
	 * Last select list, so a date read can be told from the status read.
	 *
	 * @var    string
	 */
	private string $selected = '';

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

	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(' ', $columns) : (string) $columns;

		return $this;
	}

	public function from($table)
	{
		return $this;
	}

	public function join($type, $condition) // phpcs:ignore Joomla.Commenting.FunctionComment.Missing
	{
		return $this;
	}

	public function where($condition)
	{
		return $this;
	}

	public function bind($key, &$value = null, $dataType = 'string', $length = 0, $driverOptions = [])
	{
		return $this;
	}

	public function delete($table = null)
	{
		return $this;
	}

	public function insert($table, $key = null)
	{
		return $this;
	}

	public function columns($columns)
	{
		return $this;
	}

	public function values($values)
	{
		return $this;
	}

	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'event_date'))
		{
			$this->selected = 'event_date';
		}

		return $this;
	}

	public function __toString(): string
	{
		return 'SELECT id,published,status,event_date FROM #__wendfold_events WHERE id = :id';
	}

	public function execute()
	{
		return true;
	}

	public function getAffectedRows()
	{
		return 0;
	}

	public function transactionStart($asSavepoint = false): void
	{
	}

	public function transactionCommit($toSavepoint = false): void
	{
	}

	public function transactionRollback($toSavepoint = false): void
	{
	}

	public function quoteName($name, $as = null)
	{
		if (\is_array($name))
		{
			return array_map(static fn($part): string => (string) $part, $name);
		}

		return (string) $name;
	}

	public function loadResult()
	{
		if (str_contains($this->selected, 'event_date'))
		{
			return '2026-06-01';
		}

		return 1;
	}

	public function loadObject()
	{
		return (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-06-01',
		];
	}

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
	 */
	public function __call(string $name, array $arguments)
	{
		return $this;
	}
}
