<?php

/**
 * Wendfold - Profile-edit motorcycle subform rules.
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
 * Blank-row detection, validation, and ownership-aware change planning for
 * the member profile motorcycle subform.
 *
 * @since  1.0.0
 */
final class ProfileMotorcycleInput
{
	/**
	 * Visible subform fields. A new row is blank when every one is empty.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	public const VISIBLE_FIELDS = [
		'year',
		'make',
		'model',
		'vin',
		'color',
		'nickname',
	];

	/**
	 * Lowest accepted model year for a new or changed year.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const YEAR_MIN = 1885;

	/**
	 * Required VIN length when a VIN is new or changed.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const VIN_LENGTH = 17;

	/**
	 * Maximum VIN length stored in the database / admin form.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const VIN_MAX_LENGTH = 30;

	/**
	 * Language key for an out-of-range or non-integer year.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MSG_YEAR = 'COM_WENDFOLD_PROFILEEDIT_MOTO_YEAR_INVALID';

	/**
	 * Language key for an invalid VIN.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MSG_VIN = 'COM_WENDFOLD_PROFILEEDIT_MOTO_VIN_INVALID';

	/**
	 * English sprintf format for the year validation message.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const YEAR_MESSAGE_FORMAT = 'Motorcycle year must be between %d and %d.';

	/**
	 * English VIN validation message.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const VIN_MESSAGE = 'VIN must be 17 characters, letters and digits, without I, O or Q.';

	/**
	 * Highest accepted model year: current calendar year + 1.
	 *
	 * @param   \DateTimeInterface|null  $now  Frozen clock for tests; null uses SiteTime.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function yearMax(?\DateTimeInterface $now = null): int
	{
		if ($now !== null)
		{
			return (int) $now->format('Y') + 1;
		}

		return SiteTime::fromApplication()->year() + 1;
	}

	/**
	 * Human-readable year validation message for the given clock.
	 *
	 * @param   \DateTimeInterface|null  $now  Frozen clock for tests; null uses SiteTime.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function yearMessage(?\DateTimeInterface $now = null): string
	{
		return sprintf(self::YEAR_MESSAGE_FORMAT, self::YEAR_MIN, self::yearMax($now));
	}

	/**
	 * Human-readable VIN validation message.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function vinMessage(): string
	{
		return self::VIN_MESSAGE;
	}

	/**
	 * Whether every visible subform field is empty after trimming.
	 *
	 * @param   array  $row  One posted motorcycle row.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isBlankRow(array $row): bool
	{
		foreach (self::VISIBLE_FIELDS as $field)
		{
			$value = $row[$field] ?? '';

			if (!\is_scalar($value))
			{
				return false;
			}

			if (trim((string) $value) !== '')
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Id to mark primary when remaining bikes have none, or null when OK.
	 *
	 * @param   int[]  $remainingIds  Owned motorcycle ids still present after the plan.
	 * @param   int[]  $primaryIds    Subset of those ids that already have is_primary = 1.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public static function primaryIdToAssign(array $remainingIds, array $primaryIds): ?int
	{
		$remaining = [];

		foreach ($remainingIds as $id)
		{
			$id = (int) $id;

			if ($id > 0)
			{
				$remaining[$id] = $id;
			}
		}

		if ($remaining === [])
		{
			return null;
		}

		foreach ($primaryIds as $primaryId)
		{
			$id = (int) $primaryId;

			if (isset($remaining[$id]))
			{
				return null;
			}
		}

		$sorted = array_values($remaining);
		sort($sorted, SORT_NUMERIC);

		return $sorted[0];
	}

	/**
	 * Normalise and validate one posted row.
	 *
	 * Returns null when the row is blank. Year and VIN rules apply only when
	 * the value is new or differs from the stored owned row.
	 *
	 * @param   array                    $row     One posted motorcycle row.
	 * @param   \DateTimeInterface|null  $now     Frozen clock for year max; null uses SiteTime.
	 * @param   array|null               $stored  Stored year/vin for an owned id, when present.
	 *
	 * @return  array|null
	 *
	 * @throws  \InvalidArgumentException
	 *
	 * @since   1.0.0
	 */
	public static function normalizeRow(
		array $row,
		?\DateTimeInterface $now = null,
		?array $stored = null
	): ?array
	{
		if (self::isBlankRow($row))
		{
			return null;
		}

		$storedYear = \array_key_exists('year', (array) $stored) ? $stored['year'] : null;
		$storedVin  = \array_key_exists('vin', (array) $stored) ? (string) $stored['vin'] : null;

		if ($storedYear !== null)
		{
			$storedYear = (int) $storedYear;
		}

		$year = self::normalizeYear($row['year'] ?? '', $now, $storedYear);
		$vin  = self::normalizeVin($row['vin'] ?? '', $storedVin);

		return [
			'id'       => max(0, (int) ($row['id'] ?? 0)),
			'year'     => $year,
			'make'     => self::trimField($row['make'] ?? '', 50),
			'model'    => self::trimField($row['model'] ?? '', 100),
			'vin'      => $vin,
			'color'    => self::trimField($row['color'] ?? '', 50),
			'nickname' => self::trimField($row['nickname'] ?? '', 50),
		];
	}

