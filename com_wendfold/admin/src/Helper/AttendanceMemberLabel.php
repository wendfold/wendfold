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

use Joomla\CMS\Language\Text;

/**
 * Display label for a profile in the attendance Add Member dropdown.
 *
 * @since  1.0.0
 */
final class AttendanceMemberLabel
{
	/**
	 * Prefer the Joomla user name, then preferred name, then member number,
	 * then COM_WENDFOLD_ATTENDANCE_MEMBER_FALLBACK. Never returns null or blank.
	 *
	 * @param   string|null  $userName       Linked #__users.name, if any.
	 * @param   string|null  $preferredName  Profile preferred_name.
	 * @param   string|null  $memberNumber   Profile member_number.
	 * @param   integer      $profileId      Profile primary key.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function resolve(
		?string $userName,
		?string $preferredName,
		?string $memberNumber,
		int $profileId
	): string
	{
		foreach ([$userName, $preferredName, $memberNumber] as $candidate)
		{
			$trimmed = trim((string) ($candidate ?? ''));

			if ($trimmed !== '')
			{
				return $trimmed;
			}
		}

		return Text::sprintf('COM_WENDFOLD_ATTENDANCE_MEMBER_FALLBACK', max(0, $profileId));
	}
}
