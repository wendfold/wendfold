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
 * Writes one CSV row for an administrator report download.
 *
 * Text that starts with a formula trigger is prefixed with a tab. fputcsv()
 * then quotes the field, which is the Excel-resistant form in the OWASP CSV
 * Injection guidance. An integer, a float, or a plain decimal string is
 * written unchanged, so a negative amount or count stays a number.
 *
 * The separator, enclosure, and escape are passed explicitly. An empty
 * escape matches CsvRosterParser: a quote inside a field is doubled.
 * PHP 8.4 deprecates omitting the escape argument.
 *
 * @since  1.0.0
 */
final class CsvWriter
{
	/**
	 * Field separator.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const SEPARATOR = ',';

	/**
	 * Characters wrapped around a field that needs quoting.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ENCLOSURE = '"';

	/**
	 * Escape argument for fputcsv(). Empty means doubled quotes.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ESCAPE = '';

	/**
	 * First characters that a spreadsheet may treat as a formula.
	 *
	 * Tab, CR, and LF are included with the ASCII formula signs. The four
	 * full-width signs are the ones OWASP lists for some locales.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const FORMULA_PREFIXES = [
		'=',
		'+',
		'-',
		'@',
		"\t",
		"\r",
		"\n",
		'＝',
		'＋',
		'－',
		'＠',
	];

	/**
	 * Write one row.
	 *
	 * @param   mixed  $stream  Open write stream, such as php://output.
	 * @param   array  $row     Cell values in column order.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function writeRow(mixed $stream, array $row): void
	{
		if (!\is_resource($stream))
		{
			throw new \InvalidArgumentException('CSV output must be an open stream.');
		}

		$fields = [];

		foreach ($row as $value)
		{
			$fields[] = self::formatCell($value);
		}

		fputcsv($stream, $fields, self::SEPARATOR, self::ENCLOSURE, self::ESCAPE);
	}

	/**
	 * Prepare one cell.
	 *
	 * Integers and floats are returned as they are, including negatives.
	 * A string that is only a plain decimal (optional sign, digits, optional
	 * fraction) is returned unchanged. Any other string that starts with a
	 * formula trigger is prefixed with a tab.
	 *
	 * @param   mixed  $value  Cell value from a report row.
	 *
	 * @return  integer|float|string
	 *
	 * @since   1.0.0
	 */
	public static function formatCell(mixed $value): int|float|string
	{
		if (\is_int($value) || \is_float($value))
		{
			return $value;
		}

		if (\is_bool($value))
		{
			return $value ? '1' : '0';
		}

		if (!\is_string($value))
		{
			return '';
		}

		if (self::isPlainDecimal($value))
		{
			return $value;
		}

		if (self::startsWithFormula($value))
		{
			return "\t" . $value;
		}

		return $value;
	}

	/**
	 * Whether the whole string is a base-10 number, including a leading sign.
	 *
	 * @param   string  $value  Cell text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function isPlainDecimal(string $value): bool
	{
		return preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $value) === 1;
	}

	/**
	 * Whether the text starts with a formula trigger.
	 *
	 * @param   string  $value  Cell text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function startsWithFormula(string $value): bool
	{
		foreach (self::FORMULA_PREFIXES as $prefix)
		{
			if (str_starts_with($value, $prefix))
			{
				return true;
			}
		}

		return false;
	}
}
