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
use Wendfold\Component\Wendfold\Administrator\Service\MetricValue;

/**
 * One metric definition.
 *
 * metric_key is ascii, unique, and does not change after the row is saved.
 *
 * @since  1.0.0
 */
class MetricdefinitionTable extends LookupTable
{
	/**
	 * @param   DatabaseDriver  $db  Database connector.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->usageTable = '#__wendfold_metric_entries';
		$this->usageColumn = 'metric_id';

		parent::__construct($db);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function lookupTable(): string
	{
		return '#__wendfold_metric_definitions';
	}

	/**
	 * Entries and event credits both reference this metric.
	 *
	 * @param   integer  $id  Metric id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function countUsage(int $id): int
	{
		$entries = parent::countUsage($id);

		if ($id < 1)
		{
			return $entries;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_event_metric_credits'))
			->where($db->quoteName('metric_id') . ' = :metricId')
			->bind(':metricId', $id, ParameterType::INTEGER);

		return $entries + (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Validate the key, flags, and default credit.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function check(): bool
	{
		$key = trim((string) $this->metric_key);

		if ((int) $this->id > 0)
		{
			$stored = $this->storedKey((int) $this->id);

			if ($stored !== '')
			{
				$key = $stored;
			}
		}

		if (preg_match('/^[a-z0-9_]{1,50}$/', $key) !== 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_METRIC_KEY_INVALID'));

			return false;
		}

		$this->metric_key = $key;

		if ($this->keyTaken($key, (int) $this->id))
		{
			$this->setError(Text::_('COM_WENDFOLD_METRIC_KEY_TAKEN'));

			return false;
		}

		$this->decimals = max(0, min(3, (int) $this->decimals));
		$aggregation = (int) $this->aggregation;
		$this->aggregation = \in_array($aggregation, [1, 2, 3], true) ? $aggregation : 1;

		foreach ([
				'allow_event_credit',
				'allow_member_entry',
				'member_entry_needs_approval',
				'show_on_dashboard',
			] as $flag
		)
		{
			$this->$flag = (int) $this->$flag === 1 ? 1 : 0;
		}

		$this->unit_label = trim((string) $this->unit_label);
		$rawDefault = $this->default_event_credit;

		try
		{
			$this->default_event_credit = MetricValue::normalise(
				$rawDefault === null ? '' : (string) $rawDefault,
				(int) $this->decimals
			);
		}
		catch (\InvalidArgumentException)
		{
			$this->setError(Text::_('COM_WENDFOLD_METRIC_CREDIT_INVALID'));

			return false;
		}

		return parent::check();
	}

	/**
	 * Stored metric_key, or an empty string.
	 *
	 * @param   integer  $id  Metric id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function storedKey(int $id): string
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select($db->quoteName('metric_key'))
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('id') . ' = :metricId')
			->bind(':metricId', $id, ParameterType::INTEGER);
		$value = $db->setQuery($query)->loadResult();

		return \is_string($value) ? $value : '';
	}

	/**
	 * Whether another row already uses this key.
	 *
	 * @param   string   $key  Normalised key.
	 * @param   integer  $id   Current id. 0 is a new row.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function keyTaken(string $key, int $id): bool
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('metric_key') . ' = :metricKey')
			->bind(':metricKey', $key);

		if ($id > 0)
		{
			$query->where($db->quoteName('id') . ' <> :metricId')
				->bind(':metricId', $id, ParameterType::INTEGER);
		}

		return (int) $db->setQuery($query)->loadResult() > 0;
	}
}
