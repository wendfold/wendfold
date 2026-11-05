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
 * Discards award state-change notifications.
 *
 * @since  1.0.0
 */
final class NullAwardChangeSink implements AwardChangeSink
{
	/**
	 * @param   list<object>  $changes  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function notify(array $changes): void
	{
	}
}
