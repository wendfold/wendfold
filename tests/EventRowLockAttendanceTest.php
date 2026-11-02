<?php

/**
 * Wendfold - event row lock, atomic status changes, attendance guards (PR5a).
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Helper\EventStatusTransition;
use Wendfold\Component\Wendfold\Administrator\Service\EventCancelNoticeService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Double\EventApplication;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventDeleteCascade.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventMetricCredits.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeBehavior.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/GpxFile.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';

$pr5aHelperDir = dirname(__DIR__) . '/com_wendfold/admin/src/Helper/';

if (\is_file($pr5aHelperDir . 'DuplicateKey.php'))
{
	require_once $pr5aHelperDir . 'DuplicateKey.php';
}

if (\is_file($pr5aHelperDir . 'EventRowLock.php'))
{
	require_once $pr5aHelperDir . 'EventRowLock.php';
}

if (\is_file($pr5aHelperDir . 'EventStatusTransition.php'))
{
	require_once $pr5aHelperDir . 'EventStatusTransition.php';
}

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderMailer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderPlanner.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RenewalReminderOptOut.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventCancelTransition.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/EventCancelNoticeService.php';

if (!\defined('JPATH_ROOT'))
{
	\define('JPATH_ROOT', dirname(__DIR__));
}

/**
 * Behavioural locks for PR5a: status guards, cancelled attendance, duplicates, row lock order.
 *
 * @since  1.0.0
 */