	/**
	 * Build insert, update, and delete lists for one profile save.
	 *
	 * New blank rows are dropped. Owned rows are deleted only when their
	 * Remove checkbox is posted. An owned blank row is kept without updates
	 * so non-posted columns such as notes stay untouched. Year and VIN are
	 * validated only when new or changed from $storedById.
	 *
	 * @param   int[]                               $ownedIds     Motorcycle ids that belong to the member.
	 * @param   array                               $postedRows   Posted subform rows (may include remove).
	 * @param   \DateTimeInterface|null             $now          Frozen clock for year max; null uses SiteTime.
	 * @param   array<int, array<string, mixed>>    $storedById   Stored year/vin keyed by motorcycle id.
	 *
	 * @return  array{insert: array<int, array>, update: array<int, array>, delete: int[]}
	 *
	 * @throws  \InvalidArgumentException  When a row fails validation or names a foreign id.
	 *
	 * @since   1.0.0
	 */
	public static function plan(
		array $ownedIds,
		array $postedRows,
		?\DateTimeInterface $now = null,
		array $storedById = []
	): array
	{
		$owned = [];

		foreach ($ownedIds as $ownedId)
		{
			$id = (int) $ownedId;

			if ($id > 0)
			{
				$owned[$id] = $id;
			}
		}

		$insert = [];
		$update = [];
		$delete = [];
		$seen   = [];

		foreach ($postedRows as $postedRow)
		{
			if (!\is_array($postedRow))
			{
				continue;
			}

			$id = max(0, (int) ($postedRow['id'] ?? 0));

			if ($id > 0)
			{
				if (!isset($owned[$id]))
				{
					throw new \InvalidArgumentException('Foreign motorcycle id.');
				}

				if (!empty($postedRow['remove']))
				{
					$delete[$id] = $id;
					$seen[$id]   = $id;
					continue;
				}

				$seen[$id] = $id;

				if (self::isBlankRow($postedRow))
				{
					continue;
				}

				$stored     = $storedById[$id] ?? null;
				$normalized = self::normalizeRow($postedRow, $now, \is_array($stored) ? $stored : null);

				if ($normalized === null)
				{
					continue;
				}

				$update[] = $normalized;
				continue;
			}

			if (!empty($postedRow['remove']) || self::isBlankRow($postedRow))
			{
				continue;
			}

			$normalized = self::normalizeRow($postedRow, $now, null);

			if ($normalized === null)
			{
				continue;
			}

			$insert[] = $normalized;
		}

		return [
			'insert' => $insert,
			'update' => $update,
			'delete' => array_values($delete),
		];
	}

	/**
	 * Parse a year to an int in range, or null when blank.
	 *
	 * An unchanged stored year is kept without re-checking the range.
	 *
	 * @param   mixed                    $value       Posted year.
	 * @param   \DateTimeInterface|null  $now         Frozen clock for year max; null uses SiteTime.
	 * @param   int|null                 $storedYear  Stored year for an owned row, when present.
	 *
	 * @return  integer|null
	 *
	 * @throws  \InvalidArgumentException
	 *
	 * @since   1.0.0
	 */
	public static function normalizeYear(
		mixed $value,
		?\DateTimeInterface $now = null,
		?int $storedYear = null
	): ?int
	{
		if (!\is_scalar($value))
		{
			throw new \InvalidArgumentException(self::MSG_YEAR);
		}

		$raw = trim((string) $value);

		if ($raw === '')
		{
			return null;
		}

		if (!preg_match('/^-?\d+$/', $raw))
		{
			throw new \InvalidArgumentException(self::MSG_YEAR);
		}

		$year = (int) $raw;

		if ($storedYear !== null && $year === $storedYear)
		{
			return $year;
		}

		$max = self::yearMax($now);

		if ($year < self::YEAR_MIN || $year > $max)
		{
			throw new \InvalidArgumentException(self::MSG_YEAR);
		}

		return $year;
	}

	/**
	 * Parse a VIN: empty, unchanged legacy (up to 30 chars), or a new 17-char VIN.
	 *
	 * @param   mixed        $value      Posted VIN.
	 * @param   string|null  $storedVin  Stored VIN for an owned row, when present.
	 *
	 * @return  string
	 *
	 * @throws  \InvalidArgumentException
	 *
	 * @since   1.0.0
	 */
	public static function normalizeVin(mixed $value, ?string $storedVin = null): string
	{
		if (!\is_scalar($value))
		{
			throw new \InvalidArgumentException(self::MSG_VIN);
		}

		$vin = strtoupper(trim((string) $value));

		if ($vin === '')
		{
			return '';
		}

		if ($storedVin !== null && $vin === strtoupper(trim($storedVin)))
		{
			if (\strlen($vin) > self::VIN_MAX_LENGTH)
			{
				throw new \InvalidArgumentException(self::MSG_VIN);
			}

			return $vin;
		}

		if (\strlen($vin) !== self::VIN_LENGTH)
		{
			throw new \InvalidArgumentException(self::MSG_VIN);
		}

		if (!preg_match('/^[A-HJ-NPR-Z0-9]+$/', $vin))
		{
			throw new \InvalidArgumentException(self::MSG_VIN);
		}

		return $vin;
	}

	/**
	 * Trim a string field and cap its length.
	 *
	 * @param   mixed  $value   Posted value.
	 * @param   int    $maxLen  Maximum characters to keep.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function trimField(mixed $value, int $maxLen): string
	{
		if (!\is_scalar($value))
		{
			return '';
		}

		$text = trim((string) $value);

		if ($text === '')
		{
			return '';
		}

		if (\function_exists('mb_substr'))
		{
			return mb_substr($text, 0, $maxLen);
		}

		return substr($text, 0, $maxLen);
	}
}
