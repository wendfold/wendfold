<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Attendance roster status badge decision for a checked-in member.
 *
 * @since  1.0.0
 */
final class AttendanceRosterStatus
{
	/**
	 * Which status badge the admin attendance roster should show.
	 *
	 * Waiver takes priority over unpaid dues. A signed waiver with dues paid
	 * yields no badge.
	 *
	 * @param   mixed  $waiverSigned  Profile waiver_signed value (0/1 or null).
	 * @param   mixed  $duesPaid      Profile dues_paid value (0/1 or null).
	 *
	 * @return  string|null  'waiver', 'dues', or null when neither badge applies.
	 *
	 * @since   1.0.0
	 */
	public static function badgeCode(mixed $waiverSigned, mixed $duesPaid): ?string
	{
		if (empty($waiverSigned))
		{
			return 'waiver';
		}

		if (empty($duesPaid))
		{
			return 'dues';
		}

		return null;
	}
}
