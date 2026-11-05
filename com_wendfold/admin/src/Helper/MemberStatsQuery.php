<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Compare a stored calendar day with a UTC check-in.
 *
 * @since  1.0.0
 */
final class MemberStatsQuery
{
	/**
	 * Later of a stored calendar day and a check-in, as Y-m-d.
	 *
	 * The ride value is a calendar day and is not shifted. The check-in is a
	 * UTC instant, converted to a calendar day in the viewer zone before the
	 * comparison. An empty pair returns an empty string.
	 *
	 * @param   string|null  $rideDate    Stored ride_date, or empty.
	 * @param   string|null  $checkInUtc  Stored check_in_time, UTC, or empty.
	 * @param   string       $viewerZone  IANA zone. User zone, otherwise the site offset.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function lastActivityDate(?string $rideDate, ?string $checkInUtc, string $viewerZone): string
	{
		$ride    = self::calendarDay($rideDate);
		$checkIn = self::checkInDay($checkInUtc, self::viewerZone($viewerZone, ''));

		if ($ride === '')
		{
			return $checkIn;
		}

		if ($checkIn === '')
		{
			return $ride;
		}

		return $ride >= $checkIn ? $ride : $checkIn;
	}

	/**
	 * Zone HTMLHelper date uses: the user zone, otherwise the site offset.
	 *
	 * @param   string  $userZone    User timezone parameter, or empty.
	 * @param   string  $siteOffset  Site configuration offset, or empty.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function viewerZone(string $userZone, string $siteOffset): string
	{
		foreach ([$userZone, $siteOffset] as $candidate)
		{
			$zone = self::knownZone($candidate);

			if ($zone !== '')
			{
				return $zone;
			}
		}

		return 'UTC';
	}

	/**
	 * Viewer zone for the current request.
	 *
	 * An empty user timezone falls through to the site offset. An unknown
	 * value, or no application, is UTC.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function currentViewerZone(): string
	{
		$userZone   = '';
		$siteOffset = '';

		try
		{
			$app    = Factory::getApplication();
			$offset = $app->get('offset', 'UTC');

			if (\is_string($offset))
			{
				$siteOffset = $offset;
			}

			$identity = $app->getIdentity();

			if ($identity instanceof User)
			{
				$param = $identity->getParam('timezone', '');

				if (\is_string($param))
				{
					$userZone = $param;
				}
			}
		}
		catch (\Throwable)
		{
			$userZone   = '';
			$siteOffset = '';
		}

		return self::viewerZone($userZone, $siteOffset);
	}

	/**
	 * Y-m-d from a DATE value, or empty.
	 *
	 * @param   string|null  $value  Stored day.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function calendarDay(?string $value): string
	{
		if ($value === null)
		{
			return '';
		}

		$value = trim($value);

		if ($value === '' || str_starts_with($value, '0000-00-00'))
		{
			return '';
		}

		if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $match) !== 1)
		{
			return '';
		}

		$day    = $match[1];
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
		$errors = \DateTimeImmutable::getLastErrors();

		if (!$parsed instanceof \DateTimeImmutable)
		{
			return '';
		}

		if (\is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
		{
			return '';
		}

		if ($parsed->format('Y-m-d') !== $day)
		{
			return '';
		}

		return $day;
	}

	/**
	 * Viewer calendar day of a UTC check-in, or empty.
	 *
	 * @param   string|null  $checkInUtc  Stored check_in_time.
	 * @param   string       $viewerZone  Known IANA zone.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function checkInDay(?string $checkInUtc, string $viewerZone): string
	{
		if ($checkInUtc === null)
		{
			return '';
		}

		$checkInUtc = trim($checkInUtc);

		if ($checkInUtc === '' || str_starts_with($checkInUtc, '0000-00-00'))
		{
			return '';
		}

		if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $checkInUtc, $match) !== 1)
		{
			return '';
		}

		try
		{
			$utc = new \DateTimeImmutable($match[1] . ' ' . $match[2], new \DateTimeZone('UTC'));
		}
		catch (\Throwable)
		{
			return '';
		}

		return $utc->setTimezone(new \DateTimeZone($viewerZone))->format('Y-m-d');
	}

	/**
	 * Known IANA zone, or an empty string.
	 *
	 * @param   string  $zone  Candidate zone name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function knownZone(string $zone): string
	{
		$zone = trim($zone);

		if ($zone === '')
		{
			return '';
		}

		try
		{
			return (new \DateTimeZone($zone))->getName();
		}
		catch (\Throwable)
		{
			return '';
		}
	}
}