final class EventRowLockAttendanceTest extends TestCase
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
	private function requirePr5aHelpers(): void
	{
		$this->assertTrue(
			\class_exists(DuplicateKey::class, false),
			'DuplicateKey helper must exist'
		);
		$this->assertTrue(
			\class_exists(EventRowLock::class, false),
			'EventRowLock helper must exist'
		);
		$this->assertTrue(
			\class_exists(EventStatusTransition::class, false),
			'EventStatusTransition helper must exist'
		);
	}

	/**
	 * Completing a Cancelled event leaves status 3 and completed_by unchanged.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteOnCancelledEventLeavesStatusAndCompletedBy(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'             => 4,
			'status'         => 3,
			'completed_by'   => 99,
			'completed_date' => '2026-01-01 00:00:00',
		];

		$admin->complete(4, 7);

		$this->assertSame(3, (int) $admin->events[4]->status);
		$this->assertSame(99, (int) $admin->events[4]->completed_by);
		$this->assertSame('2026-01-01 00:00:00', $admin->events[4]->completed_date);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
	}

	/**
	 * Completing an already-Completed event leaves completed_date unchanged.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteOnCompletedEventLeavesCompletedDate(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'             => 4,
			'status'         => 2,
			'completed_by'   => 3,
			'completed_date' => '2026-02-02 12:00:00',
		];

		$admin->complete(4, 7);

		$this->assertSame(2, (int) $admin->events[4]->status);
		$this->assertSame('2026-02-02 12:00:00', $admin->events[4]->completed_date);
		$this->assertSame(3, (int) $admin->events[4]->completed_by);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
	}

	/**
	 * RC complete on a Cancelled event is refused by the pre-check.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCompleteOnCancelledReturnsEventClosed(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 3,
		];
		$rc->forceZeroAffected = true;

		$this->assertSame('event_closed', $rc->completeEvent(4, 7));
		$this->assertSame(3, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC setEventStatus with the same allowed status is a successful no-op.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcSetEventStatusSameStatusIsSuccessfulNoOp(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 4,
		];

		$this->assertTrue($rc->setEventStatus(4, 4));
		$this->assertSame(4, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC setEventStatus on a Cancelled event returns event_closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcSetEventStatusOnCancelledReturnsEventClosed(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 3,
		];

		$this->assertSame('event_closed', $rc->setEventStatus(4, 4));
		$this->assertSame(3, (int) $rc->events[4]->status);
	}

	/**
	 * RC setEventStatus changes Scheduled to Confirmed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcSetEventStatusChangesAllowedStatus(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];

		$this->assertTrue($rc->setEventStatus(4, 4));
		$this->assertSame(4, (int) $rc->events[4]->status);
		$this->assertSame(1, $rc->updateCount);
	}

	/**
	 * RC setEventStatus with 0 affected rows and a closed re-read returns event_closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcSetEventStatusZeroAffectedClosedRereadReturnsEventClosed(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->forceZeroAffected = true;
		$rc->zeroAffectedRereadStatus = 2;

		$this->assertSame('event_closed', $rc->setEventStatus(4, 4));
		$this->assertSame(2, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC setEventStatus with 0 affected rows still open for a different target fails.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcSetEventStatusZeroAffectedStillOpenReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->forceZeroAffected = true;

		$this->assertSame('db_error', $rc->setEventStatus(4, 4));
		$this->assertSame(1, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC complete with 0 affected rows and re-read already Completed is a no-op success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCompleteZeroAffectedTargetRereadIsSuccessfulNoOp(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->forceZeroAffected = true;
		$rc->zeroAffectedRereadStatus = 2;

		$this->assertTrue($rc->completeEvent(4, 7));
		$this->assertSame(2, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC complete with 0 affected rows while still open fails without side effects.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCompleteZeroAffectedStillOpenReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->forceZeroAffected = true;

		$this->assertSame('db_error', $rc->completeEvent(4, 7));
		$this->assertSame(1, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC complete with 0 affected rows and a closed non-target re-read returns event_closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCompleteZeroAffectedClosedRereadReturnsEventClosed(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->forceZeroAffected = true;
		$rc->zeroAffectedRereadStatus = 3;

		$this->assertSame('event_closed', $rc->completeEvent(4, 7));
		$this->assertSame(3, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC complete changes an allowed status to Completed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCompleteChangesAllowedStatus(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];

		$this->assertTrue($rc->completeEvent(4, 7));
		$this->assertSame(2, (int) $rc->events[4]->status);
		$this->assertSame(1, $rc->updateCount);
	}

	/**
	 * RC cancel with 0 affected rows and re-read already Cancelled is a no-op success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelZeroAffectedTargetRereadIsSuccessfulNoOp(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->attendanceCount[4] = 0;
		$rc->forceZeroAffected = true;
		$rc->zeroAffectedRereadStatus = 3;

		$this->assertTrue($rc->cancelEvent(4));
		$this->assertSame(3, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC cancel with 0 affected rows while still open fails without side effects.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelZeroAffectedStillOpenReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->attendanceCount[4] = 0;
		$rc->forceZeroAffected = true;

		$this->assertSame('db_error', $rc->cancelEvent(4));
		$this->assertSame(1, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC cancel with 0 affected rows and a Completed re-read returns event_closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelZeroAffectedClosedRereadReturnsEventClosed(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->attendanceCount[4] = 0;
		$rc->forceZeroAffected = true;
		$rc->zeroAffectedRereadStatus = 2;

		$this->assertSame('event_closed', $rc->cancelEvent(4));
		$this->assertSame(2, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * Admin complete with 0 affected rows and re-read already Completed is a no-op success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteZeroAffectedTargetRereadIsSuccessfulNoOp(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$admin->forceZeroAffected = true;
		$admin->zeroAffectedRereadStatus = 2;

		$admin->complete(4, 7);

		$this->assertSame(2, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ERROR_OPERATION_FAILED', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
	}

	/**
	 * Admin complete with 0 affected rows while still open fails without side effects.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteZeroAffectedStillOpenReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$admin->forceZeroAffected = true;

		$admin->complete(4, 7);

		$this->assertSame(1, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ERROR_OPERATION_FAILED', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
	}

	/**
	 * Admin complete with 0 affected rows and a Cancelled re-read returns already closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteZeroAffectedClosedRereadReturnsAlreadyClosed(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$admin->forceZeroAffected = true;
		$admin->zeroAffectedRereadStatus = 3;

		$admin->complete(4, 7);

		$this->assertSame(3, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS', $admin->notices);
	}

	/**
	 * Admin complete changes an allowed status to Completed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteChangesAllowedStatus(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 4,
		];

		$admin->complete(4, 7);

		$this->assertSame(2, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS', $admin->notices);
	}

	/**
	 * Admin cancel with 0 affected rows and re-read already Cancelled is a no-op success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelZeroAffectedTargetRereadIsSuccessfulNoOp(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 5,
		];
		$admin->attendanceCount[4] = 0;
		$admin->forceZeroAffected = true;
		$admin->zeroAffectedRereadStatus = 3;

		$admin->cancel(4);

		$this->assertSame(3, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ERROR_OPERATION_FAILED', $admin->notices);
		$this->assertSame(0, $admin->cancelNoticeCount);
	}

	/**
	 * Admin cancel with 0 affected rows while still open fails without side effects.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelZeroAffectedStillOpenReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 5,
		];
		$admin->attendanceCount[4] = 0;
		$admin->forceZeroAffected = true;

		$admin->cancel(4);

		$this->assertSame(5, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ERROR_OPERATION_FAILED', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
	}

	/**
	 * Admin cancel with 0 affected rows and a Completed re-read returns already closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelZeroAffectedClosedRereadReturnsAlreadyClosed(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 5,
		];
		$admin->attendanceCount[4] = 0;
		$admin->forceZeroAffected = true;
		$admin->zeroAffectedRereadStatus = 2;

		$admin->cancel(4);

		$this->assertSame(2, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
		$this->assertNotContains('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS', $admin->notices);
	}

	/**
	 * Admin cancel on a Completed event stays closed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelOnCompletedReturnsAlreadyClosed(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 2,
		];

		$admin->cancel(4);

		$this->assertSame(2, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED', $admin->notices);
		$this->assertSame(0, $admin->cancelNoticeCount);
	}

	/**
	 * Refused cancel with check-ins does not dispatch a cancel notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelWithCheckinsSendsNoCancelNotice(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$admin->attendanceCount[4] = 2;

		$admin->cancel(4);

		$this->assertSame(1, (int) $admin->events[4]->status);
		$this->assertContains('COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS', $admin->notices);
		$this->assertSame(0, $admin->cancelNoticeCount);
	}

	/**
	 * Already-Cancelled no-op success does not dispatch a cancel notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelNoOpSendsNoCancelNotice(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 5,
		];
		$admin->attendanceCount[4] = 0;
		$admin->forceZeroAffected = true;
		$admin->zeroAffectedRereadStatus = 3;

		$admin->cancel(4);

		$this->assertContains('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS', $admin->notices);
		$this->assertSame(0, $admin->cancelNoticeCount);
	}

	/**
	 * Rolled-back cancel (zero affected, still open) does not dispatch a notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCancelRollbackSendsNoCancelNotice(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 5,
		];
		$admin->attendanceCount[4] = 0;
		$admin->forceZeroAffected = true;

		$admin->cancel(4);

		$this->assertContains('COM_WENDFOLD_ERROR_OPERATION_FAILED', $admin->notices);
		$this->assertSame(0, $admin->cancelNoticeCount);
	}

	/**
	 * A real admin cancel dispatches exactly one cancel notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminRealCancelSendsOneCancelNotice(): void
	{
		$this->requirePr5aHelpers();
		$admin = new AdminEventStatusDouble;
		$admin->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$admin->attendanceCount[4] = 0;

		$admin->cancel(4);
		$admin->cancel(4);

		$this->assertSame(3, (int) $admin->events[4]->status);
		$this->assertSame(1, $admin->cancelNoticeCount);
	}

	/**
	 * RC refused / no-op / rollback cancel paths send no notice; a real cancel sends once.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelNoticeOnlyOnRealTransition(): void
	{
		$this->requirePr5aHelpers();

		$refused = new RcEventStatusDouble;
		$refused->events[4] = (object) ['id' => 4, 'status' => 1];
		$refused->attendanceCount[4] = 1;
		$this->assertSame('has_checkins', $refused->cancelEvent(4));
		$this->assertSame(0, $refused->cancelNoticeCount);

		$noop = new RcEventStatusDouble;
		$noop->events[4] = (object) ['id' => 4, 'status' => 5];
		$noop->attendanceCount[4] = 0;
		$noop->forceZeroAffected = true;
		$noop->zeroAffectedRereadStatus = 3;
		$this->assertTrue($noop->cancelEvent(4));
		$this->assertSame(0, $noop->cancelNoticeCount);

		$rolled = new RcEventStatusDouble;
		$rolled->events[4] = (object) ['id' => 4, 'status' => 5];
		$rolled->attendanceCount[4] = 0;
		$rolled->forceZeroAffected = true;
		$this->assertSame('db_error', $rolled->cancelEvent(4));
		$this->assertSame(0, $rolled->cancelNoticeCount);

		$real = new RcEventStatusDouble;
		$real->events[4] = (object) ['id' => 4, 'status' => 4];
		$real->attendanceCount[4] = 0;
		$this->assertTrue($real->cancelEvent(4));
		$this->assertSame('event_closed', $real->cancelEvent(4));
		$this->assertSame(1, $real->cancelNoticeCount);
	}

	/**
	 * EventStatusTransition::resolve maps each decision branch.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventStatusTransitionResolveDecisionBranches(): void
	{
		$this->requirePr5aHelpers();
		$db = new LockRecordingDatabase;
		$db->eventRows[4] = (object) [
			'id'         => 4,
			'published'  => 1,
			'status'     => 4,
			'event_date' => '2026-07-01',
		];
		$db->transactionStart(true);
		EventRowLock::lock($db, 4);

		$this->assertSame(
			EventStatusTransition::OK,
			EventStatusTransition::resolve($db, 4, 1, 2, [1, 4])
		);
		$this->assertSame(
			EventStatusTransition::OK,
			EventStatusTransition::resolve($db, 4, 0, 4, [1, 4, 5])
		);
		$this->assertSame(
			EventStatusTransition::DB_ERROR,
			EventStatusTransition::resolve($db, 4, 0, 2, [1, 4])
		);
		$this->assertSame(
			EventStatusTransition::EVENT_CLOSED,
			EventStatusTransition::resolve($db, 4, 0, 2, [1, 5])
		);

		$db->eventRows[4] = null;
		$this->assertSame(
			EventStatusTransition::EVENT_CLOSED,
			EventStatusTransition::resolve($db, 4, 0, 2, [1, 4])
		);
	}

	/**
	 * RC cancel on a Completed event is refused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelOnCompletedEventIsRefused(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 2,
		];

		$this->assertSame('event_closed', $rc->cancelEvent(4));
		$this->assertSame(2, (int) $rc->events[4]->status);
	}

	/**
	 * RC cancel on an active event with one attendance row is refused with no UPDATE.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelWithAttendanceIsRefusedAndDoesNotUpdate(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->attendanceCount[4] = 1;

		$this->assertSame('has_checkins', $rc->cancelEvent(4));
		$this->assertSame(1, (int) $rc->events[4]->status);
		$this->assertSame(0, $rc->updateCount);
	}

	/**
	 * RC cancel on an active event with no attendance sets status 3.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcCancelWithoutAttendanceSetsStatusCancelled(): void
	{
		$this->requirePr5aHelpers();
		$rc = new RcEventStatusDouble;
		$rc->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$rc->attendanceCount[4] = 0;

		$this->assertTrue($rc->cancelEvent(4));
		$this->assertSame(3, (int) $rc->events[4]->status);
		$this->assertSame(1, $rc->updateCount);
	}

	/**
	 * QR check-in that hits a duplicate key returns already, not db_error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testQrDuplicateKeyCheckInReturnsAlready(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('qr', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 1,
		];
		$path->throwDuplicateOnInsert = true;

		$result = $path->add(4, 9);

		$this->assertSame('already', $result);
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * RC add duplicate key returns already only when the attendance row exists.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcDuplicateKeyAddReturnsAlreadyWhenAttendanceRowExists(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('rc', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 1,
		];
		$path->rows[1] = [
			'id'         => 1,
			'event_id'   => 4,
			'profile_id' => 9,
		];
		$path->throwDuplicateOnInsert = true;

		$result = $path->add(4, 9);

		$this->assertSame('already', $result);
		$this->assertSame([], $store->entries);
	}

	/**
	 * RC add duplicate key without an attendance row returns db_error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcDuplicateKeyAddWithoutAttendanceRowReturnsDbError(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('rc', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 1,
		];
		$path->throwDuplicateOnInsert = true;

		$result = $path->add(4, 9);

		$this->assertSame('db_error', $result);
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * RC addMember re-reads attendance after a duplicate-key rollback.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcAddMemberDuplicateKeyReReadsAttendanceAfterRollback(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php'
		);
		$start = strpos($source, 'public function addMember(int $eventId, int $profileId, int $checkedInBy)');
		$this->assertNotFalse($start);
		$end = strpos($source, 'public function removeMember(int $attendanceId, int $eventId)', $start);
		$this->assertNotFalse($end);
		$body = substr($source, $start, $end - $start);

		$this->assertStringContainsString('if (DuplicateKey::is($e))', $body);
		$this->assertStringContainsString("#__wendfold_attendance", $body);
		$this->assertMatchesRegularExpression(
			'/DuplicateKey::is\(\$e\)[\s\S]*loadResult\(\)[\s\S]*return \'already\'[\s\S]*return \'db_error\'/',
			$body
		);
	}

	/**
	 * Meeting add that hits a duplicate key returns already, not db_error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingDuplicateKeyAddReturnsAlready(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('meeting', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 1,
		];
		$path->throwDuplicateOnInsert = true;

		$result = $path->add(4, 9);

		$this->assertSame('already', $result);
		$this->assertSame([], $store->entries);
	}

	/**
	 * Admin add on a Cancelled event is refused with no attendance or entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminAddOnCancelledEventCreatesNothing(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('admin', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 3,
		];

		$this->assertSame('cancelled', $path->add(4, 9));
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * RC add on a Cancelled event is refused with no attendance or entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcAddOnCancelledEventCreatesNothing(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('rc', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 3,
		];

		$this->assertSame('cancelled', $path->add(4, 9));
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * Meeting add on a Cancelled event is refused with no attendance or entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingAddOnCancelledEventCreatesNothing(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('meeting', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 3,
		];

		$this->assertSame('cancelled', $path->add(4, 9));
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * QR check-in on a Cancelled event is refused with no attendance or entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testQrAddOnCancelledEventCreatesNothing(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('qr', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 3,
		];

		$this->assertSame('cancelled', $path->add(4, 9));
		$this->assertSame([], $path->rows);
		$this->assertSame([], $store->entries);
	}

	/**
	 * Admin remove on a Cancelled event is refused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminRemoveOnCancelledEventIsRefused(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('admin', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 3,
		];
		$path->rows[10] = ['id' => 10, 'event_id' => 4, 'profile_id' => 9];

		$this->assertSame('cancelled', $path->remove(10, 4));
		$this->assertArrayHasKey(10, $path->rows);
	}

	/**
	 * Each add path locks the event row inside the transaction before the insert.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAddPathsLockEventRowBeforeInsert(): void
	{
		$this->requirePr5aHelpers();

		foreach (['admin', 'rc', 'meeting', 'qr'] as $kind)
		{
			$store = $this->metricStore(4);
			$path = new AttendanceWritePathDouble($kind, $store);
			$path->events[4] = (object) [
				'id'        => 4,
				'published' => 1,
				'status'    => 1,
			];

			$this->assertTrue($path->add(4, 9), $kind);
			$this->assertLockPrecedesChildWrite($path->statements, $kind);
		}
	}

	/**
	 * Publish locks the event row inside the transaction before any further work.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishLocksEventRowBeforeChildWrites(): void
	{
		$this->requirePr5aHelpers();
		$model = $this->lockingLifecycleModel(4);
		$pks = [4];
		$this->assertTrue($model->publish($pks, 0));

		$txn = null;
		$lock = null;

		foreach ($model->database->statements as $index => $statement)
		{
			if ($txn === null && $statement === 'transactionStart')
			{
				$txn = $index;
			}

			if ($lock === null && str_contains($statement, 'FOR UPDATE'))
			{
				$lock = $index;
			}
		}

		$this->assertNotNull($txn, 'publish missing transactionStart');
		$this->assertNotNull($lock, 'publish missing FOR UPDATE');
		$this->assertLessThan($lock, $txn, 'publish');
	}

	/**
	 * Delete locks the event row inside the transaction before child deletes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteLocksEventRowBeforeChildWrites(): void
	{
		$this->requirePr5aHelpers();
		$model = $this->lockingLifecycleModel(4);
		$model->database->seedChild('attendance', 4);
		$pks = [4];
		$this->assertTrue($model->delete($pks));
		$this->assertLockPrecedesChildWrite($model->database->statements, 'delete');
	}

	/**
	 * An attendance entry on a Cancelled event counts 0 credits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCancelledEventAttendanceCountsZeroCredits(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$store->eventStatuses[4] = 3;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertSame([0, 0], array_column($store->entries, 'published'));
		$this->assertSame(0.0, $this->countedTotal($store->entries));
	}

	/**
	 * The same attendance on a Completed published event still counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCompletedEventAttendanceStillCounts(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$store->eventStatuses[4] = 2;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(34.0, $this->countedTotal($store->entries));
	}

	/**
	 * The same attendance on an Active published event still counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testActiveEventAttendanceStillCounts(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$store->eventStatuses[4] = 1;
		$service = new MetricService($store, 7);

		$service->creditAttendance(4, 10);

		$this->assertSame([1, 1], array_column($store->entries, 'published'));
		$this->assertSame(34.0, $this->countedTotal($store->entries));
	}

	/**
	 * When the parent event save fails, inline location inserts are rolled back.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFailedEventSaveRollsBackInlineLocations(): void
	{
		$this->requirePr5aHelpers();
		$jform = [
			'title'               => 'Location rollback',
			'event_type_id'       => 1,
			'event_date'          => '2026-07-01',
			'status'              => 1,
			'published'           => 1,
			'audience'            => 1,
			'allow_rsvp'          => 1,
			'workflow_mode'       => 2,
			'save_ksu_location'   => 1,
			'ksu_location_custom' => 'PR5a Lock KSU',
			'ksu_address'         => '1 Main',
			'ksu_city'            => 'Town',
			'ksu_state'           => 'TX',
			'ksu_zip'             => '75001',
			'metric_credits'      => ['1' => '1'],
		];
		Factory::$application = new EventApplication($jform);

		$model = new InlineLocationEventModel;
		$model->database = new InlineLocationEventDatabase($model);

		$result = $model->save($jform);

		$this->assertFalse($result);
		$this->assertSame([], $model->storedData);
		$this->assertSame([], $model->database->locations);
	}

	/**
	 * Admin event form save refuses Cancelled when the event has check-ins.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventFormSaveRefusesCancelWhenAttendanceExists(): void
	{
		$this->requirePr5aHelpers();
		$jform = $this->cancelCheckinsJform(3);
		Factory::$application = new EventApplication($jform);

		$model = new CancelCheckinsEventModel;
		$model->database = new CancelCheckinsEventDatabase($model);
		$model->database->eventRows[15] = (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
		];
		$model->database->attendanceCount[15] = 1;

		$result = $model->save($jform);

		$this->assertFalse($result);
		$this->assertSame('COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS', $model->getError());
		$this->assertSame([], $model->storedData);
		$this->assertSame(1, (int) $model->database->eventRows[15]->status);
		$this->assertSame(0, $model->cancelNoticeCount);
	}

	/**
	 * Admin event form may cancel when there are no attendance rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventFormSaveAllowsCancelWithoutAttendance(): void
	{
		$this->requirePr5aHelpers();
		$jform = $this->cancelCheckinsJform(3);
		$jform['title'] = 'Cancel empty roster';
		Factory::$application = new EventApplication($jform);

		$model = new CancelCheckinsEventModel;
		$model->database = new CancelCheckinsEventDatabase($model);
		$model->database->eventRows[15] = (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
		];
		$model->database->attendanceCount[15] = 0;

		$this->assertTrue($model->save($jform));
		$this->assertSame(3, (int) $model->storedData['status']);
		$this->assertSame(3, (int) $model->database->eventRows[15]->status);
		$this->assertSame(1, $model->cancelNoticeCount);

		$this->assertTrue($model->save($jform));
		$this->assertSame(1, $model->cancelNoticeCount);
	}

	/**
	 * Edit-save that posts Cancelled but fails to persist status sends no notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventFormSaveSendsNoCancelNoticeWhenStatusPersistFails(): void
	{
		$this->requirePr5aHelpers();
		$jform = $this->cancelCheckinsJform(3);
		$jform['title'] = 'Cancel persist failed';
		Factory::$application = new EventApplication($jform);

		$model = new CancelCheckinsEventModel;
		$model->database = new CancelCheckinsEventDatabase($model);
		$model->database->persistStatus = false;
		$model->database->eventRows[15] = (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
		];
		$model->database->attendanceCount[15] = 0;

		$this->assertTrue($model->save($jform));
		$this->assertSame(3, (int) $model->storedData['status']);
		$this->assertSame(1, (int) $model->database->eventRows[15]->status);
		$this->assertSame(0, $model->cancelNoticeCount);
	}

	/**
	 * Re-saving an already-Cancelled event with check-ins does not refuse.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventFormSaveAllowsAlreadyCancelledWithAttendance(): void
	{
		$this->requirePr5aHelpers();
		$jform = $this->cancelCheckinsJform(3);
		$jform['title'] = 'Already cancelled';
		Factory::$application = new EventApplication($jform);

		$model = new CancelCheckinsEventModel;
		$model->database = new CancelCheckinsEventDatabase($model);
		$model->database->eventRows[15] = (object) [
			'id'         => 15,
			'published'  => 1,
			'status'     => 3,
			'event_date' => '2026-07-01',
		];
		$model->database->attendanceCount[15] = 1;

		$this->assertTrue($model->save($jform));
		$this->assertSame(3, (int) $model->storedData['status']);
		$this->assertSame(0, $model->cancelNoticeCount);
	}

	/**
	 * Meeting add on a Completed event creates the attendance row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingAddOnCompletedEventSucceeds(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('meeting', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 2,
		];

		$this->assertTrue($path->add(4, 9));
		$this->assertCount(1, $path->rows);
		$this->assertNotSame([], $store->entries);
	}

	/**
	 * Meeting remove on a Completed event deletes the attendance row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingRemoveOnCompletedEventSucceeds(): void
	{
		$this->requirePr5aHelpers();
		$store = $this->metricStore(4);
		$path = new AttendanceWritePathDouble('meeting', $store);
		$path->events[4] = (object) [
			'id'        => 4,
			'published' => 1,
			'status'    => 2,
		];
		$path->rows[10] = ['id' => 10, 'event_id' => 4, 'profile_id' => 9];

		$this->assertTrue($path->remove(10, 4));
		$this->assertArrayNotHasKey(10, $path->rows);
	}

	/**
	 * Meeting production writers allow Completed and refuse only Cancelled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingAttendanceProductionAllowsCompletedStatus(): void
	{
		$this->requirePr5aHelpers();
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/MeetingattendanceModel.php'
		);
		$this->assertNotFalse($source);
		$this->assertStringContainsString('[1, 2, 4, 5]', $source);
		$this->assertStringNotContainsString(
			'!\\in_array((int) $locked->status, [1, 4, 5], true)',
			$source
		);
		$this->assertMatchesRegularExpression(
			'/function isEventOpen\(int \$eventId\): bool.*?\[1, 2, 4, 5\]/s',
			$source
		);
	}

	/**
	 * RC complete/cancel/setstatus use a distinct already-closed message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcStatusChangeClosedMessageIsDistinctFromCheckinClosed(): void
	{
		$this->requirePr5aHelpers();
		$controller = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Controller/RcattendanceController.php'
		);
		$ini = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);
		$this->assertNotFalse($controller);
		$this->assertNotFalse($ini);
		$this->assertStringContainsString(
			'COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED="This event is already completed or cancelled"',
			$ini
		);
		$this->assertSame(
			1,
			substr_count($ini, 'COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED=')
		);
		$this->assertMatchesRegularExpression(
			'/function complete\(\): void.*?event_closed.*?COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED/s',
			$controller
		);
		$this->assertMatchesRegularExpression(
			'/function cancel\(\): void.*?event_closed.*?COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED/s',
			$controller
		);
		$this->assertMatchesRegularExpression(
			'/function setstatus\(\): void.*?event_closed.*?COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED/s',
			$controller
		);
		$this->assertMatchesRegularExpression(
			'/function add\(\): void.*?event_closed.*?COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED/s',
			$controller
		);
		$this->assertMatchesRegularExpression(
			'/function remove\(\): void.*?event_closed.*?COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED/s',
			$controller
		);

		if (!preg_match('/function complete\(\): void(.*?)function cancel\(\): void/s', $controller, $completeMatch))
		{
			$this->fail('complete() method not found');
		}

		$this->assertStringNotContainsString(
			'COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED',
			$completeMatch[1],
			'complete() must not reuse the check-in closed message'
		);

		if (!preg_match('/function cancel\(\): void(.*)$/s', $controller, $cancelMatch))
		{
			$this->fail('cancel() method not found');
		}

		$this->assertStringNotContainsString(
			'COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED',
			$cancelMatch[1],
			'cancel() must not reuse the check-in closed message'
		);
	}

	/**
	 * DuplicateKey recognises MySQL 1062 and SQLSTATE 23000 duplicate entry.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDuplicateKeyDetectsMysqlAndSqlstateDuplicates(): void
	{
		$this->requirePr5aHelpers();
		$this->assertTrue(DuplicateKey::is(new \RuntimeException('Duplicate entry', 1062)));
		$this->assertTrue(DuplicateKey::is(new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry', 23000)));
		$this->assertFalse(DuplicateKey::is(new \RuntimeException('deadlock', 1213)));
	}

	/**
	 * EventRowLock selects the event for update and returns null when missing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventRowLockSelectsForUpdateAndReturnsNullWhenMissing(): void
	{
		$this->requirePr5aHelpers();
		$db = new LockRecordingDatabase;
		$db->eventRows[4] = (object) [
			'id'         => 4,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
		];

		$db->transactionStart(true);
		$row = EventRowLock::lock($db, 4);
		$this->assertNotNull($row);
		$this->assertSame(4, (int) $row->id);
		$this->assertTrue($db->sawForUpdate);
		$this->assertNull(EventRowLock::lock($db, 99));
	}

	/**
	 * Production attendance and event writers call EventRowLock inside a transaction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testProductionWritersCallEventRowLock(): void
	{
		$this->requirePr5aHelpers();
		$paths = [
			'com_wendfold/admin/src/Controller/AttendanceController.php',
			'com_wendfold/site/src/Model/CheckinModel.php',
			'com_wendfold/site/src/Model/RcattendanceModel.php',
			'com_wendfold/site/src/Model/MeetingattendanceModel.php',
			'com_wendfold/admin/src/Model/EventModel.php',
		];

		foreach ($paths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			$this->assertNotFalse($source, $relative);
			$this->assertStringContainsString('EventRowLock::lock', $source, $relative);
		}
	}

	/**
	 * Admin complete and cancel use conditional UPDATEs and fixed log labels.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminCompleteAndCancelUseConditionalUpdatesAndFixedLogs(): void
	{
		$this->requirePr5aHelpers();
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/AttendanceController.php'
		);
		$this->assertNotFalse($source);
		$this->assertStringContainsString("' IN (1,4)'", $source);
		$this->assertStringContainsString("' IN (1,4,5)'", $source);
		$this->assertStringContainsString('Admin attendance complete failed', $source);
		$this->assertStringContainsString('Admin attendance cancel failed', $source);
		$this->assertStringContainsString('COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS', $source);
		$this->assertStringContainsString('DuplicateKey::is', $source);
		$this->assertStringContainsString('EventStatusTransition::resolve', $source);
		$this->assertStringContainsString('EventStatusTransition::DB_ERROR', $source);
	}

	/**
	 * Locked attendance writers defer awards and flush only after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLockedAttendanceWritersFlushAwardsAfterCommit(): void
	{
		$this->requirePr5aHelpers();
		$paths = [
			'com_wendfold/admin/src/Controller/AttendanceController.php',
			'com_wendfold/site/src/Model/CheckinModel.php',
			'com_wendfold/site/src/Model/RcattendanceModel.php',
			'com_wendfold/site/src/Model/MeetingattendanceModel.php',
			'com_wendfold/admin/src/Model/EventModel.php',
		];

		foreach ($paths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			$this->assertNotFalse($source, $relative);
			$this->assertStringContainsString('EventRowLock::lock', $source, $relative);
			$this->assertStringContainsString('deferAwardRecompute(true)', $source, $relative);
			$this->assertStringContainsString('flushAwardRecompute()', $source, $relative);
			$this->assertStringContainsString('discardDeferredAwardRecompute()', $source, $relative);
		}

		$event = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php'
		);
		$this->assertNotFalse($event);
		$this->assertMatchesRegularExpression(
			'/EventRowLock::lock.*?transactionCommit\(true\);\s+\$service->flushAwardRecompute\(\);/s',
			$event
		);
	}

	/**
	 * Posted event form fields for cancel-with-check-ins save tests.
	 *
	 * @param   integer  $status  Target status.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	private function cancelCheckinsJform(int $status): array
	{
		return [
			'id'             => 15,
			'title'          => 'Cancel with check-in',
			'event_type_id'  => 1,
			'event_date'     => '2026-07-01',
			'status'         => $status,
			'published'      => 1,
			'audience'       => 1,
			'allow_rsvp'     => 1,
			'workflow_mode'  => 2,
			'metric_credits' => ['1' => '1'],
			'gpx_file'       => 'media/com_wendfold/gpx/keep.gpx',
		];
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  MemoryMetricEntryStore
	 *
	 * @since   1.0.0
	 */
	private function metricStore(int $eventId): MemoryMetricEntryStore
	{
		$store = new MemoryMetricEntryStore;
		$store->eventDates[$eventId] = '2026-06-15';
		$store->eventStates[$eventId] = 1;
		$store->eventStatuses[$eventId] = 1;
		$store->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 9,
			'event_id'   => $eventId,
			'event_date' => '2026-06-15',
		];
		$store->credits[$eventId] = [
			(object) ['metric_id' => 1, 'value' => '33.000'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];

		return $store;
	}

	/**
	 * @param   list<string>  $statements  Recorded statements.
	 * @param   string        $label       Path label for assertion messages.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertLockPrecedesChildWrite(array $statements, string $label): void
	{
		$txn = null;
		$lock = null;
		$child = null;

		foreach ($statements as $index => $statement)
		{
			if ($txn === null && $statement === 'transactionStart')
			{
				$txn = $index;
			}

			if ($lock === null && str_contains($statement, 'FOR UPDATE'))
			{
				$lock = $index;
			}

			if ($child === null
				&& (str_starts_with($statement, 'INSERT')
				|| str_starts_with($statement, 'UPDATE')
				|| str_starts_with($statement, 'DELETE'))
			)
			{
				$child = $index;
			}
		}

		$this->assertNotNull($txn, $label . ' missing transactionStart');
		$this->assertNotNull($lock, $label . ' missing FOR UPDATE');
		$this->assertNotNull($child, $label . ' missing child write');
		$this->assertLessThan($lock, $txn, $label);
		$this->assertLessThan($child, $lock, $label);
	}

	/**
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

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  LockingLifecycleEventModel
	 *
	 * @since   1.0.0
	 */
	private function lockingLifecycleModel(int $eventId): LockingLifecycleEventModel
	{
		Factory::$application = new EventApplication([]);
		$model = new LockingLifecycleEventModel;
		$model->store = new MemoryMetricEntryStore;
		$model->store->eventDates[$eventId] = '2026-06-15';
		$model->store->eventStates[$eventId] = 1;
		$model->store->eventStatuses[$eventId] = 1;
		$model->store->credits[$eventId] = [
			(object) ['metric_id' => 1, 'value' => '10.000'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];
		$model->store->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 9,
			'event_id'   => $eventId,
			'event_date' => '2026-06-15',
		];
		$model->database = new LockingLifecycleEventDatabase($model);
		$model->database->eventStates[$eventId] = 1;
		$model->database->eventRows[$eventId] = (object) [
			'id'         => $eventId,
			'title'      => 'Lock event',
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-06-15',
		];

		return $model;
	}
}

