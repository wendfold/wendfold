<?php

/**
 * Wendfold - Site timezone clock.
 *
 * Calendar dates in Wendfold (event day, RSVP cut-off, check-in window,
 * reminder windows) are the Joomla site timezone from global configuration
 * offset. PHP date() follows php.ini, Factory::getDate() without a zone is
 * UTC, and SQL CURDATE() / DATE(NOW()) follow the database session. None of
 * those is the site timezone.
 *
 * Stored DATE and TIME values are not reformatted here.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Clock in the Joomla site timezone.
 *
 * Pass a DateTimeImmutable to freeze the instant. The site zone comes from
 * application configuration offset, and an empty or unknown value is UTC.
 *
 * @since  1.0.0
 */
class SiteTime
{
	/**
	 * IANA zone name. UTC when the configured value is empty or unknown.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $timezone;

	/**
	 * Frozen instant, or null to read the current time.
	 *
	 * @var    \DateTimeImmutable|null
	 * @since  1.0.0
	 */
	private ?\DateTimeImmutable $clock;

	/**
	 * @param   string                    $timezone  Site zone, usually configuration offset.
	 * @param   \DateTimeImmutable|null   $clock     Fixed instant. Null uses the current time.
	 *
	 * @since   1.0.0
	 */
	public function __construct(string $timezone = 'UTC', ?\DateTimeImmutable $clock = null)
	{
		$this->timezone = self::normaliseZone($timezone);
		$this->clock    = $clock;
	}

	/**
	 * Build a clock from application configuration offset.
	 *
	 * @param   object|null               $application  Application with get(), or null for the current one.
	 * @param   \DateTimeImmutable|null   $clock        Fixed instant. Null uses the current time.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromApplication(?object $application = null, ?\DateTimeImmutable $clock = null): self
	{
		if ($application === null)
		{
			$application = self::currentApplication();
		}

		$offset = 'UTC';

		if ($application !== null && \is_callable([$application, 'get']))
		{
			$value = $application->get('offset', 'UTC');

			if (\is_string($value) && trim($value) !== '')
			{
				$offset = trim($value);
			}
		}

		return new self($offset, $clock);
	}

	/**
	 * Site-local calendar day, Y-m-d.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function today(): string
	{
		return $this->localNow()->format('Y-m-d');
	}

	/**
	 * Site-local calendar year.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function year(): int
	{
		return (int) $this->localNow()->format('Y');
	}

	/**
	 * Current instant as a Joomla Date.
	 *
	 * The instant is the clock's Unix time, then the Date zone is the site
	 * zone. Rebuilding it from the local clock text picks the first 1 AM when
	 * that hour happens twice, and it can also miss the hour daylight saving
	 * skips. format() without the local flag still prints UTC. Pass true to
	 * format() to print the site-local clock.
	 *
	 * @return  Date
	 *
	 * @since   1.0.0
	 */
	public function now(): Date
	{
		$moment = $this->clock ?? new \DateTimeImmutable('now');
		$date   = new Date('@' . $moment->getTimestamp());
		$date->setTimezone(new \DateTimeZone($this->timezone));

		return $date;
	}

	/**
	 * Dues state for a renewal day, using the site calendar.
	 *
	 * expired is before today. expiring is today through 60 site days ahead.
	 * current is later than that. An empty date is none.
	 *
	 * @param   string  $renewalDate  Stored renewal day, Y-m-d, or empty.
	 *
	 * @return  string  none, expired, expiring, or current.
	 *
	 * @since   1.0.0
	 */
	public function duesStatus(string $renewalDate): string
	{
		if ($renewalDate === '')
		{
			return 'none';
		}

		$today   = new \DateTimeImmutable($this->today());
		$renewal = new \DateTimeImmutable($renewalDate);
		$diff    = (int) $today->diff($renewal)->format('%r%a');

		if ($diff < 0)
		{
			return 'expired';
		}

		if ($diff <= 60)
		{
			return 'expiring';
		}

		return 'current';
	}

	/**
	 * Status-report colour and day label for one renewal day.
	 *
	 * A renewal on the site today is "In 0d", not a past day.
	 *
	 * @param   string  $renewalDate  Stored renewal day, Y-m-d, or empty.
	 *
	 * @return  array{past: bool, days: int|null, label: string, rowClass: string}
	 *
	 * @since   1.0.0
	 */
	public function renewalCue(string $renewalDate): array
	{
		if ($renewalDate === '')
		{
			return [
				'past'     => false,
				'days'     => null,
				'label'    => ' - ',
				'rowClass' => '',
			];
		}

		$today   = new \DateTimeImmutable($this->today());
		$renewal = new \DateTimeImmutable($renewalDate);
		$past    = $renewal < $today;
		$days    = (int) $today->diff($renewal)->days;

		if ($past)
		{
			return [
				'past'     => true,
				'days'     => $days,
				'label'    => $days . 'd ago',
				'rowClass' => 'table-danger',
			];
		}

		if ($days <= 30)
		{
			return [
				'past'     => false,
				'days'     => $days,
				'label'    => 'In ' . $days . 'd',
				'rowClass' => 'table-warning',
			];
		}

		return [
			'past'     => false,
			'days'     => $days,
			'label'    => 'In ' . $days . 'd',
			'rowClass' => '',
		];
	}

