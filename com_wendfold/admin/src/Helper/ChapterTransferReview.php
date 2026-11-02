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

/**
 * Decides whether a chapter-transfer review may change a profile.
 *
 * Keep and Remove from Roster update a row only while it is flagged. A
 * second request sees the flag already clear, so it does not write another
 * sync log row.
 *
 * @since  1.0.0
 */
final class ChapterTransferReview
{
	/**
	 * Whether this profile is still waiting for a chapter-transfer decision.
	 *
	 * @param   object  $profile  Profile row. It may omit the flag.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isOpen(object $profile): bool
	{
		$values = get_object_vars($profile);
		$flagged = $values['chapter_switch_flagged'] ?? 0;

		return (int) $flagged === 1;
	}

	/**
	 * Whether an update changed a row and may be written to the sync log.
	 *
	 * @param   integer  $affectedRows  Rows the database reported.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function wrote(int $affectedRows): bool
	{
		return $affectedRows > 0;
	}
}
