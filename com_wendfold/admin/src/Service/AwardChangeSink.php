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
 * Receives member-award rows whose state changed during recompute.
 *
 * @since  1.0.0
 */
interface AwardChangeSink
{
	/**
	 * Notify about state changes. Empty when nothing changed.
	 *
	 * @param   list<object>  $changes  Each object has id, award_id, profile_id, from_state, to_state.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function notify(array $changes): void;
}
