<?php

/**
 * Wendfold - Award definition blank nullable fields.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Blank optional integers and dates become NULL before store.
 *
 * Mirrors AwarddefinitionTable::nullableUnsignedInt / nullableDate without
 * booting Joomla Table.
 *
 * @since  1.0.0
 */
final class AwardNullableFieldsTest extends TestCase
{
	/**
	 * Saving with every optional period/tier field blank yields NULL.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBlankOptionalFieldsBecomeNull(): void
	{
		$rollingDays = self::nullableUnsignedInt('');
		$maxTiers = self::nullableUnsignedInt('');
		$streakPeriods = self::nullableUnsignedInt('   ');
		$periodStart = self::nullableDate('');
		$periodEnd = self::nullableDate('');

		$this->assertNull($rollingDays);
		$this->assertNull($maxTiers);
		$this->assertNull($streakPeriods);
		$this->assertNull($periodStart);
		$this->assertNull($periodEnd);
	}

	/**
	 * Positive integers and valid dates are kept.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPositiveIntegersAndDatesAreKept(): void
	{
		$this->assertSame(30, self::nullableUnsignedInt('30'));
		$this->assertSame(5, self::nullableUnsignedInt(5));
		$this->assertNull(self::nullableUnsignedInt(0));
		$this->assertSame('2026-01-15', self::nullableDate('2026-01-15'));
		$this->assertNull(self::nullableDate('0000-00-00'));
	}

	/**
	 * The table check() method calls the null coercion helpers.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTableCheckUsesNullCoercionHelpers(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Table/AwarddefinitionTable.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString('nullableUnsignedInt', $source);
		$this->assertStringContainsString('nullableDate', $source);
		$this->assertStringContainsString("'rolling_days', 'max_tiers', 'streak_periods'", $source);
		$this->assertStringContainsString("'period_start', 'period_end'", $source);
	}

	/**
	 * Mirror AwarddefinitionTable::nullableUnsignedInt.
	 *
	 * @param   mixed  $value  Posted value.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private static function nullableUnsignedInt(mixed $value): ?int
	{
		if ($value === null || $value === '')
		{
			return null;
		}

		if (\is_string($value) && trim($value) === '')
		{
			return null;
		}

		$int = (int) $value;

		return $int > 0 ? $int : null;
	}

	/**
	 * Mirror AwarddefinitionTable::nullableDate.
	 *
	 * @param   mixed  $value  Posted value.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private static function nullableDate(mixed $value): ?string
	{
		if ($value === null || $value === '')
		{
			return null;
		}

		if (\is_string($value))
		{
			$value = trim($value);

			if ($value === '' || str_starts_with($value, '0000-00-00'))
			{
				return null;
			}

			$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

			if ($parsed instanceof \DateTimeImmutable && $parsed->format('Y-m-d') === substr($value, 0, 10))
			{
				return $parsed->format('Y-m-d');
			}

			return null;
		}

		return null;
	}
}
