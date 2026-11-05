<?php

/**
 * Wendfold - Award period and threshold config validation.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\AwardPeriodType;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardPeriodType.php';

/**
 * Mirrors AwarddefinitionTable::check / AwarddefinitionModel period rules.
 *
 * @since  1.0.0
 */
final class AwardPeriodConfigValidationTest extends TestCase
{
	/**
	 * Rolling periods require a positive rolling_days value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingRequiresPositiveDays(): void
	{
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS',
			$this->validatePeriod(AwardPeriodType::Rolling->value, null, null, null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS',
			$this->validatePeriod(AwardPeriodType::Rolling->value, 0, null, null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS',
			$this->validatePeriod(AwardPeriodType::Rolling->value, '', null, null)
		);
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::Rolling->value, 30, null, null)
		);
	}

	/**
	 * Custom periods require both dates in order.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomRequiresOrderedDates(): void
	{
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD',
			$this->validatePeriod(AwardPeriodType::Custom->value, null, null, null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD',
			$this->validatePeriod(AwardPeriodType::Custom->value, null, '2026-01-01', null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD_ORDER',
			$this->validatePeriod(
				AwardPeriodType::Custom->value,
				null,
				'2026-12-01',
				'2026-01-01'
			)
		);
		$this->assertNull(
			$this->validatePeriod(
				AwardPeriodType::Custom->value,
				null,
				'2026-01-01',
				'2026-01-01'
			)
		);
		$this->assertNull(
			$this->validatePeriod(
				AwardPeriodType::Custom->value,
				null,
				'2026-01-01',
				'2026-12-31'
			)
		);
	}

	/**
	 * Lifetime and calendar-year ignore rolling/custom fields.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLifetimeAndCalendarIgnorePeriodExtras(): void
	{
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::Lifetime->value, null, null, null)
		);
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::CalendarYear->value, null, null, null)
		);
	}

	/**
	 * Blank, NULL, zero, and negative thresholds are rejected; >= 1 is kept.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testThresholdMustBeAtLeastOne(): void
	{
		$this->assertNull(self::requiredThreshold(null));
		$this->assertNull(self::requiredThreshold(''));
		$this->assertNull(self::requiredThreshold('   '));
		$this->assertNull(self::requiredThreshold(0));
		$this->assertNull(self::requiredThreshold(0.0));
		$this->assertNull(self::requiredThreshold('0'));
		$this->assertNull(self::requiredThreshold(0.999));
		$this->assertNull(self::requiredThreshold(-1));
		$this->assertSame(1.0, self::requiredThreshold(1));
		$this->assertSame(1.0, self::requiredThreshold('1'));
		$this->assertSame(10.5, self::requiredThreshold(10.5));

		$this->assertSame('COM_WENDFOLD_AWARD_ERR_THRESHOLD', $this->validateThreshold(null));
		$this->assertSame('COM_WENDFOLD_AWARD_ERR_THRESHOLD', $this->validateThreshold(''));
		$this->assertSame('COM_WENDFOLD_AWARD_ERR_THRESHOLD', $this->validateThreshold(0));
		$this->assertSame('COM_WENDFOLD_AWARD_ERR_THRESHOLD', $this->validateThreshold(-0.001));
		$this->assertNull($this->validateThreshold(1));
		$this->assertNull($this->validateThreshold(10));
	}

	/**
	 * Period extras use showon but are not HTML-required (browser would block
	 * lifetime/calendar saves). Server check() / model enforce them by type.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFormPeriodFieldsAreShowonNotHtmlRequired(): void
	{
		$xml = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/forms/awarddefinition.xml'
		);
		$this->assertIsString($xml);
		$this->assertDoesNotMatchRegularExpression(
			'/name="rolling_days"[^>]*required="true"/s',
			$xml
		);
		$this->assertMatchesRegularExpression(
			'/name="rolling_days"[^>]*showon="period_type:3"/s',
			$xml
		);
		$this->assertDoesNotMatchRegularExpression(
			'/name="period_start"[^>]*required="true"/s',
			$xml
		);
		$this->assertMatchesRegularExpression(
			'/name="period_start"[^>]*showon="period_type:4"/s',
			$xml
		);
		$this->assertDoesNotMatchRegularExpression(
			'/name="period_end"[^>]*required="true"/s',
			$xml
		);
		$this->assertMatchesRegularExpression(
			'/name="period_end"[^>]*showon="period_type:4"/s',
			$xml
		);
	}

	/**
	 * Lifetime and calendar awards save with blank dates and rolling_days.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLifetimeAndCalendarSaveWithBlankPeriodExtras(): void
	{
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::Lifetime->value, null, null, null)
		);
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::Lifetime->value, '', '', '')
		);
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::CalendarYear->value, null, null, null)
		);
		$this->assertNull(
			$this->validatePeriod(AwardPeriodType::CalendarYear->value, 0, '', '')
		);
	}

	/**
	 * Custom awards reject missing dates; rolling awards reject 0 / blank days.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomAndRollingSaveRejectInvalidExtras(): void
	{
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD',
			$this->validatePeriod(AwardPeriodType::Custom->value, null, null, null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD',
			$this->validatePeriod(AwardPeriodType::Custom->value, null, '2026-01-01', null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS',
			$this->validatePeriod(AwardPeriodType::Rolling->value, 0, null, null)
		);
		$this->assertSame(
			'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS',
			$this->validatePeriod(AwardPeriodType::Rolling->value, null, null, null)
		);
	}

	/**
	 * Criterion form requires threshold >= 1.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFormRequiresThresholdAtLeastOne(): void
	{
		$xml = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/forms/awardcriterion.xml'
		);
		$this->assertIsString($xml);
		$this->assertMatchesRegularExpression(
			'/name="threshold"[^>]*required="true"/s',
			$xml
		);
		$this->assertMatchesRegularExpression(
			'/name="threshold"[^>]*min="1"/s',
			$xml
		);
		$this->assertDoesNotMatchRegularExpression(
			'/name="threshold"[^>]*default="0"/s',
			$xml
		);
	}

	/**
	 * Table helper and model validation reject blank/zero thresholds.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTableHelperAndModelRejectBadThresholds(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Table/AwarddefinitionTable.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString('function requiredThreshold', $source);

		$model = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/AwarddefinitionModel.php'
		);
		$this->assertIsString($model);
		$this->assertStringContainsString('requiredThreshold', $model);
		$this->assertStringContainsString('COM_WENDFOLD_AWARD_ERR_THRESHOLD', $model);
	}

	/**
	 * Table check() validates rolling and custom after null coercion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTableCheckValidatesPeriodFields(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Table/AwarddefinitionTable.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString('COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS', $source);
		$this->assertStringContainsString('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD', $source);
		$this->assertStringContainsString('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD_ORDER', $source);
	}

	/**
	 * Mirror table/model period validation after null coercion.
	 *
	 * @param   integer       $periodType   Period type.
	 * @param   mixed         $rollingDays  Rolling days.
	 * @param   string|null   $start        Period start.
	 * @param   string|null   $end          Period end.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private function validatePeriod(
		int $periodType,
		mixed $rollingDays,
		?string $start,
		?string $end
	): ?string {
		$days = self::nullableUnsignedInt($rollingDays);
		$start = self::nullableDate($start);
		$end = self::nullableDate($end);

		if ($periodType === AwardPeriodType::Rolling->value && ($days === null || $days < 1))
		{
			return 'COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS';
		}

		if ($periodType === AwardPeriodType::Custom->value)
		{
			if ($start === null || $end === null)
			{
				return 'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD';
			}

			if ($start > $end)
			{
				return 'COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD_ORDER';
			}
		}

		return null;
	}

	/**
	 * @param   mixed  $threshold  Threshold.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private function validateThreshold(mixed $threshold): ?string
	{
		return self::requiredThreshold($threshold) === null
			? 'COM_WENDFOLD_AWARD_ERR_THRESHOLD'
			: null;
	}

	/**
	 * Mirror AwarddefinitionTable::requiredThreshold.
	 *
	 * @param   mixed  $value  Posted threshold.
	 *
	 * @return  float|null
	 *
	 * @since   1.0.0
	 */
	private static function requiredThreshold(mixed $value): ?float
	{
		if ($value === null || $value === '')
		{
			return null;
		}

		if (\is_string($value) && trim($value) === '')
		{
			return null;
		}

		if (!\is_numeric($value))
		{
			return null;
		}

		$threshold = (float) $value;

		return $threshold >= 1.0 ? $threshold : null;
	}

	/**
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
