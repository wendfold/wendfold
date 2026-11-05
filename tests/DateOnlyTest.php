<?php

/**
 * Wendfold - Date-only display.
 *
 * A stored calendar day must stay on that day in zones west of UTC, at UTC,
 * and east of UTC.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\HTML\HTMLHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;

/**
 * Locks calendar-day formatting so a DATE value is not shifted.
 *
 * @since  1.0.0
 */
final class DateOnlyTest extends TestCase
{
	/**
	 * PHP default zone before the test.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $previousTimezone;

	/**
	 * Remember the process zone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		$this->previousTimezone = date_default_timezone_get();
	}

	/**
	 * Restore the process zone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->previousTimezone);
	}

	/**
	 * Zones the same stored day must survive.
	 *
	 * @return  array<string, array{0: string}>
	 *
	 * @since   1.0.0
	 */
	public static function zoneProvider(): array
	{
		return [
			'America/Chicago'     => ['America/Chicago'],
			'UTC'                 => ['UTC'],
			'Pacific/Kiritimati'  => ['Pacific/Kiritimati'],
			'Pacific/Pago_Pago'   => ['Pacific/Pago_Pago'],
		];
	}

	/**
	 * The stored day is the displayed day in each zone.
	 *
	 * @param   string  $zone  IANA zone used as the user and site offset.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('zoneProvider')]
	public function testStoredDateIsTheSameCalendarDayInEachZone(string $zone): void
	{
		date_default_timezone_set($zone);

		self::assertSame('2026-10-29', DateOnly::format('2026-10-29', 'Y-m-d'));
		self::assertSame('2026-10-29', DateOnly::format('2026-10-29 00:00:00', 'Y-m-d'));
		self::assertSame('2026-10-29', DateOnly::format('2026-10-29 00:00:00.000000', 'Y-m-d'));
		self::assertSame('Thu, Oct 29, 2026', DateOnly::format('2026-10-29', 'D, M j, Y'));
		self::assertSame('29 October 2026', DateOnly::format('  2026-10-29  ', 'DATE_FORMAT_LC3'));

		$shifted = HTMLHelper::_('date', '2026-10-29', 'Y-m-d', false);

		if ($zone === 'America/Chicago' || $zone === 'Pacific/Pago_Pago')
		{
			self::assertSame('2026-10-28', $shifted);
		}
		else
		{
			self::assertSame('2026-10-29', $shifted);
		}
	}

	/**
	 * Empty and zero dates do not format.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyAndZeroDatesReturnAnEmptyString(): void
	{
		date_default_timezone_set('America/Chicago');

		self::assertSame('', DateOnly::format(null, 'DATE_FORMAT_LC3'));
		self::assertSame('', DateOnly::format('', 'Y-m-d'));
		self::assertSame('', DateOnly::format('   ', 'Y-m-d'));
		self::assertSame('', DateOnly::format('0000-00-00', 'Y-m-d'));
		self::assertSame('', DateOnly::format('0000-00-00 00:00:00', 'M j, Y'));
		self::assertSame('', DateOnly::format('2026-02-31', 'Y-m-d'));
		self::assertSame('', DateOnly::format('2026-10-29 18:30:00', 'Y-m-d'));
	}

	/**
	 * Zones where a wall-clock time must stay on that clock.
	 *
	 * @return  array<string, array{0: string}>
	 *
	 * @since   1.0.0
	 */
	public static function timeZoneProvider(): array
	{
		return [
			'America/Chicago'    => ['America/Chicago'],
			'UTC'                => ['UTC'],
			'Pacific/Pago_Pago'  => ['Pacific/Pago_Pago'],
		];
	}

	/**
	 * A stored kickstands-up time is the displayed time in each zone.
	 *
	 * @param   string  $zone  IANA zone used as the user and site offset.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('timeZoneProvider')]
	public function testStoredTimeIsTheSameClockInEachZone(string $zone): void
	{
		date_default_timezone_set($zone);

		self::assertSame('8:00 AM', DateOnly::time('08:00:00', 'g:i A'));
		self::assertSame('8:00 AM', DateOnly::time('08:00', 'g:i A'));
		self::assertSame('8:00 AM', DateOnly::time('8:00:00', 'g:i A'));
		self::assertSame('12:00 AM', DateOnly::time('00:00:00', 'g:i A'));
		self::assertSame('', DateOnly::time(null, 'g:i A'));
		self::assertSame('', DateOnly::time('', 'g:i A'));
		self::assertSame('', DateOnly::time('   ', 'g:i A'));
	}

	/**
	 * The converting date helper moves 08:00 UTC into the previous clock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUserZoneShiftsATimeAnchoredOnAUtcDay(): void
	{
		date_default_timezone_set('America/Chicago');

		self::assertSame('2:00 AM', HTMLHelper::_('date', '1970-01-01 08:00:00', 'g:i A'));
		self::assertSame('8:00 AM', DateOnly::time('08:00:00', 'g:i A'));
	}
}