/**
 * Admin complete/cancel sequence with conditional updates and cancel guards.
 *
 * @since  1.0.0
 */
final class AdminEventStatusDouble
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * Enqueued notice language keys.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $notices = [];

	/**
	 * Attendance counts keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $attendanceCount = [];

	/**
	 * When true, the conditional UPDATE pretends to affect 0 rows.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $forceZeroAffected = false;

	/**
	 * Status left on the row after a zero-affected UPDATE, when set.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	public ?int $zeroAffectedRereadStatus = null;

	/**
	 * How many cancel notices the production gate would dispatch.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $cancelNoticeCount = 0;

	/**
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $userId   Completing user id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function complete(int $eventId, int $userId): void
	{
		$event = $this->events[$eventId] ?? null;
		$allowed = [1, 4];

		if ($event === null || !\in_array((int) $event->status, $allowed, true))
		{
			$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED';

			return;
		}

		$affected = 1;

		if ($this->forceZeroAffected)
		{
			$affected = 0;

			if ($this->zeroAffectedRereadStatus !== null)
			{
				$event->status = $this->zeroAffectedRereadStatus;
			}
		}
		else
		{
			$event->status = 2;
			// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- mirrors event columns
			$event->completed_by = $userId;
			$event->completed_date = '2026-06-15 12:00:00';
			// phpcs:enable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		}

		$outcome = $this->resolveOutcome($eventId, $affected, 2, $allowed);

		if ($outcome === EventStatusTransition::OK)
		{
			$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS';

			return;
		}

		if ($outcome === EventStatusTransition::DB_ERROR)
		{
			$this->notices[] = 'COM_WENDFOLD_ERROR_OPERATION_FAILED';

			return;
		}

		$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED';
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function cancel(int $eventId): void
	{
		$event = $this->events[$eventId] ?? null;
		$allowed = [1, 4, 5];

		if ($event === null || !\in_array((int) $event->status, $allowed, true))
		{
			$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED';

			return;
		}

		if (($this->attendanceCount[$eventId] ?? 0) > 0)
		{
			$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS';

			return;
		}

		$affected = 1;

		if ($this->forceZeroAffected)
		{
			$affected = 0;

			if ($this->zeroAffectedRereadStatus !== null)
			{
				$event->status = $this->zeroAffectedRereadStatus;
			}
		}
		else
		{
			$event->status = 3;
		}

		$outcome = $this->resolveOutcome($eventId, $affected, 3, $allowed);

		if ($outcome === EventStatusTransition::OK)
		{
			$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS';

			if (EventCancelNoticeService::shouldNotify($affected, $outcome))
			{
				$this->cancelNoticeCount++;
			}

			return;
		}

		if ($outcome === EventStatusTransition::DB_ERROR)
		{
			$this->notices[] = 'COM_WENDFOLD_ERROR_OPERATION_FAILED';

			return;
		}

		$this->notices[] = 'COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED';
	}

	/**
	 * @param   integer    $eventId   Event id.
	 * @param   integer    $affected  Affected-row count.
	 * @param   integer    $target    Target status.
	 * @param   integer[]  $allowed   Allowed source statuses.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function resolveOutcome(int $eventId, int $affected, int $target, array $allowed): string
	{
		$db = new LockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		$db->transactionStart(true);
		EventRowLock::lock($db, $eventId);

		return EventStatusTransition::resolve($db, $eventId, $affected, $target, $allowed);
	}
}

/**
 * RC status transitions with conditional updates and cancel guards.
 *
 * @since  1.0.0
 */
