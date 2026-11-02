<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;

/**
 * Calendar periods in the site time zone.
 *
 * The current year comes from Factory::getDate('now', $siteTz). SQL never
 * supplies the clock. entry_date is a local calendar day, so the bounds are
 * Y-m-d strings, not UTC instants.
 *
 * @since  1.0.0
 */
final class MetricPeriod
{
	/**
	 * Inclusive calendar-year bounds for the site clock.
	 *
	 * Pass $now to read a fixed instant. The zone on that date is replaced
	 * with the site zone; the instant stays the same.
	 *
	 * @param   string     $siteTz  Site time zone, usually configuration offset.
	 * @param   Date|null  $now     Fixed instant. Null reads Factory::getDate('now', $siteTz).
	 *
	 * @return  array{start: string, end: string, year: int}
	 *
	 * @since   1.0.0
	 */
	public static function calendarYear(string $siteTz, ?Date $now = null): array
	{
		$zone = self::zone($siteTz);

		if ($now === null)
		{
			$now = Factory::getDate('now', $zone);
		}
		else
		{
			$now = clone $now;
			$now->setTimezone(new \DateTimeZone($zone));
		}

		return self::boundsForYear((int) $now->format('Y', true));
	}

	/**
	 * Inclusive bounds for one calendar year.
	 *
	 * @param   integer  $year  Four-digit year.
	 *
	 * @return  array{start: string, end: string, year: int}
	 *
	 * @since   1.0.0
	 */
	public static function boundsForYear(int $year): array
	{
		if ($year < 1 || $year > 9999)
		{
			throw new \InvalidArgumentException('Metric period year is out of range.');
		}

		return [
			'start' => sprintf('%04d-01-01', $year),
			'end'   => sprintf('%04d-12-31', $year),
			'year'  => $year,
		];
	}

	/**
	 * Site zone from application configuration, or UTC.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function siteZone(): string
	{
		$offset = 'UTC';

		try
		{
			$app = Factory::getApplication();
			$value = $app->get('offset', 'UTC');

			if (\is_string($value) && trim($value) !== '')
			{
				$offset = trim($value);
			}
		}
		catch (\Throwable)
		{
			$offset = 'UTC';
		}

		return self::zone($offset);
	}

	/**
	 * Whether the value is a real Y-m-d day.
	 *
	 * @param   string  $day  Candidate day.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isDay(string $day): bool
	{
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1)
		{
			return false;
		}

		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
		$errors = \DateTimeImmutable::getLastErrors();

		if (!$parsed instanceof \DateTimeImmutable)
		{
			return false;
		}

		if (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
		{
			return false;
		}

		return $parsed->format('Y-m-d') === $day;
	}

	/**
	 * Known IANA zone, or UTC.
	 *
	 * @param   string  $siteTz  Candidate zone.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function zone(string $siteTz): string
	{
		$siteTz = trim($siteTz);

		if ($siteTz === '')
		{
			return 'UTC';
		}

		try
		{
			return (new \DateTimeZone($siteTz))->getName();
		}
		catch (\Throwable)
		{
			return 'UTC';
		}
	}
}
