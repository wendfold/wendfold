<?php

/**
 * Wendfold - ReminderPlanner catch-up rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderPlanner;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ComponentOptions.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderPlanner.php';

/**
 * Catch-up window selection for renewal offsets.
 *
 * @since  1.0.0
 */
final class ReminderPlannerTest extends TestCase
{
	/**
	 * After a missed week inside both windows, only the nearer offset is sent.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCatchUpSendsMostUrgentAndSkipsEarlierOffset(): void
	{
		$windows = [
			['days' => 60],
			['days' => 30],
		];
		$plan = ReminderPlanner::renewalPlan('2026-10-20', '2026-11-01', $windows, []);

		$this->assertSame(30, $plan['send']);
		$this->assertSame([60], $plan['skip']);
	}

	/**
	 * A logged send for this expiry is not planned again in the same year.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoResendSameYearWhenAlreadyLogged(): void
	{
		$windows = [
			['days' => 60],
			['days' => 30],
		];
		$logged = [ReminderPlanner::renewalKey(60)];
		$plan = ReminderPlanner::renewalPlan('2026-09-02', '2026-11-01', $windows, $logged);

		$this->assertNull($plan);
	}

	/**
	 * A new expiry date is a new key, so year 2 can send again.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNextYearUsesNewExpiryKey(): void
	{
		$windows = [
			['days' => 60],
			['days' => 30],
		];
		$plan = ReminderPlanner::renewalPlan('2026-09-02', '2026-11-01', $windows, []);

		$this->assertSame(60, $plan['send']);
		$this->assertSame([], $plan['skip']);
		$this->assertSame('renewal-60', ReminderPlanner::renewalKey(60));
		$this->assertSame('event-12-0', ReminderPlanner::eventKey(12, 0));
	}

	/**
	 * Expired or blank renewals are never due.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testExpiredRenewalIsNotDue(): void
	{
		$windows = [['days' => 60], ['days' => 30]];

		$this->assertNull(ReminderPlanner::renewalPlan('2026-11-01', '2026-11-01', $windows, []));
		$this->assertNull(ReminderPlanner::renewalPlan('2026-11-02', '2026-11-01', $windows, []));
		$this->assertNull(ReminderPlanner::renewalPlan('2026-10-01', '', $windows, []));
	}

	/**
	 * Upgrade path: legacy reminder_60_sent for this expiry suppresses the 60-day
	 * resend when reminder_log is empty, while the 30-day offset stays available.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtySentBlocksResendButThirtyStillDue(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => '2026-09-02',
			'reminder_30_sent' => null,
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-11-01', $windows, [], $legacy);

		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNotContains(ReminderPlanner::renewalKey(30), $logged);

		// 45 days before expiry: only the 60-day window is open, and it is legacy-sent.
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-09-17', '2026-11-01', $windows, $logged)
		);

		// 30-day window opens later and is still due.
		$plan = ReminderPlanner::renewalPlan('2026-10-02', '2026-11-01', $windows, $logged);

		$this->assertSame(30, $plan['send']);
		$this->assertSame([], $plan['skip']);
	}

	/**
	 * A send six days before the exact offset day still counts in this cycle.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtySixDaysBeforeOffsetStillCounts(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$logged = ReminderPlanner::loggedKeysWithLegacy(
			'2026-11-01',
			$windows,
			[],
			['reminder_60_sent' => '2026-08-27', 'reminder_30_sent' => null]
		);

		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-09-02', '2026-11-01', $windows, $logged)
		);
	}

	/**
	 * Moving renewal earlier must not reopen a 60-day stage already sent.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtySurvivesRenewalMovedEarlier(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => '2026-10-31',
			'reminder_30_sent' => null,
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-12-15', $windows, [], $legacy);

		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-10-31', '2026-12-15', $windows, $logged)
		);
	}

	/**
	 * Moving renewal later by 15 days must not reopen a 60-day stage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtySurvivesRenewalMovedLaterFifteenDays(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => '2026-10-16',
			'reminder_30_sent' => null,
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-12-30', $windows, [], $legacy);

		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-10-31', '2026-12-30', $windows, $logged)
		);
	}

	/**
	 * Moving renewal later by 30 days must not reopen a 30-day stage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacyThirtySurvivesRenewalMovedLaterThirtyDays(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => null,
			'reminder_30_sent' => '2026-10-16',
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-11-30', $windows, [], $legacy);

		$this->assertContains(ReminderPlanner::renewalKey(30), $logged);
		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-10-31', '2026-11-30', $windows, $logged)
		);
	}

	/**
	 * A legacy flag from a previous renewal cycle does not suppress the new cycle.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacyFlagFromPreviousCycleDoesNotSuppressNewCycle(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => '2025-09-02',
			'reminder_30_sent' => '2025-10-02',
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-11-01', $windows, [], $legacy);

		$this->assertSame([], $logged);

		$plan = ReminderPlanner::renewalPlan('2026-09-02', '2026-11-01', $windows, $logged);

		$this->assertSame(60, $plan['send']);
	}

	/**
	 * Upgrade path: legacy 30-day sent with 60 still NULL must not send a
	 * farther 60-day mail. Reminders only move forward toward expiry.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacyThirtySentBlocksFartherSixty(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$legacy = [
			'reminder_60_sent' => null,
			'reminder_30_sent' => '2026-10-02',
		];
		$logged = ReminderPlanner::loggedKeysWithLegacy('2026-11-01', $windows, [], $legacy);

		$this->assertContains(ReminderPlanner::renewalKey(30), $logged);
		$this->assertContains(ReminderPlanner::renewalKey(60), $logged);
		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-10-02', '2026-11-01', $windows, $logged)
		);
	}

	/**
	 * New tracking: a logged nearer stage makes farther stages done.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLoggedThirtyBlocksFartherSixty(): void
	{
		$windows = ComponentOptions::reminderWindows(60, 30);
		$logged = [ReminderPlanner::renewalKey(30)];

		$this->assertNull(
			ReminderPlanner::renewalPlan('2026-10-02', '2026-11-01', $windows, $logged)
		);

		// After only the farther stage is logged, the nearer stage still sends.
		$plan = ReminderPlanner::renewalPlan(
			'2026-10-02',
			'2026-11-01',
			$windows,
			[ReminderPlanner::renewalKey(60)]
		);

		$this->assertSame(30, $plan['send']);
		$this->assertSame([], $plan['skip']);
	}

	/**
	 * Event reminders go to going and maybe only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventReminderResponses(): void
	{
		$this->assertTrue(ReminderPlanner::receivesEventReminder(1));
		$this->assertTrue(ReminderPlanner::receivesEventReminder(2));
		$this->assertFalse(ReminderPlanner::receivesEventReminder(3));
		$this->assertFalse(ReminderPlanner::receivesEventReminder(4));
		$this->assertFalse(ReminderPlanner::receivesEventReminder(0));
	}

	/**
	 * Day-of reminders only for published events; unpublished and trashed skip.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventPublishedGateMatchesLiveMail(): void
	{
		$this->assertTrue(ReminderPlanner::eventEligibleForReminder(1));
		$this->assertFalse(ReminderPlanner::eventEligibleForReminder(0));
		$this->assertFalse(ReminderPlanner::eventEligibleForReminder(-2));
		$this->assertFalse(ReminderPlanner::eventEligibleForReminder(2));
	}
}
