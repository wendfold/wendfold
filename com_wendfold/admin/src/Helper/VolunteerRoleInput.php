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
 * Filters volunteer role subform rows when saving an event.
 *
 * Role text fields declare filter="string" in volunteerrole.xml. When the
 * model reads roles from a raw post array, those filters never run; this
 * helper applies the same string cleaning before rows are written.
 *
 * @since  1.0.0
 */
final class VolunteerRoleInput
{
	/**
	 * Text field names that use filter="string" on the role subform.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private const STRING_FIELDS = [
		'title',
		'description',
		'shift_label',
		'start_time',
		'end_time',
	];

	/**
	 * Apply string filters to each role row's text fields.
	 *
	 * @param   array<int, array<string, mixed>>  $rows  Normalised role rows.
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	public static function filterRows(array $rows): array
	{
		$out = [];

		foreach ($rows as $row)
		{
			foreach (self::STRING_FIELDS as $field)
			{
				if (!\array_key_exists($field, $row))
				{
					continue;
				}

				$row[$field] = InputStringFilter::clean((string) $row[$field]);
			}

			$out[] = $row;
		}

		return $out;
	}
}
