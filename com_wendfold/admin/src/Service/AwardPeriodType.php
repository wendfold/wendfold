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
 * Hard-coded award period kinds.
 *
 * @since  1.0.0
 */
enum AwardPeriodType: int
{
	case Lifetime = 1;
	case CalendarYear = 2;
	case Rolling = 3;
	case Custom = 4;
}
