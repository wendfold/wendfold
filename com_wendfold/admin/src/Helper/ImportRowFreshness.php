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
 * Decide whether a profile changed after the import preview snapshot.
 *
 * @since  1.0.0
 */
final class ImportRowFreshness
{
	/**
	 * True when the live modified stamp differs from the preview snapshot.
	 *
	 * @param   string|null  $previewModified  modified value when the plan was built.
	 * @param   string|null  $currentModified  modified value from SELECT … FOR UPDATE.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function changedSincePreview(?string $previewModified, ?string $currentModified): bool
	{
		return self::normalise($previewModified) !== self::normalise($currentModified);
	}

	/**
	 * @param   string|null  $value  Datetime string or null.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function normalise(?string $value): string
	{
		$value = trim((string) $value);

		if ($value === '' || str_starts_with($value, '0000-00-00'))
		{
			return '';
		}

		return $value;
	}
}