	/**
	 * Site-local calendar day shifted by whole days.
	 *
	 * The shift is calendar days in the site zone, not 24-hour periods.
	 *
	 * @param   integer  $days  Days to add. Negative moves backward.
	 *
	 * @return  string  Y-m-d
	 *
	 * @since   1.0.0
	 */
	public function shiftDays(int $days): string
	{
		$local = $this->localNow()->setTime(12, 0);

		if ($days !== 0)
		{
			$local = $local->modify(sprintf('%+d days', $days));
		}

		return $local->format('Y-m-d');
	}

	/**
	 * Half-open UTC range covering one site-local calendar day.
	 *
	 * start is that local midnight in UTC. end is the next local midnight in
	 * UTC and is exclusive. Both values are Y-m-d H:i:s.
	 *
	 * @param   string  $localDate  Site-local day, Y-m-d.
	 *
	 * @return  array{start: string, end: string}
	 *
	 * @since   1.0.0
	 */
	public function utcRange(string $localDate): array
	{
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $localDate) !== 1)
		{
			throw new \InvalidArgumentException('Site-local date must be Y-m-d.');
		}

		$zone  = new \DateTimeZone($this->timezone);
		$start = new \DateTimeImmutable($localDate . ' 00:00:00', $zone);

		if ($start->format('Y-m-d') !== $localDate)
		{
			throw new \InvalidArgumentException('Site-local date must be a real calendar day.');
		}

		$endLocal = $start->modify('+1 day')->format('Y-m-d');
		$end      = new \DateTimeImmutable($endLocal . ' 00:00:00', $zone);
		$utc      = new \DateTimeZone('UTC');

		return [
			'start' => $start->setTimezone($utc)->format('Y-m-d H:i:s'),
			'end'   => $end->setTimezone($utc)->format('Y-m-d H:i:s'),
		];
	}

	/**
	 * RSVP is closed when the event date is missing or before the site today.
	 *
	 * The same rule keeps a volunteer signup closed.
	 *
	 * @param   string  $eventDate  Stored event_date, Y-m-d, or an empty string.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function rsvpClosed(string $eventDate): bool
	{
		return $eventDate === '' || $eventDate < $this->today();
	}

	/**
	 * Comparison operator for the site events list.
	 *
	 * Past is event_date < site today. Any other timeframe is upcoming,
	 * event_date >= site today. The site today itself stays on the upcoming list.
	 *
	 * @param   string  $timeframe  upcoming or past.
	 *
	 * @return  string  Less-than, or greater-than-or-equal.
	 *
	 * @since   1.0.0
	 */
	public function eventsListOperator(string $timeframe): string
	{
		return $timeframe === 'past' ? '<' : '>=';
	}

	/**
	 * Whether an event date falls in the upcoming or past events list.
	 *
	 * @param   string  $eventDate  Stored event_date, Y-m-d.
	 * @param   string  $timeframe  upcoming or past.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function eventsListIncludes(string $eventDate, string $timeframe): bool
	{
		if ($eventDate === '')
		{
			return false;
		}

		$today = $this->today();

		if ($this->eventsListOperator($timeframe) === '<')
		{
			return $eventDate < $today;
		}

		return $eventDate >= $today;
	}

	/**
	 * Current instant in the site timezone.
	 *
	 * @return  \DateTimeImmutable
	 *
	 * @since   1.0.0
	 */
	private function localNow(): \DateTimeImmutable
	{
		$zone   = new \DateTimeZone($this->timezone);
		$moment = $this->clock ?? new \DateTimeImmutable('now');

		return $moment->setTimezone($zone);
	}

	/**
	 * Application currently running, or null when Joomla is not booted.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private static function currentApplication(): ?object
	{
		if (!\class_exists(Factory::class))
		{
			return null;
		}

		try
		{
			return Factory::getApplication();
		}
		catch (\Throwable)
		{
			return null;
		}
	}

	/**
	 * Known IANA zone, or UTC.
	 *
	 * @param   string  $timezone  Candidate zone name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function normaliseZone(string $timezone): string
	{
		$timezone = trim($timezone);

		if ($timezone === '')
		{
			return 'UTC';
		}

		try
		{
			return (new \DateTimeZone($timezone))->getName();
		}
		catch (\Throwable)
		{
			return 'UTC';
		}
	}
}
