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
 * Hard-coded award criterion kinds.
 *
 * @since  1.0.0
 */
enum AwardCriterionType: int
{
	case MetricTotal = 1;
	case MembershipYears = 2;
	case HoldsAward = 3;
}
