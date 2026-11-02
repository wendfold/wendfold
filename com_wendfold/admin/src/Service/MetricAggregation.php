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
 * How a metric's entries combine.
 *
 * The values match metric_definitions.aggregation.
 *
 * @since  1.0.0
 */
enum MetricAggregation: int
{
	case Sum = 1;
	case Count = 2;
	case Max = 3;

	/**
	 * A known aggregation, or sum when the stored value is not one of these.
	 *
	 * @param   integer  $value  Stored aggregation.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromStored(int $value): self
	{
		return self::tryFrom($value) ?? self::Sum;
	}
}
