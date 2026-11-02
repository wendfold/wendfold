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
 * Resolves the configurable member-number column label for admin lists.
 *
 * @since  1.0.0
 */
final class MemberIdLabel
{
	/**
	 * Prefer the Options member_id_label; fall back to a translated default.
	 *
	 * @param   string  $configured         Raw Options value (may be blank).
	 * @param   string  $languageFallback   Already-translated COM_WENDFOLD_MEMBER_NUMBER.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function forListHeader(string $configured, string $languageFallback): string
	{
		$configured = trim($configured);

		return $configured !== '' ? $configured : $languageFallback;
	}

	/**
	 * Build a grid.sort title that is never passed through Text::_().
	 *
	 * Joomla Grid::sort treats a title starting with "&lt;" as raw HTML, so the
	 * escaped label is shown as-is instead of being looked up as a language key.
	 *
	 * @param   string  $label  Plain-text column header.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function gridSortTitle(string $label): string
	{
		return '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
	}
}
