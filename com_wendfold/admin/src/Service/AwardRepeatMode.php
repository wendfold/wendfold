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
 * Hard-coded award repeat modes.
 *
 * @since  1.0.0
 */
enum AwardRepeatMode: int
{
	case Once = 0;
	case Tiered = 1;
}
