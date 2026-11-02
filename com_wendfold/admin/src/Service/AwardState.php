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
 * Member award row state.
 *
 * @since  1.0.0
 */
enum AwardState: int
{
	case Revoked = 0;
	case Granted = 1;
	case NeedsReview = 2;
}