final class RcEventStatusDouble
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * Attendance counts keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $attendanceCount = [];

	/**
	 * When true, the conditional UPDATE pretends to affect 0 rows.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $forceZeroAffected = false;

	/**
	 * Status left on the row after a zero-affected UPDATE, when set.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	public ?int $zeroAffectedRereadStatus = null;

	/**
	 * Number of status UPDATE attempts that mutated a row.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $updateCount = 0;

	/**
	 * How many cancel notices the production gate would dispatch.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $cancelNoticeCount = 0;

	/**
	 * @param   integer  $eventId            Event id.
	 * @param   integer  $completedByUserId  Completing user id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function completeEvent(int $eventId, int $completedByUserId): bool|string
	{
		$event = $this->events[$eventId] ?? null;
		$allowed = [1, 4];

		if ($event === null || !\in_array((int) $event->status, $allowed, true))
		{
			return 'event_closed';
		}

		$affected = 1;

		if ($this->forceZeroAffected)
		{
			$affected = 0;

			if ($this->zeroAffectedRereadStatus !== null)
			{
				$event->status = $this->zeroAffectedRereadStatus;
			}
		}
		else
		{
			$event->status = 2;
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- mirrors event columns
			$event->completed_by = $completedByUserId;
			$this->updateCount++;
		}

		return $this->mapOutcome(
			$this->resolveOutcome($eventId, $affected, 2, $allowed)
		);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $status   Target status.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function setEventStatus(int $eventId, int $status): bool|string
	{
		if (!\in_array($status, [1, 4, 5], true))
		{
			return 'invalid_status';
		}

		$event = $this->events[$eventId] ?? null;
		$allowed = [1, 4, 5];

		if ($event === null || !\in_array((int) $event->status, $allowed, true))
		{
			return 'event_closed';
		}

		$affected = 1;

		if ((int) $event->status === $status || $this->forceZeroAffected)
		{
			$affected = 0;

			if ($this->forceZeroAffected && $this->zeroAffectedRereadStatus !== null)
			{
				$event->status = $this->zeroAffectedRereadStatus;
			}
		}
		else
		{
			$event->status = $status;
			$this->updateCount++;
		}

		return $this->mapOutcome(
			$this->resolveOutcome($eventId, $affected, $status, $allowed)
		);
	}

	/**
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function cancelEvent(int $eventId): bool|string
	{
		$event = $this->events[$eventId] ?? null;
		$allowed = [1, 4, 5];

		if ($event === null || !\in_array((int) $event->status, $allowed, true))
		{
			return 'event_closed';
		}

		if (($this->attendanceCount[$eventId] ?? 0) > 0)
		{
			return 'has_checkins';
		}

		$affected = 1;

		if ($this->forceZeroAffected)
		{
			$affected = 0;

			if ($this->zeroAffectedRereadStatus !== null)
			{
				$event->status = $this->zeroAffectedRereadStatus;
			}
		}
		else
		{
			$event->status = 3;
			$this->updateCount++;
		}

		$outcome = $this->resolveOutcome($eventId, $affected, 3, $allowed);
		$result  = $this->mapOutcome($outcome);

		if ($result === true && EventCancelNoticeService::shouldNotify($affected, $outcome))
		{
			$this->cancelNoticeCount++;
		}

		return $result;
	}

	/**
	 * @param   integer    $eventId   Event id.
	 * @param   integer    $affected  Affected-row count.
	 * @param   integer    $target    Target status.
	 * @param   integer[]  $allowed   Allowed source statuses.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function resolveOutcome(int $eventId, int $affected, int $target, array $allowed): string
	{
		$db = new LockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		$db->transactionStart(true);
		EventRowLock::lock($db, $eventId);

		return EventStatusTransition::resolve($db, $eventId, $affected, $target, $allowed);
	}

	/**
	 * @param   string  $outcome  Helper outcome.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	private function mapOutcome(string $outcome): bool|string
	{
		if ($outcome === EventStatusTransition::OK)
		{
			return true;
		}

		if ($outcome === EventStatusTransition::DB_ERROR)
		{
			return 'db_error';
		}

		return 'event_closed';
	}
}

/**
 * Attendance add/remove path used by admin, RC, meeting, and QR sequences.
 *
 * @since  1.0.0
 */
