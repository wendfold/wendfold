<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

/**
 * Normalise a metric amount for DECIMAL(12,3).
 *
 * @since  1.0.0
 */
final class MetricValue
{
	/**
	 * Round a posted amount to the metric's decimals and store three places.
	 *
	 * An empty string is "no value". Only a plain non-negative decimal is
	 * accepted. Scientific notation, signs, and letters are refused. Extra
	 * significant fractional digits beyond the metric's decimals are refused.
	 * Trailing zeros past the metric's decimals are allowed so a stored
	 * DECIMAL value like 1.000 can round-trip through the event form.
	 *
	 * @param   string  $raw       Posted amount.
	 * @param   integer $decimals  Display decimals, 0 through 3.
	 *
	 * @return  string|null  Null when the field was left blank.
	 *
	 * @since   1.0.0
	 */
	public static function normalise(string $raw, int $decimals): ?string
	{
		$raw = trim($raw);

		if ($raw === '')
		{
			return null;
		}

		if (preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $raw) !== 1)
		{
			throw new \InvalidArgumentException('Metric value must be a plain non-negative decimal.');
		}

		$decimals = max(0, min(3, $decimals));

		if (str_contains($raw, '.'))
		{
			$fraction = substr($raw, strpos($raw, '.') + 1);
			$significant = rtrim($fraction, '0');

			if (\strlen($significant) > $decimals)
			{
				throw new \InvalidArgumentException('Metric value has too many decimal places.');
			}
		}

		return number_format((float) $raw, 3, '.', '');
	}

	/**
	 * Format a stored DECIMAL value for an input whose step matches $decimals.
	 *
	 * @param   string   $stored    Value from the database, e.g. 1.000.
	 * @param   integer  $decimals  Metric display decimals, 0 through 3.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function formatForInput(string $stored, int $decimals): string
	{
		$stored = trim($stored);

		if ($stored === '')
		{
			return '';
		}

		$decimals = max(0, min(3, $decimals));

		return number_format((float) $stored, $decimals, '.', '');
	}
}
