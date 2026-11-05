<?php

/**
 * Wendfold - MetricValue plain-decimal rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\MetricValue;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricValue.php';

/**
 * Posted metric amounts must be plain non-negative decimals.
 *
 * @since  1.0.0
 */
final class MetricValueTest extends TestCase
{
	/**
	 * @return  array<string, array{0: string, 1: int, 2: string|null}>
	 *
	 * @since   1.0.0
	 */
	public static function acceptedProvider(): array
	{
		return [
			'blank' => ['', 1, null],
			'zero' => ['0', 0, '0.000'],
			'integer' => ['12', 0, '12.000'],
			'one decimal' => ['1.5', 1, '1.500'],
			'three decimals when allowed' => ['1.125', 3, '1.125'],
			'trailing zeros for zero decimals' => ['1.000', 0, '1.000'],
			'trailing zeros for one decimal' => ['1.500', 1, '1.500'],
			'stored three places for two decimals' => ['50.000', 2, '50.000'],
		];
	}

	/**
	 * @param   string       $raw       Posted amount.
	 * @param   integer      $decimals  Metric decimals.
	 * @param   string|null  $expected  Stored value, or null.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('acceptedProvider')]
	public function testAcceptedAmounts(string $raw, int $decimals, ?string $expected): void
	{
		$this->assertSame($expected, MetricValue::normalise($raw, $decimals));
	}

	/**
	 * @return  array<string, array{0: string, 1: int}>
	 *
	 * @since   1.0.0
	 */
	public static function rejectedProvider(): array
	{
		return [
			'scientific' => ['1e3', 1],
			'scientific upper' => ['1E3', 1],
			'negative' => ['-1', 1],
			'letters' => ['abc', 1],
			'leading zero integer' => ['01', 0],
			'too many decimals' => ['1.55', 1],
			'signed positive' => ['+1', 0],
		];
	}

	/**
	 * @param   string   $raw       Posted amount.
	 * @param   integer  $decimals  Metric decimals.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('rejectedProvider')]
	public function testRejectedAmounts(string $raw, int $decimals): void
	{
		$this->expectException(\InvalidArgumentException::class);
		MetricValue::normalise($raw, $decimals);
	}

	/**
	 * A stored DECIMAL value formatted for the metric's decimals validates again.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testStoredValueRoundTripsThroughInputFormatting(): void
	{
		foreach ([0, 1, 2, 3] as $decimals)
		{
			$stored = '1.000';
			$input = MetricValue::formatForInput($stored, $decimals);
			$again = MetricValue::normalise($input, $decimals);

			$this->assertSame('1.000', $again);
			$this->assertSame(
				number_format(1.0, $decimals, '.', ''),
				$input
			);
		}

		$this->assertSame('50', MetricValue::formatForInput('50.000', 0));
		$this->assertSame('50.000', MetricValue::normalise('50', 0));
		$this->assertSame('50.00', MetricValue::formatForInput('50.000', 2));
		$this->assertSame('50.000', MetricValue::normalise('50.00', 2));
	}
}