final class AttendanceWritePathDouble
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * Attendance rows keyed by id.
	 *
	 * @var    array<int, array{id: int, event_id: int, profile_id: int}>
	 * @since  1.0.0
	 */
	public array $rows = [];

	/**
	 * Recorded statement labels in order.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * When true, insert throws a duplicate-key error.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $throwDuplicateOnInsert = false;

	/**
	 * Next attendance id.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $nextId = 1;

	/**
	 * @param   string                  $kind   admin, rc, meeting, or qr.
	 * @param   MemoryMetricEntryStore  $store  Metric store.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private string $kind,
		private MemoryMetricEntryStore $store
	)
	{
	}

	/**
	 * @param   integer  $eventId    Event id.
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function add(int $eventId, int $profileId): bool|string
	{
		$event = $this->events[$eventId] ?? null;

		if ($event === null)
		{
			return 'invalid';
		}

		if ((int) $event->published === -2)
		{
			return 'trashed';
		}

		$this->statements[] = 'transactionStart';
		$db = new LockRecordingDatabase;
		$db->eventRows[$eventId] = $event;
		$locked = EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);

		if ($locked === null)
		{
			return 'invalid';
		}

		if ((int) $locked->status === 3)
		{
			return 'cancelled';
		}

		if ($this->kind === 'meeting' && !\in_array((int) $locked->status, [1, 2, 4, 5], true))
		{
			return 'closed';
		}

		if ($this->kind === 'qr' && !\in_array((int) $locked->status, [1, 4], true))
		{
			return 'cancelled';
		}

		try
		{
			if ($this->throwDuplicateOnInsert)
			{
				throw new \RuntimeException('Duplicate entry \'4-9\' for key \'idx_event_profile\'', 1062);
			}

			$attendanceId = $this->nextId++;
			$this->statements[] = 'INSERT #__wendfold_attendance';
			$this->rows[$attendanceId] = [
				'id'         => $attendanceId,
				'event_id'   => $eventId,
				'profile_id' => $profileId,
			];
			$this->store->attendances[$attendanceId] = (object) [
				'id'         => $attendanceId,
				'profile_id' => $profileId,
				'event_id'   => $eventId,
				'event_date' => $this->store->eventDates[$eventId] ?? '2026-06-15',
			];
			(new MetricService($this->store, 7))->creditAttendance($eventId, $attendanceId);

			return true;
		}
		catch (\Throwable $error)
		{
			if (DuplicateKey::is($error))
			{
				// Mirror RcattendanceModel::addMember: only 'already' when the row remains.
				if ($this->kind === 'rc')
				{
					foreach ($this->rows as $row)
					{
						if ((int) $row['event_id'] === $eventId && (int) $row['profile_id'] === $profileId)
						{
							return 'already';
						}
					}

					return 'db_error';
				}

				return 'already';
			}

			return 'db_error';
		}
	}

	/**
	 * @param   integer  $attendanceId  Attendance id.
	 * @param   integer  $eventId       Event id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function remove(int $attendanceId, int $eventId): bool|string
	{
		$row = $this->rows[$attendanceId] ?? null;

		if ($row === null || (int) $row['event_id'] !== $eventId)
		{
			return 'not_found';
		}

		$this->statements[] = 'transactionStart';
		$db = new LockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		$locked = EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);

		if ($locked === null)
		{
			return 'not_found';
		}

		if ((int) $locked->status === 3)
		{
			return 'cancelled';
		}

		if ($this->kind === 'meeting' && !\in_array((int) $locked->status, [1, 2, 4, 5], true))
		{
			return 'closed';
		}

		$this->statements[] = 'DELETE #__wendfold_attendance';
		$profileId = (int) $row['profile_id'];
		unset($this->rows[$attendanceId]);
		(new MetricService($this->store, 7))->trashAttendance($eventId, $attendanceId, $profileId);

		return true;
	}
}

/**
 * Records SELECT … FOR UPDATE statements for EventRowLock.
 *
 * @since  1.0.0
 */
