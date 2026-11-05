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

/**
 * Pure catch-up rules for renewal reminder offsets.
 *
 * An offset is due when the expiry is still ahead, the site day is on or after
 * the offset day, and that (member, expiry, offset) has not been logged.
 * Only the most urgent due offset is sent; other due offsets are skipped.
 *
 * Reminders only move forward toward expiry (60, then 30, then due). Once a
 * nearer stage is sent, every farther stage for that cycle counts as done —
 * never send a stage farther out than one already sent.
 *
 * Legacy profile columns reminder_60_sent / reminder_30_sent count as sent for
 * the current cycle when the stored date is after the cycle start (the
 * previous renewal, renewal - 365). The current renewal date is not used as
 * a floor or ceiling, so moving expiry earlier or later mid-cycle never
 * reopens a stage. Flags from a previous cycle do not.
 *
 * @since  1.0.0
 */
final class ReminderPlanner
{
	/**
	 * RSVP response: going.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public const RSVP_GOING = 1;

	/**
	 * RSVP response: maybe.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public const RSVP_MAYBE = 2;

	/**
	 * Reminder log key for a renewal offset.
	 *
	 * @param   integer  $days  Configured days before expiry.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function renewalKey(int $days): string
	{
		return 'renewal-' . $days;
	}

	/**
	 * Reminder log key for an event reminder.
	 *
	 * @param   integer  $eventId     Event id.
	 * @param   integer  $offsetDays  Lead time in days (0 = day of event).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function eventKey(int $eventId, int $offsetDays = 0): string
	{
		return 'event-' . $eventId . '-' . $offsetDays;
	}

	/**
	 * Whether an RSVP response should receive an event reminder.
	 *
	 * Going (1) and maybe (2) only. Not going and waitlisted are excluded.
	 *
	 * @param   integer  $response  #__wendfold_rsvps.response value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function receivesEventReminder(int $response): bool
	{
		return $response === self::RSVP_GOING || $response === self::RSVP_MAYBE;
	}

	/**
	 * Whether an event's published state is eligible for day-of reminders.
	 *
	 * Aligns with MetricService attendanceEntryPublished for live mail:
	 * unpublished (0) and trashed (-2) never remind. Only published (1)
	 * events send. Archived (2) still counts for credits but is not a
	 * day-of reminder target.
	 *
	 * @param   integer  $published  #__wendfold_events.published value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function eventEligibleForReminder(int $published): bool
	{
		return $published === 1;
	}

	/**
	 * Merge reminder_log keys with legacy profile sent dates for this expiry.
	 *
	 * Each stage column counts as sent for this cycle when its date is after
	 * the cycle start (previous renewal). No band relative to the current
	 * renewal — moving expiry mid-cycle must not reopen a stage.
	 *
	 * @param   string  $renewalDate  Member expiry Y-m-d.
	 * @param   array   $windows      Result of ComponentOptions::reminderWindows().
	 * @param   array   $loggedKeys   Keys already in the reminder log for this expiry.
	 * @param   array   $legacySent   Map of profile column name => Y-m-d|null.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public static function loggedKeysWithLegacy(
		string $renewalDate,
		array $windows,
		array $loggedKeys,
		array $legacySent
	): array
	{
		$keys = [];

		foreach ($loggedKeys as $key)
		{
			$keys[(string) $key] = true;
		}

		if ($renewalDate === '')
		{
			return array_keys($keys);
		}

		$previousRenewal = self::shiftDate($renewalDate, -365);

		if ($previousRenewal === null)
		{
			return array_keys($keys);
		}

		foreach ($windows as $window)
		{
			$days   = (int) ($window['days'] ?? 0);
			$column = (string) ($window['column'] ?? '');

			if ($days < 1 || $column === '')
			{
				continue;
			}

			$sentDate = self::calendarDay($legacySent[$column] ?? null);

			if ($sentDate === '')
			{
				continue;
			}

			// After the previous cycle's renewal — never reuse last year's flag.
			if (strcmp($sentDate, $previousRenewal) <= 0)
			{
				continue;
			}

			$keys[self::renewalKey($days)] = true;
		}

		return self::withFartherStagesDone($windows, array_keys($keys));
	}

	/**
	 * Mark farther (larger-day) stages done once a nearer stage is logged.
	 *
	 * @param   array  $windows     Result of ComponentOptions::reminderWindows().
	 * @param   array  $loggedKeys  Keys already logged for this expiry.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public static function withFartherStagesDone(array $windows, array $loggedKeys): array
	{
		$keys = [];

		foreach ($loggedKeys as $key)
		{
			$keys[(string) $key] = true;
		}

		$nearestSent = self::nearestSentDays($windows, $keys);

		if ($nearestSent === null)
		{
			return array_keys($keys);
		}

		foreach ($windows as $window)
		{
			$days = (int) ($window['days'] ?? 0);

			if ($days > $nearestSent)
			{
				$keys[self::renewalKey($days)] = true;
			}
		}

		return array_keys($keys);
	}

	/**
	 * Decide which renewal offset to send and which due offsets to skip.
	 *
	 * @param   string  $today        Site-local Y-m-d.
	 * @param   string  $renewalDate  Member expiry Y-m-d.
	 * @param   array   $windows      Result of ComponentOptions::reminderWindows().
	 * @param   array   $loggedKeys   Keys already logged (reminder_log and/or legacy).
	 *
	 * @return  array{send: int, skip: array<int, int>}|null  Null when nothing is due.
	 *
	 * @since   1.0.0
	 */
	public static function renewalPlan(string $today, string $renewalDate, array $windows, array $loggedKeys): ?array
	{
		if ($renewalDate === '' || $today === '' || strcmp($renewalDate, $today) <= 0)
		{
			return null;
		}

		$logged = [];

		foreach (self::withFartherStagesDone($windows, $loggedKeys) as $key)
		{
			$logged[(string) $key] = true;
		}

		$due = [];

		foreach ($windows as $window)
		{
			$days = (int) $window['days'];

			if ($days < 1)
			{
				continue;
			}

			$key = self::renewalKey($days);

			if (isset($logged[$key]))
			{
				continue;
			}

			$windowStart = self::shiftDate($renewalDate, -$days);

			if ($windowStart === null)
			{
				continue;
			}

			// Due when renewal - N <= today < renewal.
			if (strcmp($today, $windowStart) >= 0)
			{
				$due[$days] = true;
			}
		}

		if ($due === [])
		{
			return null;
		}

		$send = min(array_keys($due));
		$skip = [];

		foreach (array_keys($due) as $days)
		{
			if ($days !== $send)
			{
				$skip[] = $days;
			}
		}

		sort($skip);

		return [
			'send' => $send,
			'skip' => $skip,
		];
	}

