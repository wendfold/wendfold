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
 * Thrown when a per-award recompute lock cannot be acquired in time.
 *
 * Callers skip that award and continue with the rest of the batch.
 *
 * @since  1.0.0
 */
final class AwardLockTimeoutException extends \RuntimeException
{
	/**
	 * @param   integer          $awardId   Award definition id.
	 * @param   \Throwable|null  $previous  Underlying driver error, if any.
	 *
	 * @since   1.0.0
	 */
	public function __construct(int $awardId, ?\Throwable $previous = null)
	{
		parent::__construct(
			sprintf('Award recompute lock timed out for award %d', $awardId),
			1205,
			$previous
		);
	}
}