final class LockRecordingDatabase implements DatabaseInterface
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object|null>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

	/**
	 * Recorded statement labels.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * Whether a FOR UPDATE select was built.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $sawForUpdate = false;

	/**
	 * Selected columns for the current query.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * Bound event id.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundId = null;

	/**
	 * Whether the current query is for update.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $forUpdate = false;

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		$this->selected = '';
		$this->boundId = null;
		$this->forUpdate = false;

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
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(',', $columns) : (string) $columns;

		return $this;
	}

	/**
	 * @param   mixed  $table  Table.
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
	 * @param   mixed  $condition  Condition.
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
	 * @param   mixed    $query   Query object or raw SQL with FOR UPDATE.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'FOR UPDATE'))
		{
			$this->forUpdate = true;
			$this->sawForUpdate = true;
			$this->statements[] = 'SELECT #__wendfold_events FOR UPDATE';
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
	 * @param   boolean  $asSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionStart($asSavepoint = false): void
	{
		$this->statements[] = 'transactionStart';
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
	 * @param   string  $name       Method.
	 * @param   array   $arguments  Args.
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
 * EventModel stand-in that records lock order on publish/delete.
 *
 * @since  1.0.0
 */
final class LockingLifecycleEventModel extends FixtureEventModel
{
	/**
	 * @var    MemoryMetricEntryStore
	 * @since  1.0.0
	 */
	public MemoryMetricEntryStore $store;

