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
 * Where a metric entry came from.
 *
 * The values match metric_entries.source_type.
 *
 * @since  1.0.0
 */
enum MetricSource: int
{
	case Attendance = 1;
	case Activity = 2;
	case Manual = 3;
	case Import = 4;
	case Volunteer = 5;
}
