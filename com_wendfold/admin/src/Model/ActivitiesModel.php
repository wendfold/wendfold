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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricSource;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

/**
 * Member activities and their approval state.
 *
 * activities.published uses the existing Joomla values: 1 counted, 0 pending,
 * -2 rejected. Metric entry published values use the same meanings.
 *
 * @since  1.0.0
 */
class ActivitiesModel extends ListModel
{
	/**
	 * @param   array  $config  Configuration.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		$config['filter_fields'] = [
			'me.entry_date',
			'activity_title',
			'me.published',
			'me.id',
			'member_name',
		];

		parent::__construct($config);
	}

	/**
	 * Publish pending or rejected metric entries for the selected activities.
	 *
	 * @param   integer[]  $ids  Activity ids.
	 *
	 * @return  integer  How many activities actually changed.
	 *
	 * @since   1.0.0
	 */
	public function approve(array $ids): int
	{
		return $this->decide($ids, 1);
	}

	/**
	 * Return counted entries for the selected activities to pending.
	 *
	 * @param   integer[]  $ids  Activity ids.
	 *
	 * @return  integer  How many activities actually changed.
	 *
	 * @since   1.0.0
	 */
	public function unapprove(array $ids): int
	{
		return $this->decide($ids, 0);
	}

	/**
	 * Reject the selected activities and trash their metric entries.
	 *
	 * @param   integer[]  $ids  Activity ids.
	 *
	 * @return  integer  How many activities actually changed.
	 *
	 * @since   1.0.0
	 */
	public function reject(array $ids): int
	{
		return $this->decide($ids, -2);
	}

	/**
	 * Apply one approval decision to each selected activity.
	 *
	 * A nonexistent id is skipped. The count only includes rows whose
	 * metric entries or activity published value actually changed.
	 *
	 * @param   integer[]  $ids        Activity ids.
	 * @param   integer    $published  1 approve, 0 unapprove, -2 reject.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function decide(array $ids, int $published): int
	{
		$user = Factory::getApplication()->getIdentity();
		$db = $this->getDatabase();
		$service = $this->metricService($db, (int) $user->id);
		$service->deferAwardRecompute(true);
		$changed = 0;

		try
		{
			foreach ($ids as $id)
			{
				$id = (int) $id;

				if ($id < 1)
				{
					continue;
				}

				// Metric writes queue award profiles before commit; restore that
				// queue if the activity decision rolls back.
				$awardCheckpoint = $service->deferredAwardProfileIds();
				$db->transactionStart(true);

				try
				{
					if (!$this->activityExists($id))
					{
						$db->transactionRollback(true);

						continue;
					}

					$stateChanged = $this->setActivityPublished($id, $published);
					$entriesChanged = $service->setActivityEntriesPublished($id, $published);
					$db->transactionCommit(true);
				}
				catch (\Throwable $error)
				{
					$db->transactionRollback(true);
					$service->replaceDeferredAwardProfiles($awardCheckpoint);

					throw $error;
				}

				if ($entriesChanged || $stateChanged)
				{
					$changed++;
				}
			}
		}
		finally
		{
			$service->flushAwardRecompute();
		}

		return $changed;
	}

	/**
	 * MetricService for this model's database and actor.
	 *
	 * Tests may override to inject an in-memory store.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db       Database driver.
	 * @param   integer                             $actorId  User id.
	 *
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService($db, int $actorId): MetricService
	{
		return MetricService::fromDatabase($db, $actorId);
	}

	/**
	 * Activity rows with the metric entries that decide their approval state.
	 *
	 * @return  object[]|false
	 *
	 * @since   1.0.0
	 */
	public function getItems()
	{
		$items = parent::getItems();

		if (!\is_array($items) || $items === [])
		{
			return $items;
		}

		foreach ($items as $item)
		{
			$published = (int) ($item->published ?? 0);
			$item->pending_entries = $published === 0 ? 1 : 0;
			$item->counted_entries = $published === 1 ? 1 : 0;
			$item->trashed_entries = $published === -2 ? 1 : 0;
			$item->title = MetricEntryTitle::display(
				isset($item->activity_title) ? (string) $item->activity_title : null
			);
			$item->metric_entries = [
				(object) [
					'title'      => (string) ($item->metric_title ?? ''),
					'unit_label' => (string) ($item->unit_label ?? ''),
					'decimals'   => (int) ($item->decimals ?? 0),
					'value'      => $item->value ?? 0,
					'published'  => $published,
				],
			];

			// Approval acts on activity ids; non-activity rows stay unselectable.
			if ((int) ($item->source_type ?? 0) === MetricSource::Activity->value)
			{
				$item->id = (int) ($item->source_id ?? 0);
			}
			else
			{
				$item->id = 0;
			}
		}

		return $items;
	}

