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
 * Recomputes member awards after metric writes.
 *
 * @since  1.0.0
 */
interface AwardRecomputer
{
	/**
	 * Recompute one award or every published award.
	 *
	 * @param   integer|null    $awardId     Award id, or null for all published awards.
	 * @param   integer[]|null  $profileIds  Profiles to limit, or null for all members.
	 *
	 * @return  integer  Number of member-award rows whose state changed.
	 *
	 * @since   1.0.0
	 */
	public function recompute(?int $awardId = null, ?array $profileIds = null): int;
}