	/**
	 * @var    LockingLifecycleEventDatabase
	 * @since  1.0.0
	 */
	public LockingLifecycleEventDatabase $database;

	/**
	 * @return  LockingLifecycleEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService(): MetricService
	{
		return new MetricService($this->store, 7);
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
		return $this->database->eventRows[$eventId] ?? null;
	}
}

/**
 * Database double that records transaction and FOR UPDATE order for EventModel.
 *
 * @since  1.0.0
 */
final class LockingLifecycleEventDatabase implements DatabaseInterface
{
	/**
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventStates = [];

	/**
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

	/**
	 * @var    array<string, array<int, int>>
	 * @since  1.0.0
	 */
	public array $children = [];

	/**
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $deleteTable = '';

	/**
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundEventId = null;

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $forUpdate = false;

	/**
	 * Snapshots for nested transactions.
	 *
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   LockingLifecycleEventModel  $model  Owning model.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private LockingLifecycleEventModel $model)
	{
	}

	/**
	 * @param   string   $table    Short table name.
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
	 * @param   boolean  $new  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		$this->selected = '';
		$this->deleteTable = '';
		$this->boundEventId = null;
		$this->forUpdate = false;

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
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(',', $columns) : (string) $columns;

		return $this;
	}

	/**
	 * @param   mixed  $table  Table.
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
	 * @param   mixed  $table  Table.
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
	 * @param   mixed  $condition  Condition.
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
		if ((string) $key === ':eventId' || (string) $key === ':id')
		{
			$this->boundEventId = (int) $value;
		}

		return $this;
	}

	/**
	 * @param   mixed    $query   Query object or raw SQL with FOR UPDATE.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'FOR UPDATE'))
		{
			$this->forUpdate = true;
			$this->statements[] = 'SELECT #__wendfold_events FOR UPDATE';
		}

		if ($this->deleteTable !== '')
		{
			$this->statements[] = 'DELETE ' . $this->deleteTable;
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

		return $this->eventRows[$this->boundEventId] ?? null;
	}

	/**
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		return null;
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
		$this->statements[] = 'transactionStart';
		$this->snapshots[] = [
			'children'        => $this->children,
			'entries'         => $this->model->store->entries,
			'statements'      => $this->statements,
			'publishedValues' => $this->model->publishedValues,
			'deletedIds'      => $this->model->deletedIds,
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
		$this->statements = $snapshot['statements'];
		$this->model->publishedValues = $snapshot['publishedValues'];
		$this->model->deletedIds = $snapshot['deletedIds'];
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
	 * @param   array   $arguments  Args.
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
 * Event save that exercises real inline location writes.
 *
 * Resync sees a missing event date so the save transaction rolls back.
 *
 * @since  1.0.0
 */
final class InlineLocationEventModel extends FixtureEventModel
{
	/**
	 * @var    InlineLocationEventDatabase
	 * @since  1.0.0
	 */
	public InlineLocationEventDatabase $database;

