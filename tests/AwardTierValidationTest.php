<?php

/**
 * Wendfold - Award definition tier criterion rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\AwardCriterionType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardCriterionType.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRepeatMode.php';

/**
 * Tier step must be positive on tiered awards and stripped otherwise.
 *
 * @since  1.0.0
 */
final class AwardTierValidationTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testZeroTierStepIsRejectedOnTieredAward(): void
	{
		$rows = [
			[
				'criterion_type' => AwardCriterionType::MetricTotal->value,
				'tier_step'      => 0.0,
				'threshold'      => 1000.0,
			],
		];

		$this->assertFalse($this->validate(AwardRepeatMode::Tiered->value, $rows));
		$this->assertSame('COM_WENDFOLD_AWARD_ERR_TIER_STEP', $this->lastError);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNegativeTierStepIsRejectedOnTieredAward(): void
	{
		$rows = [
			[
				'criterion_type' => AwardCriterionType::MetricTotal->value,
				'tier_step'      => -5.0,
				'threshold'      => 1000.0,
			],
		];

		$this->assertFalse($this->validate(AwardRepeatMode::Tiered->value, $rows));
		$this->assertSame('COM_WENDFOLD_AWARD_ERR_TIER_STEP', $this->lastError);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTierStepIsStrippedOnNonTieredAward(): void
	{
		$rows = [
			[
				'criterion_type' => AwardCriterionType::MetricTotal->value,
				'tier_step'      => 1000.0,
				'threshold'      => 1000.0,
			],
		];

		$this->assertTrue($this->validate(AwardRepeatMode::Once->value, $rows));
		$this->assertNull($rows[0]['tier_step']);
	}

	/**
	 * @var    string|null
	 * @since  1.0.0
	 */
	private ?string $lastError = null;

	/**
	 * Mirror AwarddefinitionModel::validateCriteria tier rules without Joomla.
	 *
	 * @param   integer                            $repeatMode  Repeat mode.
	 * @param   array<int, array<string, mixed>>   $rows        Criteria rows.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function validate(int $repeatMode, array &$rows): bool
	{
		$this->lastError = null;

		if ($repeatMode === AwardRepeatMode::Tiered->value)
		{
			$tiered = 0;

			foreach ($rows as $row)
			{
				$step = $row['tier_step'] ?? null;

				if ($step === null)
				{
					continue;
				}

				if ((float) $step <= 0)
				{
					$this->lastError = 'COM_WENDFOLD_AWARD_ERR_TIER_STEP';

					return false;
				}

				$tiered++;
			}

			if ($tiered !== 1)
			{
				$this->lastError = 'COM_WENDFOLD_AWARD_ERR_TIER_CRITERION';

				return false;
			}

			return true;
		}

		foreach ($rows as $index => $row)
		{
			$rows[$index]['tier_step'] = null;
		}

		return true;
	}
}
