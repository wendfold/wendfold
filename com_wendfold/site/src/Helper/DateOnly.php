<?php

/**
 * Wendfold - Date-only display.
 *
 * A SQL DATE, or a Y-m-d string, is a calendar day. It is not an instant.
 * The Joomla date helper reads that text as midnight UTC. The default user
 * zone, and the false flag (the site offset), then move it. West of UTC the
 * shown day is the day before.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

use Joomla\CMS\HTML\HTMLHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Formats a calendar day without moving it into another time zone.
 *
 * @since  1.0.0
 */
abstract class DateOnly
{
	/**
	 * Format a stored calendar day.
	 *
	 * Accepts Y-m-d, or Y-m-d 00:00:00 from a DATE column. The format is a
	 * PHP date string or a language key such as DATE_FORMAT_LC3. Null, an
	 * empty string, and a zero date (0000-00-00) return an empty string.
	 *
	 * Joomla treats false as the site offset, which still shifts a UTC
	 * midnight. null is the argument that does not convert.
	 *
	 * @param   string|null  $value   Stored day, or empty.
	 * @param   string       $format  PHP date format or a language key.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function format(?string $value, string $format): string
	{
		$day = self::calendarDay($value);

		if ($day === '')
		{
			return '';
		}

		return (string) HTMLHelper::_('date', $day, $format, null);
	}

	/**
	 * Format a stored wall-clock time.
	 *
	 * A SQL TIME value such as kickstands-up is a clock reading, not an
	 * instant. Accepts HH:MM or HH:MM:SS. Null and a blank string return an
	 * empty string. The format is the same PHP time string the screens
	 * already use, for example g:i A.
	 *
	 * The value is placed on a fixed UTC day and printed with no zone
	 * conversion, so 08:00 stays 08:00 in every zone.
	 *
	 * @param   string|null  $value   Stored time, or empty.
	 * @param   string       $format  PHP time format.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function time(?string $value, string $format): string
	{
		$clock = self::clockTime($value);

		if ($clock === '')
		{
			return '';
		}

		return (string) HTMLHelper::_('date', '1970-01-01 ' . $clock, $format, null);
	}

	/**
	 * Y-m-d when the value is a calendar day, otherwise an empty string.
	 *
	 * A clock time other than midnight is not a date-only value.
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

		if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T]00:00:00(?:\.0+)?)?$/', $value, $match) !== 1)
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
	 * HH:MM:SS when the value is a clock time, otherwise an empty string.
	 *
	 * Null and blank are empty. 00:00:00 is a real midnight and is kept,
	 * matching the screens that use empty() on the stored time.
	 *
	 * @param   string|null  $value  Stored time.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function clockTime(?string $value): string
	{
		if ($value === null)
		{
			return '';
		}

		$value = trim($value);

		if ($value === '')
		{
			return '';
		}

		$matched = preg_match(
			'/^(\d{1,2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/',
			$value,
			$match
		);

		if ($matched !== 1)
		{
			return '';
		}

		$hour   = (int) $match[1];
		$minute = (int) $match[2];
		$second = isset($match[3]) ? (int) $match[3] : 0;

		if ($hour > 23 || $minute > 59 || $second > 59)
		{
			return '';
		}

		return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
	}
}
