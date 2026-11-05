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
 * Map a roster CSV membership cell onto a lookup import code.
 *
 * The seeded codes are Full, Associate, and Life. A chapter can add more
 * codes. The importer stores the code here and resolves the row id from
 * #__wendfold_membership_types.import_code. A life-member flag selects the
 * published type that is exempt from renewal.
 *
 * @since  1.0.0
 */
final class MembershipImport
{
	/**
	 * Marker the parser stores when the life-member column is yes.
	 *
	 * The sync model treats this as "use the exempt type", not as an import code.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const LIFE_FLAG = '__life__';

	/**
	 * Canonical import code for a CSV cell, or null when the cell is unusable.
	 *
	 * Familiar words and the old 1/2/3 numbers map to the seeded codes. Any
	 * other short value is kept so it can match a chapter-defined import code.
	 *
	 * @param   string  $raw  Cell text.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function canonicalCode(string $raw): ?string
	{
		$trimmed = trim($raw);

		if ($trimmed === '' || \strlen($trimmed) > 50)
		{
			return null;
		}

		$key = preg_replace('/[^a-z0-9]+/', '', strtolower($trimmed)) ?? '';

		if (\in_array($key, ['1', 'full', 'fullmember'], true))
		{
			return 'Full';
		}

		if (\in_array($key, ['2', 'associate', 'associatemember', 'assoc'], true))
		{
			return 'Associate';
		}

		if (\in_array($key, ['3', 'life', 'lifetime', 'lifemember'], true))
		{
			return 'Life';
		}

		return $trimmed;
	}

	/**
	 * Rows whose membership code did not match a published type.
	 *
	 * The import still writes the rest of the row. The caller shows one
	 * warning per returned row, the same way other per-row messages are
	 * listed. A matched id, including a chapter-defined import code, is
	 * not a warning. The life-member flag uses the label Life.
	 *
	 * @param   array  $rows         Parser rows with line, has_type, and membership_type.
	 * @param   array  $resolvedIds  Import code or life flag mapped to a type id. 0 is unknown.
	 *
	 * @return  array<int, array{line: int, code: string, label: string}>
	 *
	 * @since   1.0.0
	 */
	public static function unresolvedRows(array $rows, array $resolvedIds): array
	{
		$warnings = [];

		foreach ($rows as $row)
		{
			if (empty($row['has_type']))
			{
				continue;
			}

			$code = trim((string) ($row['membership_type'] ?? ''));

			if ($code === '')
			{
				continue;
			}

			if ((int) ($resolvedIds[$code] ?? 0) > 0)
			{
				continue;
			}

			$warnings[] = [
				'line'  => (int) ($row['line'] ?? 0),
				'code'  => $code,
				'label' => $code === self::LIFE_FLAG ? 'Life' : $code,
			];
		}

		return $warnings;
	}
}