	/**
	 * @param   array<string, mixed>  $posted  Posted credits.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	protected function parsePostedMetricCredits(array $posted): array
	{
		return [1 => '1.000'];
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
		return new MetricService(new MemoryMetricEntryStore, 7);
	}

	/**
	 * @return  InlineLocationEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Database double that rolls back inline location inserts with the save transaction.
 *
 * @since  1.0.0
 */
final class InlineLocationEventDatabase implements DatabaseInterface
{
	/**
	 * Inserted location names.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $locations = [];

	/**
	 * Recorded statements.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundId = null;

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $forUpdate = false;

	/**
	 * @var    list<array{locations: list<string>, storedData: array, statements: list<string>}>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   InlineLocationEventModel  $model  Owning model.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private InlineLocationEventModel $model)
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
		$this->selected = '';
		$this->boundId = null;
		$this->forUpdate = false;

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
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(',', $columns) : (string) $columns;

		return $this;
	}

	/**
	 * @param   mixed  $table  Table.
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
	 * @param   mixed  $condition  Condition.
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
	 * @param   mixed    $query   Query object or raw SQL with FOR UPDATE.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'FOR UPDATE'))
		{
			$this->forUpdate = true;
			$this->statements[] = 'SELECT #__wendfold_events FOR UPDATE';
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
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if (str_contains($this->selected, 'COUNT'))
		{
			return 0;
		}

		if (str_contains($this->selected, 'status'))
		{
			return 1;
		}

		if (str_contains($this->selected, 'event_date'))
		{
			return '2026-07-01';
		}

		if (str_contains($this->selected, 'published'))
		{
			return 1;
		}

		return null;
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

		return (object) [
			'id'         => $this->boundId,
			'published'  => 1,
			'status'     => 1,
			'event_date' => '2026-07-01',
		];
	}

	/**
	 * @param   string    $table   Table name.
	 * @param   object    $object  Row.
	 * @param   string    $key     Primary key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function insertObject($table, &$object, $key = null)
	{
		if (str_contains((string) $table, 'locations'))
		{
			$this->statements[] = 'INSERT #__wendfold_locations';
			$this->locations[] = (string) ($object->name ?? '');
		}

		if ($key !== null && !isset($object->$key))
		{
			$object->$key = \count($this->locations);
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
		$this->statements[] = 'transactionStart';
		$this->snapshots[] = [
			'locations'  => $this->locations,
			'storedData' => $this->model->storedData,
			'statements' => $this->statements,
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

		$this->locations = $snapshot['locations'];
		$this->model->storedData = $snapshot['storedData'];
		$this->statements = $snapshot['statements'];
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
	 * @param   mixed  $value  Value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quote($value)
	{
		return "'" . (string) $value . "'";
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
	 * @param   array   $arguments  Args.
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
 * Event save that exercises cancel-with-check-ins refusal.
 *
 * @since  1.0.0
 */
final class CancelCheckinsEventModel extends FixtureEventModel
{
	/**
	 * @var    CancelCheckinsEventDatabase
	 * @since  1.0.0
	 */
	public CancelCheckinsEventDatabase $database;

	/**
	 * How many cancel notices dispatchEventCancelNotice would send.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $cancelNoticeCount = 0;

	/**
	 * @param   DatabaseInterface  $db             Database.
	 * @param   integer            $eventId        Event id.
	 * @param   boolean            $transitioned   True when the event became Cancelled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function dispatchEventCancelNotice(
		DatabaseInterface $db,
		int $eventId,
		bool $transitioned
	): void
	{
		if ($transitioned && $eventId > 0)
		{
			$this->cancelNoticeCount++;
		}
	}

	/**
	 * @param   array<string, mixed>  $posted  Posted credits.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	protected function parsePostedMetricCredits(array $posted): array
	{
		return [1 => '1.000'];
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
		$store = new MemoryMetricEntryStore;
		$store->eventDates[15] = '2026-07-01';
		$store->eventStates[15] = 1;
		$store->eventStatuses[15] = 1;

		return new MetricService($store, 7);
	}

	/**
	 * @return  CancelCheckinsEventDatabase
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Database double for cancel-with-check-ins event form saves.
 *
 * @since  1.0.0
 */
final class CancelCheckinsEventDatabase implements DatabaseInterface
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

	/**
	 * Attendance counts keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $attendanceCount = [];

	/**
	 * When false, parent::save records fields but leaves eventRows.status unchanged.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $persistStatus = true;

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $selected = '';

	/**
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundId = null;

	/**
	 * @var    list<array{storedData: array, eventRows: array<int, object>}>
	 * @since  1.0.0
	 */
	private array $snapshots = [];

	/**
	 * @param   CancelCheckinsEventModel  $model  Owning model.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private CancelCheckinsEventModel $model)
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
		$this->selected = '';
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
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->selected = \is_array($columns) ? implode(',', $columns) : (string) $columns;

		return $this;
	}

	/**
	 * @param   mixed  $table  Table.
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
	 * @param   mixed  $condition  Condition.
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
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if (str_contains($this->selected, 'COUNT'))
		{
			return $this->attendanceCount[$this->boundId ?? 0] ?? 0;
		}

		if (str_contains($this->selected, 'event_date'))
		{
			return '2026-07-01';
		}

		if (str_contains($this->selected, 'published'))
		{
			return 1;
		}

		if (str_contains($this->selected, 'status'))
		{
			$row = $this->eventRows[$this->boundId ?? 0] ?? null;

			return $row !== null ? (int) $row->status : 1;
		}

		return null;
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
	 * @param   boolean  $asSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionStart($asSavepoint = false): void
	{
		$cloned = [];

		foreach ($this->eventRows as $id => $row)
		{
			$cloned[$id] = clone $row;
		}

		$this->snapshots[] = [
			'storedData' => $this->model->storedData,
			'eventRows'  => $cloned,
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
		$this->eventRows = $snapshot['eventRows'];
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
	 * @param   mixed  $value  Value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quote($value)
	{
		return "'" . (string) $value . "'";
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
	 * @param   array   $arguments  Args.
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

