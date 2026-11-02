<?php

/**
 * Wendfold - Admin attendance roster must honour profile waiver_signed.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\AttendanceRosterStatus;
use Wendfold\Component\Wendfold\Administrator\Model\AttendanceModel;
use Wendfold\Tests\Support\AttendanceAppDouble;
use Wendfold\Tests\Support\AttendanceDatabaseStub;
use Wendfold\Tests\Support\AttendanceQueryStub;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/AttendanceModelDouble.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AttendanceMemberLabel.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AttendanceRosterStatus.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/AttendanceModel.php';

/**
 * Exposes getListQuery without booting Joomla's ListModel constructor.
 *
 * @since  1.0.0
 */
final class AttendanceModelListHarness extends AttendanceModel
{
	/**
	 * @var    AttendanceDatabaseStub
	 * @since  1.0.0
	 */
	private AttendanceDatabaseStub $database;

	/**
	 * @param   AttendanceDatabaseStub|null  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?AttendanceDatabaseStub $database = null)
	{
		$this->database = $database ?? new AttendanceDatabaseStub;
	}

	/**
	 * @return  AttendanceQueryStub
	 *
	 * @since   1.0.0
	 */
	public function exposeListQuery(): AttendanceQueryStub
	{
		/** @var AttendanceQueryStub $query */
		$query = $this->getListQuery();

		return $query;
	}

	/**
	 * @return  AttendanceDatabaseStub
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Attendance roster waiver badges follow the profile column, not a missing select.
 *
 * @since  1.0.0
 */
final class AttendanceWaiverBadgeTest extends TestCase
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
	 * Signed members must not get the NO WAIVER badge; unsigned members must.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBadgeCodeDistinguishesSignedFromUnsigned(): void
	{
		$this->assertSame('waiver', AttendanceRosterStatus::badgeCode(0, 1));
		$this->assertSame('waiver', AttendanceRosterStatus::badgeCode(null, 1));
		$this->assertSame('waiver', AttendanceRosterStatus::badgeCode('0', 1));
		$this->assertNull(AttendanceRosterStatus::badgeCode(1, 1));
		$this->assertNull(AttendanceRosterStatus::badgeCode('1', '1'));
		$this->assertSame('dues', AttendanceRosterStatus::badgeCode(1, 0));
		$this->assertSame('dues', AttendanceRosterStatus::badgeCode(1, null));
	}

	/**
	 * The attendance list query selects the profile waiver and dues columns.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListQuerySelectsWaiverAndDuesColumns(): void
	{
		Factory::$application = new AttendanceAppDouble(42);

		$model = new AttendanceModelListHarness;
		$query = $model->exposeListQuery();
		$sql = (string) $query;
		$columns = $query->selectedColumns();

		$this->assertContains('p.waiver_signed', $columns);
		$this->assertContains('p.dues_paid', $columns);
		$this->assertStringContainsString('#__wendfold_profiles', $sql);
		$this->assertStringContainsString('p.waiver_signed', $sql);
		$this->assertStringContainsString('p.dues_paid', $sql);
		$this->assertStringContainsString('a.event_id = 42', $sql);

		$joined = implode(' ', $query->joinedTables());
		$this->assertStringContainsString('#__wendfold_profiles', $joined);
	}

	/**
	 * The attendance template decides badges from the selected profile fields.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAttendanceTemplateUsesRosterStatusHelper(): void
	{
		$template = file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/tmpl/attendance/default.php');

		$this->assertNotFalse($template);
		$this->assertStringContainsString('AttendanceRosterStatus::badgeCode', $template);
		$this->assertStringContainsString('$item->waiver_signed ?? null', $template);
		$this->assertStringContainsString('$item->dues_paid ?? null', $template);
		$this->assertStringContainsString("\$statusBadge === 'waiver'", $template);
		$this->assertStringContainsString("\$statusBadge === 'dues'", $template);
		$this->assertStringContainsString(
			"htmlspecialchars(Text::_('COM_WENDFOLD_BADGE_NO_WAIVER'), ENT_QUOTES, 'UTF-8')",
			$template
		);
	}
}