	/**
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('me.id', 'entry_id'),
				$db->quoteName('me.profile_id'),
				$db->quoteName('me.entry_date', 'activity_date'),
				$db->quoteName('me.published'),
				$db->quoteName('me.value'),
				$db->quoteName('me.source_type'),
				$db->quoteName('me.source_id'),
				$db->quoteName('d.title', 'metric_title'),
				$db->quoteName('d.unit_label'),
				$db->quoteName('d.decimals'),
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries', 'me'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_metric_definitions', 'd')
				. ' ON ' . $db->quoteName('d.id') . ' = ' . $db->quoteName('me.metric_id')
			)
			->join(
				'LEFT',
				$db->quoteName('#__wendfold_profiles', 'p')
				. ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('me.profile_id')
			)
			->join(
				'LEFT',
				$db->quoteName('#__users', 'u')
				. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			);

		MetricEntryTitle::applyToQuery($query, $db);

		$search = trim((string) StateValue::get($this->getState(), 'filter.search'));

		if ($search !== '')
		{
			$likeTitle = '%' . $db->escape($search, true) . '%';
			$likeName = $likeTitle;
			$titleExpr = MetricEntryTitle::titleExpression($db);
			$query->where(
				'(' . $titleExpr . ' LIKE :searchTitle'
				. ' OR ' . $db->quoteName('u.name') . ' LIKE :searchName)'
			);
			$query->bind(':searchTitle', $likeTitle);
			$query->bind(':searchName', $likeName);
		}

		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'me.entry_date');
		$allowed = ['me.entry_date', 'activity_title', 'me.published', 'me.id', 'member_name'];

		if (!\in_array($orderCol, $allowed, true))
		{
			$orderCol = 'me.entry_date';
		}

		$direction = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$query->order($db->quoteName($orderCol) . ' ' . $direction);

		return $query;
	}

	/**
	 * @param   string  $ordering   Default column.
	 * @param   string  $direction  Default direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'me.entry_date', $direction = 'DESC')
	{
		/** @var \Joomla\CMS\Application\CMSApplication $app */
		$app = Factory::getApplication();
		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		parent::populateState($ordering, $direction);
	}

	/**
	 * Whether the activity row exists.
	 *
	 * @param   integer  $id  Activity id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function activityExists(int $id): bool
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_activities'))
			->where($db->quoteName('id') . ' = :activityId')
			->bind(':activityId', $id, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult() > 0;
	}

	/**
	 * Set activities.published when it differs from the target.
	 *
	 * @param   integer  $id         Activity id.
	 * @param   integer  $published  Target published value.
	 *
	 * @return  boolean  True when the row changed.
	 *
	 * @since   1.0.0
	 */
	private function setActivityPublished(int $id, int $published): bool
	{
		$db = $this->getDatabase();
		$target = $published;
		$current = $published;
		$query = $db->createQuery()
			->update($db->quoteName('#__wendfold_activities'))
			->set($db->quoteName('published') . ' = :published')
			->where($db->quoteName('id') . ' = :activityId')
			->where($db->quoteName('published') . ' <> :publishedCheck')
			->bind(':published', $target, ParameterType::INTEGER)
			->bind(':activityId', $id, ParameterType::INTEGER)
			->bind(':publishedCheck', $current, ParameterType::INTEGER);
		$db->setQuery($query)->execute();

		return $db->getAffectedRows() > 0;
	}


}
