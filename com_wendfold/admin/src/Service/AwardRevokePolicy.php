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
 * What recompute does when a grant is no longer supported.
 *
 * @since  1.0.0
 */
enum AwardRevokePolicy: int
{
	case Auto = 1;
	case FlagReview = 2;
}
