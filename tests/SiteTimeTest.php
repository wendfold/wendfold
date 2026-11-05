<?php

/**
 * Wendfold - Site timezone clock.
 *
 * Fixed clock at 23:30 America/Chicago, which is the next calendar day in UTC,
 * with php.ini set to UTC.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Date\Date;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Locks the site calendar day used by the events list and the RSVP cut-off.
 *
 * @since  1.0.0
 */
final class SiteTimeTest extends TestCase
{
	/**
	 * PHP default zone before the test.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $previousTimezone;

	/**
	 * Pin the PHP default zone to UTC for each test.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		$this->previousTimezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
	}

	/**
	 * Restore the PHP default zone.
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
	 * Site date at 2330chicago is not the utc day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteDateAt2330ChicagoIsNotTheUtcDay(): void
	{
		$clock = $this->chicagoEvening();
		$time = $this->chicagoClock($clock);

		self::assertSame('UTC', date_default_timezone_get());
		self::assertSame('2026-10-25', $clock->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'));
		self::assertSame('2026-10-25', date('Y-m-d', $clock->getTimestamp()));
		self::assertSame('2026-10-24', $time->today());
		self::assertSame(2026, $time->year());

		$now = $time->now();
		self::assertInstanceOf(Date::class, $now);
		self::assertSame('2026-10-24 23:30:00', $now->format('Y-m-d H:i:s', true));
		self::assertSame('2026-10-25 04:30:00', $now->format('Y-m-d H:i:s', false));

		$range = $time->utcRange('2026-10-24');
		self::assertSame('2026-10-24 05:00:00', $range['start']);
		self::assertSame('2026-10-25 05:00:00', $range['end']);
	}

	/**
	 * Rsvp cutoff stays open on the site day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpCutoffStaysOpenOnTheSiteDay(): void
	{
		$time = $this->chicagoClock($this->chicagoEvening());

		self::assertFalse($time->rsvpClosed('2026-10-24'));
		self::assertTrue($time->rsvpClosed('2026-10-23'));
		self::assertFalse($time->rsvpClosed('2026-10-25'));
		self::assertTrue($time->rsvpClosed(''));
	}

	/**
	 * Events list boundary uses the site day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventsListBoundaryUsesTheSiteDay(): void
	{
		$time = $this->chicagoClock($this->chicagoEvening());

		self::assertSame('>=', $time->eventsListOperator('upcoming'));
		self::assertSame('<', $time->eventsListOperator('past'));
		self::assertSame('2026-10-24', $time->today());

		self::assertTrue($time->eventsListIncludes('2026-10-24', 'upcoming'));
		self::assertFalse($time->eventsListIncludes('2026-10-24', 'past'));
		self::assertFalse($time->eventsListIncludes('2026-10-23', 'upcoming'));
		self::assertTrue($time->eventsListIncludes('2026-10-23', 'past'));
		self::assertTrue($time->eventsListIncludes('2026-10-25', 'upcoming'));
		self::assertFalse($time->eventsListIncludes('2026-10-25', 'past'));
		self::assertFalse($time->eventsListIncludes('', 'upcoming'));
		self::assertFalse($time->eventsListIncludes('', 'past'));
	}

	/**
	 * Reads the site zone from application config.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReadsTheSiteZoneFromApplicationConfig(): void
	{
		$clock = $this->chicagoEvening();
		$chicago = new class
		{
			/**
			 * Return a configuration value.
			 *
			 * @param   string  $key      Configuration key.
			 * @param   mixed   $default  Value when the key is not set.
			 *
			 * @return  mixed
			 *
			 * @since   1.0.0
			 */
			public function get(string $key, mixed $default = null): mixed
			{
				return $key === 'offset' ? 'America/Chicago' : $default;
			}
		};
		$blank = new class
		{
			/**
			 * Return a configuration value.
			 *
			 * @param   string  $key      Configuration key.
			 * @param   mixed   $default  Value when the key is not set.
			 *
			 * @return  mixed
			 *
			 * @since   1.0.0
			 */
			public function get(string $key, mixed $default = null): mixed
			{
				return '';
			}
		};

		self::assertSame('2026-10-24', SiteTime::fromApplication($chicago, $clock)->today());
		self::assertSame('2026-10-25', SiteTime::fromApplication($blank, $clock)->today());
		self::assertSame('2026-10-25', (new SiteTime('Not/AZone', $clock))->today());
		self::assertSame('2026-10-25', SiteTime::fromApplication(null, $clock)->today());
	}

	/**
	 * Reminder window is the site calendar.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReminderWindowIsTheSiteCalendar(): void
	{
		$time = $this->chicagoClock($this->chicagoEvening());

		self::assertSame('2026-09-24', $time->shiftDays(-30));
		self::assertSame('2026-12-23', $time->shiftDays(60));
		self::assertSame('2026-12-24', $time->shiftDays(61));
	}

	/**
	 * 2026-10-24 23:30 in Chicago, which is already the 25th in UTC.
	 *
	 * @return  \DateTimeImmutable
	 *
	 * @since   1.0.0
	 */
	private function chicagoEvening(): \DateTimeImmutable
	{
		return new \DateTimeImmutable('2026-10-24 23:30:00', new \DateTimeZone('America/Chicago'));
	}

	/**
	 * Site time in Chicago at the given instant.
	 *
	 * @param   \DateTimeImmutable  $clock  Fixed instant.
	 *
	 * @return  SiteTime
	 *
	 * @since   1.0.0
	 */
	private function chicagoClock(\DateTimeImmutable $clock): SiteTime
	{
		return new SiteTime('America/Chicago', $clock);
	}
}
