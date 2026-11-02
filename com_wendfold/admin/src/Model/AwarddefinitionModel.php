<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\AwardPrerequisiteGraph;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Component\Wendfold\Administrator\Service\AwardCriterionType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardPeriodType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;
use Wendfold\Component\Wendfold\Administrator\Table\AwarddefinitionTable;

/**
 * One award definition, including its criteria subform.
 *
 * @since  1.0.0
 */
class AwarddefinitionModel extends LookupModel
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD_AWARD'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	/**
	 * @param   array    $data      Form data.
	 * @param   boolean  $loadData  Load the stored row.
	 *
	 * @return  \Joomla\CMS\Form\Form|false
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		return $this->loadForm(
			'com_wendfold.awarddefinition',
			'awarddefinition',
			['control' => 'jform', 'load_data' => $loadData]
		);
	}

	/**
	 * @param   string  $name     Table name.
	 * @param   string  $prefix   Class prefix.
	 * @param   array   $options  Options.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 *
	 * @since   1.0.0
	 */
	public function getTable($name = 'Awarddefinition', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function usageTable(): string
	{
		return '#__wendfold_member_awards';
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function usageColumn(): string
	{
		return 'award_id';
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function editContext(): string
	{
		return 'com_wendfold.edit.awarddefinition.data';
	}

	/**
	 * Load the definition and attach criteria for the subform.
	 *
	 * @param   integer  $pk  Primary key.
	 *
	 * @return  object|false
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		if ($item === false)
		{
			return false;
		}

		$item->criteria = $this->loadCriteria((int) ($item->id ?? 0));

		return $item;
	}

	/**
	 * Validate criteria, then save the definition and its criteria rows.
	 *
	 * @param   array  $data  Form data.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		/** @var \Joomla\CMS\Application\CMSApplication $app */
		$app = Factory::getApplication();
		$post = $app->getInput()->post->get('jform', [], 'array');

		// Prefer form-filtered $data over a raw post array for the criteria subform.
		$criterionRows = $this->normaliseCriterionRows(
			$data['criteria'] ?? ($post['criteria'] ?? [])
		);

		if (!$this->validateCriteria($data, $criterionRows))
		{
			return false;
		}

		$db = $this->getDatabase();
		$db->transactionStart(true);

		try
		{
			$result = parent::save($data);

			if (!$result)
			{
				$db->transactionRollback(true);

				return false;
			}

			$awardId = (int) $this->getState($this->getName() . '.id');
			$this->saveCriteria($awardId, $criterionRows);
			$db->transactionCommit(true);
		}
		catch (\Throwable $error)
		{
			$db->transactionRollback(true);
			WendfoldLog::throwable('Award definition save failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_SAVE_FAILED'));

			return false;
		}

		return true;
	}

	/**
	 * Criteria rows for one award, ordered for the subform.
	 *
	 * @param   integer  $awardId  Award id.
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	protected function loadCriteria(int $awardId): array
	{
		if ($awardId < 1)
		{
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_award_criteria'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadAssocList() ?: [];
	}

	/**
	 * Replace criteria for an award with the posted rows.
	 *
	 * @param   integer                            $awardId  Award id.
	 * @param   array<int, array<string, mixed>>   $rows     Normalised criteria.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveCriteria(int $awardId, array $rows): void
	{
		if ($awardId < 1)
		{
			return;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_award_criteria'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);
		$existingIds = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);
		$keepIds = [];
		$ordering = 1;

		foreach ($rows as $row)
		{
			$record = new \stdClass;
			$record->id = (int) ($row['id'] ?? 0);
			$record->award_id = $awardId;
			$record->criterion_type = (int) $row['criterion_type'];
			$record->metric_id = $row['metric_id'];
			$record->required_award_id = $row['required_award_id'];
			$record->threshold = $row['threshold'];
			$record->tier_step = $row['tier_step'];
			$record->ordering = $ordering;
			$ordering++;

			if ($record->id > 0 && \in_array($record->id, $existingIds, true))
			{
				$db->updateObject('#__wendfold_award_criteria', $record, 'id', true);
				$keepIds[] = $record->id;
				continue;
			}

			unset($record->id);
			$db->insertObject('#__wendfold_award_criteria', $record, 'id');
			$keepIds[] = (int) $record->id;
		}

		$deleteIds = array_values(array_diff($existingIds, $keepIds));

		if ($deleteIds === [])
		{
			return;
		}

		$deleteQuery = $db->createQuery()
			->delete($db->quoteName('#__wendfold_award_criteria'))
			->whereIn($db->quoteName('id'), $deleteIds, ParameterType::INTEGER)
			->where($db->quoteName('award_id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);
		$db->setQuery($deleteQuery)->execute();
	}

	/**
	 * Coerce posted subform rows into typed criterion arrays.
	 *
	 * @param   mixed  $raw  Posted criteria.
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	protected function normaliseCriterionRows(mixed $raw): array
	{
		if (\is_string($raw) && $raw !== '')
		{
			$decoded = json_decode($raw, true);
			$raw = \is_array($decoded) ? $decoded : [];
		}

		if (!\is_array($raw))
		{
			return [];
		}

		$out = [];

		foreach ($raw as $row)
		{
			if (!\is_array($row))
			{
				continue;
			}

			$type = (int) ($row['criterion_type'] ?? 0);

			if (!\in_array($type, [
				AwardCriterionType::MetricTotal->value,
				AwardCriterionType::MembershipYears->value,
				AwardCriterionType::HoldsAward->value,
				], true
			))
			{
				continue;
			}

			$metricId = (int) ($row['metric_id'] ?? 0);
			$requiredAwardId = (int) ($row['required_award_id'] ?? 0);
			$tierRaw = $row['tier_step'] ?? null;
			$tierStep = ($tierRaw === null || $tierRaw === '') ? null : (float) $tierRaw;

			$out[] = [
				'id'                => (int) ($row['id'] ?? 0),
				'criterion_type'    => $type,
				'metric_id'         => $type === AwardCriterionType::MetricTotal->value && $metricId > 0
					? $metricId
					: null,
				'required_award_id' => $type === AwardCriterionType::HoldsAward->value && $requiredAwardId > 0
					? $requiredAwardId
					: null,
				// Keep raw value so validateCriteria can reject blank / 0.
				'threshold'         => $row['threshold'] ?? null,
				'tier_step'         => $tierStep,
			];
		}

		return $out;
	}

	/**
	 * Enforce period/tier rules and reject prerequisite cycles.
	 *
	 * @param   array                             $data  Definition fields.
	 * @param   array<int, array<string, mixed>>  $rows  Criteria rows.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	protected function validateCriteria(array $data, array &$rows): bool
	{
		$periodType = (int) ($data['period_type'] ?? 0);

		if ($periodType === AwardPeriodType::Rolling->value)
		{
			$days = AwarddefinitionTable::nullableUnsignedInt($data['rolling_days'] ?? null);

			if ($days === null)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_ROLLING_DAYS'));

				return false;
			}
		}

		if ($periodType === AwardPeriodType::Custom->value)
		{
			$start = AwarddefinitionTable::nullableDate($data['period_start'] ?? null);
			$end = AwarddefinitionTable::nullableDate($data['period_end'] ?? null);

			if ($start === null || $end === null)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD'));

				return false;
			}

			if ($start > $end)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_CUSTOM_PERIOD_ORDER'));

				return false;
			}
		}

		foreach ($rows as $index => $row)
		{
			$threshold = AwarddefinitionTable::requiredThreshold($row['threshold'] ?? null);

			if ($threshold === null)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_THRESHOLD'));

				return false;
			}

			$rows[$index]['threshold'] = $threshold;
		}

		$repeatMode = (int) ($data['repeat_mode'] ?? 0);

		if ($repeatMode === AwardRepeatMode::Tiered->value)
		{
			$tiered = 0;

			foreach ($rows as $row)
			{
				$step = $row['tier_step'];

				if ($step === null)
				{
					continue;
				}

				if ((float) $step <= 0)
				{
					$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_TIER_STEP'));

					return false;
				}

				$tiered++;
			}

			if ($tiered !== 1)
			{
				$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_TIER_CRITERION'));

				return false;
			}
		}
		else
		{
			foreach ($rows as $index => $row)
			{
				$rows[$index]['tier_step'] = null;
			}
		}

		$awardId = (int) ($data['id'] ?? $this->getState($this->getName() . '.id'));
		$required = [];

		foreach ($rows as $row)
		{
			if ((int) $row['criterion_type'] !== AwardCriterionType::HoldsAward->value)
			{
				continue;
			}

			$requiredId = (int) ($row['required_award_id'] ?? 0);

			if ($requiredId > 0)
			{
				$required[] = $requiredId;
			}
		}

		if ($required !== []
			&& AwardPrerequisiteGraph::wouldCycle($this->prerequisiteEdges(), $awardId, $required)
		)
		{
			$this->setError(Text::_('COM_WENDFOLD_AWARD_ERR_PREREQUISITE_CYCLE'));

			return false;
		}

		return true;
	}

	/**
	 * Existing type-3 edges keyed by award id.
	 *
	 * @return  array<int, list<int>>
	 *
	 * @since   1.0.0
	 */
	protected function prerequisiteEdges(): array
	{
		$db = $this->getDatabase();
		$type = AwardCriterionType::HoldsAward->value;
		$query = $db->createQuery()
			->select([
				$db->quoteName('award_id'),
				$db->quoteName('required_award_id'),
				]
			)
			->from($db->quoteName('#__wendfold_award_criteria'))
			->where($db->quoteName('criterion_type') . ' = :criterionType')
			->where($db->quoteName('required_award_id') . ' IS NOT NULL')
			->bind(':criterionType', $type, ParameterType::INTEGER);

		$edges = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$awardId = (int) $row->award_id;
			$required = (int) $row->required_award_id;

			if ($awardId < 1 || $required < 1)
			{
				continue;
			}

			$edges[$awardId][] = $required;
		}

		return $edges;
	}
}
