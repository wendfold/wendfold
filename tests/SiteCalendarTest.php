<?php

/**
 * Wendfold - site calendar day at the call sites.
 *
 * A fixed clock at 23:30 or 00:31 in a zone away from UTC, with php.ini in UTC.
 * These fail when a date-only field converts through UTC, or when a call site
 * reads the UTC day.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Locks date-only form fields and the remaining site-day call sites.
 *
 * @since  1.0.0
 */
final class SiteCalendarTest extends TestCase
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
	 * Dues default date survives the form filter.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDuesDefaultDateSurvivesTheFormFilter(): void
	{
		$model = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/DuespaymentModel.php'
		);
		self::assertStringNotContainsString("date('Y-m-d')", $model);
		self::assertStringContainsString('->today()', $model);

		$filter = $this->calendarFilter('com_wendfold/admin/forms/duespayment.xml', 'payment_date');
		$zones  = ['America/Chicago', 'Asia/Tokyo', 'Pacific/Honolulu'];

		foreach ($zones as $zone)
		{
			$clock   = new \DateTimeImmutable('2026-10-25 00:31:00', new \DateTimeZone($zone));
			$siteDay = (new SiteTime($zone, $clock))->today();
			$stored  = $this->resave($siteDay, $filter, $zone, true);

			self::assertSame('2026-10-25', $siteDay, $zone);
			self::assertSame('2026-10-25', $stored, $zone . ' stored ' . $stored . ' from filter ' . $filter);
		}
	}

	/**
	 * Date only fields round trip on resave.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDateOnlyFieldsRoundTripOnResave(): void
	{
		$failures = [];

		foreach ($this->calendarFields() as $field)
		{
			$label = $field['file'] . ' ' . $field['name'];

			if ($field['dateOnly'])
			{
				if ($field['filter'] !== 'date')
				{
					$failures[] = $label . ' uses ' . $field['filter'];
				}

				foreach (['America/Chicago', 'Asia/Tokyo'] as $zone)
				{
					$stored = $this->resave('2026-06-15', $field['filter'], $zone, true);

					if ($stored !== '2026-06-15')
					{
						$failures[] = $label . ' ' . $zone . ' stored ' . $stored;
					}
				}

				continue;
			}

			if (!in_array($field['filter'], ['server_utc', 'user_utc'], true))
			{
				$failures[] = $label . ' is a datetime and uses ' . $field['filter'];
			}
		}

		self::assertSame([], $failures);
	}

	/**
	 * Dashboard dues stay expiring on new years eve.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDashboardDuesStayExpiringOnNewYearsEve(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/DashboardModel.php'
		);
		self::assertStringNotContainsString("new \\DateTime('today')", $source);
		self::assertStringContainsString('->duesStatus(', $source);

		$clock = new \DateTimeImmutable('2026-12-31 23:30:00', new \DateTimeZone('America/Chicago'));
		$time  = new SiteTime('America/Chicago', $clock);

		self::assertSame('2027-01-01', $clock->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'));
		self::assertSame('expiring', $time->duesStatus('2026-12-31'));
		self::assertSame('expired', $time->duesStatus('2026-12-30'));
		self::assertSame('expiring', $time->duesStatus('2027-03-01'));
		self::assertSame('current', $time->duesStatus('2027-03-02'));
	}

	/**
	 * Status report labels use the site day.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testStatusReportLabelsUseTheSiteDay(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/statusreport/default.php'
		);
		self::assertStringNotContainsString("new \\DateTime('today')", $source);
		self::assertStringContainsString('->renewalCue(', $source);

		$clock    = new \DateTimeImmutable('2026-10-24 23:30:00', new \DateTimeZone('America/Chicago'));
		$time     = new SiteTime('America/Chicago', $clock);
		$dueToday = $time->renewalCue('2026-10-24');

		self::assertSame('2026-10-25', $clock->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d'));
		self::assertFalse($dueToday['past']);
		self::assertSame('In 0d', $dueToday['label']);
		self::assertSame('table-warning', $dueToday['rowClass']);
		self::assertSame('1d ago', $time->renewalCue('2026-10-23')['label']);
		self::assertTrue($time->renewalCue('2026-10-23')['past']);
	}

	/**
	 * Now keeps the fallback instant.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNowKeepsTheFallbackInstant(): void
	{
		$zone = 'America/Chicago';

		$first = new \DateTimeImmutable('2026-11-01 06:30:00', new \DateTimeZone('UTC'));
		$early = (new SiteTime($zone, $first))->now();
		self::assertSame('2026-11-01 06:30:00', $early->format('Y-m-d H:i:s', false));
		self::assertSame('2026-11-01 01:30:00', $early->format('Y-m-d H:i:s', true));

		$second = new \DateTimeImmutable('2026-11-01 07:30:00', new \DateTimeZone('UTC'));
		$late   = (new SiteTime($zone, $second))->now();
		self::assertSame('2026-11-01 07:30:00', $late->format('Y-m-d H:i:s', false));
		self::assertSame('2026-11-01 01:30:00', $late->format('Y-m-d H:i:s', true));
	}

	/**
	 * Now keeps the spring forward instant.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNowKeepsTheSpringForwardInstant(): void
	{
		$zone = 'America/Chicago';

		$before = new \DateTimeImmutable('2026-03-08 07:30:00', new \DateTimeZone('UTC'));
		$still  = (new SiteTime($zone, $before))->now();
		self::assertSame('2026-03-08 07:30:00', $still->format('Y-m-d H:i:s', false));
		self::assertSame('2026-03-08 01:30:00', $still->format('Y-m-d H:i:s', true));

		$after = new \DateTimeImmutable('2026-03-08 08:30:00', new \DateTimeZone('UTC'));
		$jumped = (new SiteTime($zone, $after))->now();
		self::assertSame('2026-03-08 08:30:00', $jumped->format('Y-m-d H:i:s', false));
		self::assertSame('2026-03-08 03:30:00', $jumped->format('Y-m-d H:i:s', true));
	}

	/**
	 * @return list<array{file: string, name: string, filter: string, dateOnly: bool}>
	 *
	 * @since   1.0.0
	 */
	private function calendarFields(): array
	{
		$fields = [];
		$root   = dirname(__DIR__) . '/com_wendfold';

		foreach (['admin/forms', 'site/forms', 'site/tmpl'] as $dir)
		{
			$base = $root . '/' . $dir;

			if (!is_dir($base))
			{
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));

			foreach ($iterator as $file)
			{
				if (!$file->isFile() || $file->getExtension() !== 'xml')
				{
					continue;
				}

				$xml = simplexml_load_file($file->getPathname());

				if ($xml === false)
				{
					continue;
				}

				foreach ($xml->xpath('//field[@type="calendar"]') ?: [] as $field)
				{
					$format = (string) ($field['format'] ?? '%Y-%m-%d');
					$filter = strtolower(trim((string) ($field['filter'] ?? '')));
					$fields[] = [
						'file'     => 'com_wendfold/' . $dir . '/' . substr($file->getPathname(), strlen($base) + 1),
						'name'     => (string) $field['name'],
						'filter'   => $filter === '' ? 'user_utc' : $filter,
						'dateOnly' => !str_contains($format, '%H') && !str_contains($format, '%I'),
					];
				}
			}
		}

		return $fields;
	}

	/**
	 * Filter attribute of one calendar field in the form XML.
	 *
	 * @param   string  $relative  Form file path relative to the repo.
	 * @param   string  $name      Field name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function calendarFilter(string $relative, string $name): string
	{
		foreach ($this->calendarFields() as $field)
		{
			if ($field['file'] === $relative && $field['name'] === $name)
			{
				return $field['filter'];
			}
		}

		self::fail('Missing calendar field ' . $relative . ' ' . $name);
	}

	/**
	 * One display-then-save pass, matching the Joomla calendar field.
	 *
	 * server_utc and user_utc treat a stored date as UTC on the way out and as
	 * local time on the way back in. A plain date filter does neither.
	 * A missing filter displays as user_utc and stores the shown day as-is.
	 *
	 * @param   string  $stored    Stored database value.
	 * @param   string  $filter    Calendar field filter.
	 * @param   string  $zone      User time zone.
	 * @param   bool    $dateOnly  Whether the field shows the date only.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function resave(string $stored, string $filter, string $zone, bool $dateOnly): string
	{
		$shown   = $stored;
		$display = $filter;

		if ($filter === 'server_utc' || $filter === 'user_utc')
		{
			$shown = (new \DateTimeImmutable($stored, new \DateTimeZone('UTC')))
				->setTimezone(new \DateTimeZone($zone))
				->format('Y-m-d H:i:s');
		}

		$active = date_default_timezone_get();
		date_default_timezone_set('UTC');
		$posted = date($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', strtotime($shown));
		date_default_timezone_set($active);

		if ($display === 'server_utc' || $display === 'user_utc')
		{
			$saved = (new \DateTimeImmutable($posted, new \DateTimeZone($zone)))
				->setTimezone(new \DateTimeZone('UTC'));

			return $dateOnly ? $saved->format('Y-m-d') : $saved->format('Y-m-d H:i:s');
		}

		return $dateOnly ? substr($posted, 0, 10) : $posted;
	}
}
