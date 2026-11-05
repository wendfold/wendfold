<?php

/**
 * Wendfold - Chapter name/number for site page-header subtitles.
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
 * Builds the page-header subtitle from chapter_name and chapter_number.
 *
 * @since  1.0.0
 */
abstract class ChapterSubtitle
{
	/**
	 * Format a chapter subtitle for the shared page header.
	 *
	 * When the name is empty the result is empty (even if a number is set).
	 * When both are set the result is "#number name". When only the name is
	 * set the result is the name alone. Callers escape via page_header.php.
	 *
	 * @param   string  $name    Trimmed chapter_name option.
	 * @param   string  $number  Trimmed chapter_number option.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function format(string $name, string $number): string
	{
		$name = trim($name);

		if ($name === '')
		{
			return '';
		}

		$number = trim($number);

		if ($number === '')
		{
			return $name;
		}

		return '#' . $number . ' ' . $name;
	}
}
