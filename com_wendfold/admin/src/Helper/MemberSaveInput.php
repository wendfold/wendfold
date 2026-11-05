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
 * Rules for an administrator member save that do not touch the filesystem.
 *
 * Photos are edited by the member on the site. A posted photo value is dropped
 * here so the parent save cannot store it. A posted id that names a different
 * record is rejected by the controller before that save runs.
 *
 * @since  1.0.0
 */
final class MemberSaveInput
{
	/**
	 * Whether jform posted an id other than the record this save will write.
	 *
	 * A missing id is not a mismatch. The administrator form does not send one.
	 *
	 * @param   integer  $recordId  Record id from the request, or 0 for a new member.
	 * @param   array    $jform     Posted jform array.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function idMismatches(int $recordId, array $jform): bool
	{
		if (!\array_key_exists('id', $jform))
		{
			return false;
		}

		$posted = $jform['id'];

		if (!\is_scalar($posted))
		{
			return true;
		}

		return (int) $posted !== $recordId;
	}

	/**
	 * Remove posted photo fields.
	 *
	 * An existing row keeps the stored photo because the field is absent.
	 * A new row stores an empty photo.
	 *
	 * @param   array    $jform     Posted jform array.
	 * @param   integer  $recordId  Record id from the request, or 0 for a new member.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public static function withoutPhoto(array $jform, int $recordId): array
	{
		foreach (array_keys($jform) as $field)
		{
			if ($field === 'photo' || str_starts_with((string) $field, 'photo_'))
			{
				unset($jform[$field]);
			}
		}

		if ($recordId === 0)
		{
			$jform['photo'] = '';
		}

		return $jform;
	}
}
