<?php

/**
 * Wendfold - When the event Return row may render.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Ride-or-multi-day gate for Departure/Return display.
 *
 * Multi-day overnight saves force is_riding_event=0. Meetings and other
 * same-day non-ride events must not show a stale Return time.
 *
 * @since  1.0.0
 */
abstract class EventReturnDisplay
{
	/**
	 * Whether Return may show for this event.
	 *
	 * True for rides (is_riding_event) and for multi-day events whose end date
	 * differs from their start date (falling back to event_date for start).
	 *
	 * @param   boolean  $isRidingEvent  Stored is_riding_event flag.
	 * @param   string   $startDate      Stored start_date (may be empty).
	 * @param   string   $endDate        Stored end_date (may be empty).
	 * @param   string   $eventDate      Stored event_date.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function shouldShow(
		bool $isRidingEvent,
		string $startDate,
		string $endDate,
		string $eventDate
	): bool
	{
		if ($isRidingEvent)
		{
			return true;
		}

		$start = $startDate !== '' ? $startDate : $eventDate;

		return $endDate !== '' && $endDate !== $start;
	}
}
