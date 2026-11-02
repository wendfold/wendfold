<?php

/**
 * Wendfold - AwardRecomputer double that counts calls.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Service\AwardRecomputer;

/**
 * Counts recompute invocations for MetricService tests.
 *
 * @since  1.0.0
 */
final class CountingAwardRecomputer implements AwardRecomputer
{
	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $calls = 0;

	/**
	 * @var    list<integer[]|null>
	 * @since  1.0.0
	 */
	public array $profileArgs = [];

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $throw = false;

	/**
	 * Optional hook invoked at the start of recompute.
	 *
	 * @var    callable|null
	 * @since  1.0.0
	 */
	public $onRecompute = null;

	/**
	 * @param   integer|null    $awardId     Unused.
	 * @param   integer[]|null  $profileIds  Profiles.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function recompute(?int $awardId = null, ?array $profileIds = null): int
	{
		if ($this->onRecompute !== null)
		{
			($this->onRecompute)($awardId, $profileIds);
		}

		$this->calls++;
		$this->profileArgs[] = $profileIds;

		if ($this->throw)
		{
			throw new \RuntimeException('Award recompute failed on purpose.');
		}

		return 0;
	}
}
