<?php

/**
 * Wendfold - Manual grant tier rules for non-tiered awards.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRepeatMode.php';

/**
 * Non-tiered awards reject tier > 1 and force tier 1.
 *
 * Mirrors MemberawardsModel::grantManual without booting Joomla.
 *
 * @since  1.0.0
 */
final class AwardManualGrantTierTest extends TestCase
{
	/**
	 * @var    string|null
	 * @since  1.0.0
	 */
	private ?string $lastError = null;

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTierGreaterThanOneRejectedOnNonTieredAward(): void
	{
		$tier = 5;
		$this->assertFalse($this->normaliseTier(AwardRepeatMode::Once->value, $tier));
		$this->assertSame('COM_WENDFOLD_MEMBERAWARDS_ERR_TIER_NOT_TIERED', $this->lastError);
		$this->assertSame(5, $tier);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNonTieredAwardForcesTierOne(): void
	{
		$tier = 1;
		$this->assertTrue($this->normaliseTier(AwardRepeatMode::Once->value, $tier));
		$this->assertSame(1, $tier);
		$this->assertNull($this->lastError);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTieredAwardKeepsHigherTier(): void
	{
		$tier = 5;
		$this->assertTrue($this->normaliseTier(AwardRepeatMode::Tiered->value, $tier));
		$this->assertSame(5, $tier);
		$this->assertNull($this->lastError);
	}

	/**
	 * Mirror MemberawardsModel::grantManual tier rules.
	 *
	 * @param   integer  $repeatMode  Award repeat mode.
	 * @param   integer  $tier        Requested tier (by ref).
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function normaliseTier(int $repeatMode, int &$tier): bool
	{
		$this->lastError = null;
		$tier = max(1, $tier);

		if ($repeatMode !== AwardRepeatMode::Tiered->value)
		{
			if ($tier > 1)
			{
				$this->lastError = 'COM_WENDFOLD_MEMBERAWARDS_ERR_TIER_NOT_TIERED';

				return false;
			}

			$tier = 1;
		}

		return true;
	}
}
