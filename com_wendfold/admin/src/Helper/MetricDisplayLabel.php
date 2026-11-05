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

use Joomla\CMS\Language\Text;

/**
 * Formats metric titles and amounts with optional units for member views.
 *
 * Omits the unit when it equals or is contained in the metric label
 * (case-insensitive), including simple English singular/plural forms.
 * Counted amounts use Text::plural on language-key unit labels (_1 / _MORE).
 *
 * @since  1.0.0
 */
final class MetricDisplayLabel
{
	/**
	 * Label alone, or label plus unit via COM_WENDFOLD_METRIC_LABEL_WITH_UNIT.
	 *
	 * @param   string  $label    Metric title (plain text or language key).
	 * @param   string  $unitRaw  Stored unit_label (plain text or language key).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function withOptionalUnit(string $label, string $unitRaw): string
	{
		$labelDisplay = LookupTitle::display(trim($label));
		$unitRaw      = trim($unitRaw);

		if ($unitRaw === '')
		{
			return $labelDisplay;
		}

		$unitDisplay = LookupTitle::display($unitRaw);

		if ($unitDisplay === '' || self::isRedundantUnit($labelDisplay, $unitDisplay))
		{
			return $labelDisplay;
		}

		return Text::sprintf('COM_WENDFOLD_METRIC_LABEL_WITH_UNIT', $labelDisplay, $unitDisplay);
	}

	/**
	 * Formatted amount, with a pluralised unit when it adds information.
	 *
	 * @param   string  $formattedAmount  Already number_format()'d amount.
	 * @param   float   $value            Numeric value used for pluralisation.
	 * @param   string  $unitRaw          Stored unit_label (plain text or language key).
	 * @param   string  $metricLabel      Metric title used for redundancy checks.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function formatAmountWithUnit(
		string $formattedAmount,
		float $value,
		string $unitRaw,
		string $metricLabel = ''
	): string
	{
		$formattedAmount = trim($formattedAmount);
		$unitRaw         = trim($unitRaw);
		$metricLabel     = LookupTitle::display(trim($metricLabel));

		if ($unitRaw === '')
		{
			return $formattedAmount;
		}

		$baseUnit = LookupTitle::display($unitRaw);

		if ($baseUnit !== '' && self::isRedundantUnit($metricLabel, $baseUnit))
		{
			return $formattedAmount;
		}

		$unitText = self::unitForCount($unitRaw, $value);

		if ($unitText === '' || self::isRedundantUnit($metricLabel, $unitText))
		{
			return $formattedAmount;
		}

		return trim($formattedAmount . ' ' . $unitText);
	}

	/**
	 * Singular or plural unit word for a numeric count.
	 *
	 * Language-key unit labels go through Text::plural (_1 / _MORE suffixes).
	 * Plain-text units are returned unchanged.
	 *
	 * @param   string  $unitRaw  Stored unit_label.
	 * @param   float   $value    Numeric value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function unitForCount(string $unitRaw, float $value): string
	{
		$unitRaw = trim($unitRaw);

		if ($unitRaw === '')
		{
			return '';
		}

		if (!LookupTitle::isLanguageKey($unitRaw))
		{
			return $unitRaw;
		}

		return Text::plural($unitRaw, self::pluralCount($value));
	}

	/**
	 * Whether appending the unit would repeat information already in the label.
	 *
	 * @param   string  $label  Metric title.
	 * @param   string  $unit   Unit text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isRedundantUnit(string $label, string $unit): bool
	{
		$labelNorm = self::fold(trim($label));
		$unitNorm  = self::fold(trim($unit));

		if ($unitNorm === '')
		{
			return true;
		}

		if ($labelNorm === '')
		{
			return false;
		}

		foreach (self::unitVariants($unitNorm) as $variant)
		{
			if ($variant === '')
			{
				continue;
			}

			if ($variant === $labelNorm || str_contains($labelNorm, $variant))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Count passed to Text::plural: 1 only for exactly one, else a non-one value.
	 *
	 * @param   float  $value  Metric amount.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function pluralCount(float $value): int
	{
		if (abs($value - 1.0) < 0.0000001)
		{
			return 1;
		}

		if (abs($value) < 0.0000001)
		{
			return 0;
		}

		$rounded = (int) round(abs($value));

		return $rounded === 1 ? 2 : max(2, $rounded);
	}

	/**
	 * Case-fold for comparison without depending on locale multibyte tables.
	 *
	 * @param   string  $value  Raw text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function fold(string $value): string
	{
		return strtolower($value);
	}

	/**
	 * Unit text plus a simple English singular or plural sibling.
	 *
	 * @param   string  $unitNorm  Already-folded unit.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private static function unitVariants(string $unitNorm): array
	{
		$variants = [$unitNorm];

		if (str_ends_with($unitNorm, 's') && \strlen($unitNorm) > 1)
		{
			$variants[] = substr($unitNorm, 0, -1);
		}
		else
		{
			$variants[] = $unitNorm . 's';
		}

		return array_values(array_unique($variants));
	}
}
