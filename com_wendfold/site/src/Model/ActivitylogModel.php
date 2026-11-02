<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Model;

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricValue;

/**
 * A member logs an activity and the metrics it counts toward.
 *
 * @since  1.0.0
 */
class ActivitylogModel extends BaseDatabaseModel
{
	/**
	 * Published profile for the logged-in user.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getProfile(): ?object
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user || $user->guest)
		{
			return null;
		}

		$db = $this->getDatabase();
		$userId = (int) $user->id;
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->where($db->quoteName('published') . ' = :published')
			->bind(':userId', $userId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER);

		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
	}

	/**
	 * Metrics a member may log.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getMemberMetrics(): array
	{
		$db = $this->getDatabase();
		$published = 1;
		$allowed = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				$db->quoteName('unit_label'),
				$db->quoteName('decimals'),
				$db->quoteName('member_entry_needs_approval'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('published') . ' = :published')
			->where($db->quoteName('allow_member_entry') . ' = :allowed')
			->bind(':published', $published, ParameterType::INTEGER)
			->bind(':allowed', $allowed, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Recent metric entries for a profile with shared source titles.
	 *
	 * @param   integer  $profileId  Profile id.
	 * @param   integer  $limit      Max rows.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getRecentEntries(int $profileId, int $limit = 20): array
	{
		if ($profileId <= 0)
		{
			return [];
		}

		$db = $this->getDatabase();
		$query = $this->buildEntriesTitleQuery($profileId);
		$query->setLimit(max(1, $limit), 0);

		$rows = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($rows as $row)
		{
			$row->activity_title = MetricEntryTitle::display(
				isset($row->activity_title) ? (string) $row->activity_title : null
			);
		}

		return $rows;
	}

	/**
	 * List query for a profile's metric entries with shared source titles.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	public function buildEntriesTitleQuery(int $profileId)
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('me.id'),
				$db->quoteName('me.entry_date'),
				$db->quoteName('me.value'),
				$db->quoteName('me.published'),
				$db->quoteName('me.source_type'),
				$db->quoteName('d.title', 'metric_title'),
				$db->quoteName('d.unit_label'),
				$db->quoteName('d.decimals'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_entries', 'me'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_metric_definitions', 'd')
				. ' ON ' . $db->quoteName('d.id') . ' = ' . $db->quoteName('me.metric_id')
			);

		MetricEntryTitle::applyToQuery($query, $db);

		$query->where($db->quoteName('me.profile_id') . ' = :ownProfileId')
			->bind(':ownProfileId', $profileId, ParameterType::INTEGER)
			->order($db->quoteName('me.entry_date') . ' DESC, ' . $db->quoteName('me.id') . ' DESC');

		return $query;
	}

	/**
	 * Motorcycles owned by the profile.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getMotorcycles(int $profileId): array
	{
		$db = $this->getDatabase();
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('model_year'),
				$db->quoteName('make'),
				$db->quoteName('model'),
				$db->quoteName('nickname'),
				]
			)
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('profile_id') . ' = :profileId')
			->where($db->quoteName('published') . ' = :published')
			->bind(':profileId', $profileId, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->order($db->quoteName('is_primary') . ' DESC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Store one activity and its metric entries.
	 *
	 * @param   integer              $profileId  Owning profile.
	 * @param   array<string, mixed> $data       Filtered input.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function storeActivity(int $profileId, array $data): bool
	{
		$date = (string) ($data['activity_date'] ?? '');
		$title = trim((string) ($data['title'] ?? ''));

		if (!\Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod::isDay($date) || $title === '')
		{
			$this->setError('COM_WENDFOLD_ACTIVITY_INVALID');

			return false;
		}

		$metrics = [];

		foreach ($this->getMemberMetrics() as $metric)
		{
			$metrics[(int) $metric->id] = $metric;
		}

		$values = [];
		$needsApproval = [];
		$posted = \is_array($data['metrics'] ?? null) ? $data['metrics'] : [];

		try
		{
			foreach ($posted as $metricId => $raw)
			{
				$metricId = (int) $metricId;
				$metric = $metrics[$metricId] ?? null;

				if ($metric === null)
				{
					continue;
				}

				$normalised = MetricValue::normalise((string) $raw, (int) $metric->decimals);

				if ($normalised === null)
				{
					continue;
				}

				$values[$metricId] = $normalised;
				$needsApproval[$metricId] = (int) $metric->member_entry_needs_approval === 1;
			}
		}
		catch (\InvalidArgumentException)
		{
			$this->setError('COM_WENDFOLD_METRIC_CREDIT_INVALID');

			return false;
		}

		if ($values === [])
		{
			$this->setError('COM_WENDFOLD_ACTIVITY_VALUE_REQUIRED');

			return false;
		}

		$user = Factory::getApplication()->getIdentity();
		$userId = (int) $user->id;
		$now = Factory::getDate()->toSql();
		$motorcycleId = (int) ($data['motorcycle_id'] ?? 0);

		if ($motorcycleId > 0 && !$this->ownsMotorcycle($profileId, $motorcycleId))
		{
			$motorcycleId = 0;
		}

		$pending = false;

		foreach ($needsApproval as $needs)
		{
			if ($needs)
			{
				$pending = true;
				break;
			}
		}

		$db = $this->getDatabase();
		$row = (object) [
			'profile_id'     => $profileId,
			'activity_date'  => $date,
			'title'          => $title,
			'description'    => trim((string) ($data['description'] ?? '')),
			'motorcycle_id'  => $motorcycleId > 0 ? $motorcycleId : null,
			'start_location' => trim((string) ($data['start_location'] ?? '')),
			'end_location'   => trim((string) ($data['end_location'] ?? '')),
			'published'      => $pending ? 0 : 1,
			'created'        => $now,
			'created_by'     => $userId,
			'modified'       => $now,
			'modified_by'    => $userId,
		];

		$metrics = $this->metricService($db, $userId);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$db->insertObject('#__wendfold_activities', $row, 'id');
			$metrics->recordActivity(
				(int) $row->id,
				$profileId,
				$date,
				$values,
				$needsApproval
			);
			$db->transactionCommit(true);
			$metrics->flushAwardRecompute();
		}
		catch (\InvalidArgumentException)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			$this->setError('COM_WENDFOLD_METRIC_CREDIT_INVALID');

			return false;
		}
		catch (\Throwable $error)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
			$this->setError('COM_WENDFOLD_ERROR_OPERATION_FAILED');

			return false;
		}

		$this->setState('activity.pending', $pending);

		return true;
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
	 * Whether the motorcycle belongs to this profile.
	 *
	 * @param   integer  $profileId     Profile id.
	 * @param   integer  $motorcycleId  Motorcycle id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function ownsMotorcycle(int $profileId, int $motorcycleId): bool
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('id') . ' = :motorcycleId')
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':motorcycleId', $motorcycleId, ParameterType::INTEGER)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult() > 0;
	}
}
