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
 * Matches Joomla form filter="string" / InputFilter STRING semantics.
 *
 * Decodes HTML entities, then strips tags, so text fields that bypass the
 * form filter still cannot store markup.
 *
 * @since  1.0.0
 */
final class InputStringFilter
{
	/**
	 * Clean one string the way filter="string" does.
	 *
	 * @param   string  $value  Raw or partially filtered text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function clean(string $value): string
	{
		$value = html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

		return trim(strip_tags($value));
	}
}