	/**
	 * Smallest (nearest-to-expiry) day offset already logged, or null.
	 *
	 * @param   array                $windows  Reminder windows.
	 * @param   array<string, true>  $logged   Logged key set.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private static function nearestSentDays(array $windows, array $logged): ?int
	{
		$sentDays = [];

		foreach ($windows as $window)
		{
			$days = (int) ($window['days'] ?? 0);

			if ($days >= 1 && isset($logged[self::renewalKey($days)]))
			{
				$sentDays[] = $days;
			}
		}

		return $sentDays === [] ? null : min($sentDays);
	}

	/**
	 * Normalise a stored calendar day to Y-m-d, or empty when missing.
	 *
	 * @param   mixed  $value  Date string or null.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function calendarDay(mixed $value): string
	{
		if (!\is_string($value))
		{
			return '';
		}

		$trimmed = trim($value);

		if ($trimmed === '' || str_starts_with($trimmed, '0000-00-00'))
		{
			return '';
		}

		return substr($trimmed, 0, 10);
	}

	/**
	 * Shift a Y-m-d calendar date by a whole number of days.
	 *
	 * @param   string   $date  Y-m-d.
	 * @param   integer  $days  Positive or negative day count.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private static function shiftDate(string $date, int $days): ?string
	{
		try
		{
			$dt = new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone('UTC'));
		}
		catch (\Exception $e)
		{
			return null;
		}

		return $dt->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
	}
}
