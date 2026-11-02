<?php

/**
 * Wendfold - site-local metric periods.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';

/**
 * A UTC instant can fall in different calendar years by site zone.
 *
 * @since  1.0.0
 */
final class MetricPeriodTest extends TestCase
{
	/**
	 * Just after UTC midnight is still the previous Chicago year.
	 *
	 * The same instant is already the new year in Auckland.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCalendarYearFollowsTheSiteZone(): void
	{
		$instant = new \DateTimeImmutable('2026-01-01 05:00:00', new \DateTimeZone('UTC'));
		$date = new Date('@' . $instant->getTimestamp());

		$chicago = MetricPeriod::calendarYear('America/Chicago', $date);
		$auckland = MetricPeriod::calendarYear('Pacific/Auckland', $date);
		$utc = MetricPeriod::calendarYear('UTC', $date);

		$this->assertSame(2025, $chicago['year']);
		$this->assertSame('2025-01-01', $chicago['start']);
		$this->assertSame('2025-12-31', $chicago['end']);
		$this->assertSame(2026, $auckland['year']);
		$this->assertSame('2026-01-01', $auckland['start']);
		$this->assertSame('2026-12-31', $auckland['end']);
		$this->assertSame(2026, $utc['year']);
	}

	/**
	 * Late on 31 December UTC is already 1 January in Auckland.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNewYearStartsEarlierEastOfUtc(): void
	{
		$instant = new \DateTimeImmutable('2025-12-31 14:00:00', new \DateTimeZone('UTC'));
		$date = new Date('@' . $instant->getTimestamp());

		$chicago = MetricPeriod::calendarYear('America/Chicago', $date);
		$auckland = MetricPeriod::calendarYear('Pacific/Auckland', $date);

		$this->assertSame(2025, $chicago['year']);
		$this->assertSame(2026, $auckland['year']);
		$this->assertSame('2026-01-01', $auckland['start']);
	}

	/**
	 * The default clock is Factory::getDate('now', site zone).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDefaultClockUsesFactoryGetDate(): void
	{
		$chicago = MetricPeriod::calendarYear('America/Chicago');
		$expected = (int) Factory::getDate('now', 'America/Chicago')->format('Y', true);

		$this->assertSame($expected, $chicago['year']);
		$this->assertSame(sprintf('%04d-01-01', $expected), $chicago['start']);
		$this->assertSame(sprintf('%04d-12-31', $expected), $chicago['end']);
		$this->assertTrue(MetricPeriod::isDay($chicago['start']));
		$this->assertFalse(MetricPeriod::isDay('2026-02-31'));
	}
}
