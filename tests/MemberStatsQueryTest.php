<?php

/**
 * Wendfold - Year-to-date last activity day.
 *
 * A ride date stays on its calendar day. A UTC check-in becomes the viewer's
 * calendar day before the two are compared.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MemberStatsQuery;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;

/**
 * Locks the statistics last-activity day and the reminder clock.
 *
 * @since  1.0.0
 */
final class MemberStatsQueryTest extends TestCase
{
	/**
	 * Ride only stays on the stored day in Chicago.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRideOnlyStaysOnTheStoredDay(): void
	{
		$day = MemberStatsQuery::lastActivityDate('2026-06-01', null, 'America/Chicago');

		self::assertSame('2026-06-01', $day);
		self::assertSame('Jun 01, 2026', DateOnly::format($day, 'M d, Y'));
	}

	/**
	 * A check-in just after midnight UTC is the previous Chicago day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckInOnlyUsesTheViewerCalendarDay(): void
	{
		$chicago = MemberStatsQuery::lastActivityDate(null, '2026-06-02 03:00:00', 'America/Chicago');
		$utc     = MemberStatsQuery::lastActivityDate(null, '2026-06-02 03:00:00', 'UTC');

		self::assertSame('2026-06-01', $chicago);
		self::assertSame('2026-06-02', $utc);
		self::assertSame('Jun 01, 2026', DateOnly::format($chicago, 'M d, Y'));
	}

	/**
	 * The later calendar day wins when both values are present.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLaterOfRideAndCheckIn(): void
	{
		$sameChicagoDay = MemberStatsQuery::lastActivityDate(
			'2026-06-01',
			'2026-06-02 03:00:00',
			'America/Chicago'
		);
		$rideLater = MemberStatsQuery::lastActivityDate(
			'2026-06-03',
			'2026-06-02 03:00:00',
			'America/Chicago'
		);
		$checkInLater = MemberStatsQuery::lastActivityDate(
			'2026-05-01',
			'2026-06-02 15:00:00',
			'America/Chicago'
		);

		self::assertSame('2026-06-01', $sameChicagoDay);
		self::assertSame('2026-06-03', $rideLater);
		self::assertSame('2026-06-02', $checkInLater);
		self::assertSame('', MemberStatsQuery::lastActivityDate(null, null, 'America/Chicago'));
		self::assertSame('', MemberStatsQuery::lastActivityDate('', '0000-00-00 00:00:00', 'UTC'));
	}

	/**
	 * The viewer zone is the user zone, otherwise the site offset.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testViewerZonePrefersTheUserThenTheSite(): void
	{
		self::assertSame('America/Chicago', MemberStatsQuery::viewerZone('America/Chicago', 'UTC'));
		self::assertSame('America/Chicago', MemberStatsQuery::viewerZone('', 'America/Chicago'));
		self::assertSame('UTC', MemberStatsQuery::viewerZone('', ''));
		self::assertSame('America/Chicago', MemberStatsQuery::viewerZone('Not/AZone', 'America/Chicago'));
	}

	/**
	 * The day-of reminder prints a wall clock and is blank when empty.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDayOfReminderFormatsTheStoredClock(): void
	{
		$previous = date_default_timezone_get();
		date_default_timezone_set('America/Chicago');

		try
		{
			self::assertSame('8:00 AM', DateOnly::time('08:00:00', 'g:i A'));
			self::assertSame('', DateOnly::time(null, 'g:i A'));
			self::assertSame('', DateOnly::time('', 'g:i A'));
		}
		finally
		{
			date_default_timezone_set($previous);
		}

		$service = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderService.php'
		);

		self::assertStringContainsString('DateOnly::time(', $service);
		self::assertStringNotContainsString("' Kickstands Up: ' . \$ev->ksu_time", $service);
		self::assertStringNotContainsString("' Kickstands Up: ' . \$row->ksu_time", $service);
	}
}
