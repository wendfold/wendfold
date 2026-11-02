<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Table;

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * One award definition.
 *
 * Refuse delete while member_awards rows exist. When unused, delete criteria
 * rows for the award after the definition is removed.
 *
 * @since  1.0.0
 */
class AwarddefinitionTable extends LookupTable
{
	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->usageTable = '#__wendfold_member_awards';
		$this->usageColumn = 'award_id';

		parent::__construct($db);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_award_definitions';
	}

	/**
	 * Coerce blank optional integers and dates to NULL, then enforce period fields.
	 *
	 * Strict SQL mode rejects '' for nullable integer/date columns. Rolling
	 * periods require rolling_days > 0. Custom periods require ordered dates.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		foreach (['rolling_days', 'max_tiers', 'streak_periods'] as $column)
		{
			$this->$column = self::nullableUnsignedInt($this->$column ?? null);
		}

		foreach (['period_start', 'period_end'] as $column)
		{
			$this->$column = self::nullableDate($this->$column ?? null);
		}

		if (!parent::check())
		{
			return false;
		}

		$periodType = (int) ($this->period_type ?? 0);

		// AwardPeriodType::Rolling = 3
		if ($periodType === 3 && ($this->rolling_days === null || (int) $this->rolling_days < 1))
		{
			$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS'));

			return false;
		}

		// AwardPeriodType::Custom = 4
		if ($periodType === 4)
		{
			if ($this->period_start === null || $this->period_end === null)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD'));

				return false;
			}

			if ((string) $this->period_start > (string) $this->period_end)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD_ORDER'));

				return false;
			}
		}

		return true;
	}

	/**
	 * Blank or non-positive optional integers become NULL.
	 *
	 * @param   mixed  $value  Posted value.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public static function nullableUnsignedInt(mixed $value): ?int
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
	 * Blank calendar values become NULL; Y-m-d strings are kept.
	 *
	 * @param   mixed  $value  Posted value.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function nullableDate(mixed $value): ?string
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

	/**
	 * Criterion threshold: blank, zero, and values below 1 are rejected.
	 *
	 * @param   mixed  $value  Posted threshold.
	 *
	 * @return  float|null  Threshold when >= 1, otherwise null.
	 *
	 * @since   1.0.0
	 */
	public static function requiredThreshold(mixed $value): ?float
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
	 * Delete the definition, then its criteria when the usage guard allows it.
	 *
	 * @param   integer|null  $pk  Primary key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function delete($pk = null): bool
	{
		$pk = (int) ($pk ?: $this->id);

		if ($pk > 0 && $this->isUsedAsPrerequisite($pk))
		{
			$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_USED_AS_PREREQUISITE'));

			return false;
		}

		if (!parent::delete($pk))
		{
			return false;
		}

		if ($pk < 1)
		{
			return true;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->delete($db->quoteName('#__wendfold_award_criteria'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->bind(':awardId', $pk, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		return true;
	}

	/**
	 * Whether another award criterion lists this award as a prerequisite.
	 *
	 * @param   integer  $awardId  Award definition id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isUsedAsPrerequisite(int $awardId): bool
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_award_criteria'))
			->where($db->quoteName('required_award_id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult() > 0;
	}
}
