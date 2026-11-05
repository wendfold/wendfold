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

\defined('_JEXEC') or die;

/**
 * Refuse deletion of a lookup row that another table still points at.
 *
 * Unpublish is the way to retire a row. The lookup models and tables call
 * this before they delete.
 *
 * @since  1.0.0
 */
final class LookupDeleteGuard
{
	/**
	 * Language key for the refusal, or null when the row may be deleted.
	 *
	 * @param   integer  $usageCount  Rows that reference the lookup.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function messageKey(int $usageCount): ?string
	{
		if ($usageCount > 0)
		{
			return 'COM_WENDFOLD_LOOKUP_DELETE_IN_USE';
		}

		return null;
	}

	/**
	 * Whether the row must stay.
	 *
	 * @param   integer  $usageCount  Rows that reference the lookup.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isBlocked(int $usageCount): bool
	{
		return self::messageKey($usageCount) !== null;
	}
}
