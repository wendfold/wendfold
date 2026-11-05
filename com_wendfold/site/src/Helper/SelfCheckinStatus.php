<?php

/**
 * Wendfold - Event statuses that allow QR self check-in.
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
 * Shared open-status rule for the site event QR and CheckinModel::checkIn().
 *
 * Only Scheduled (1) and Confirmed (4) accept self check-in. Completed,
 * cancelled, weather-watch, and every other status refuse it.
 *
 * @since  1.0.0
 */
abstract class SelfCheckinStatus
{
	/**
	 * Scheduled.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const SCHEDULED = 1;

	/**
	 * Confirmed.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const CONFIRMED = 4;

	/**
	 * Statuses that accept a self check-in.
	 *
	 * @return  int[]
	 *
	 * @since   1.0.0
	 */
	public static function allowed(): array
	{
		return [self::SCHEDULED, self::CONFIRMED];
	}

	/**
	 * Whether this event status may receive a self check-in.
	 *
	 * @param   integer  $status  Stored event status.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function allows(int $status): bool
	{
		return \in_array($status, self::allowed(), true);
	}
}
