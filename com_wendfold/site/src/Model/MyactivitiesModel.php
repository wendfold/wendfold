<?php

/**
 * Wendfold - Chapter Management System
 * Site My Activities Model
 *
 * Lists the logged-in member's own metric_entries and per-metric totals.
 * The member is always resolved from the current user; request profile or
 * member ids are ignored.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricQuery;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Read-only list of the current member's metric entries.
 *
 * @since  1.0.0
 */
class MyactivitiesModel extends ListModel
{
	/**
	 * Inclusive start day for all-time totals.
	 *
	 * @since  1.0.0
	 */
	private const TOTALS_START = '1900-01-01';

	/**
	 * Request/session filter keys that must never widen the list scope.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const SCOPE_FILTER_KEYS = [
		'profile_id',
		'member_id',
		'user_id',
		'id',
	];

	/**
	 * Whether a visitor may open the page (logged-in user required).
	 *
	 * @param   object|null  $user  Application identity, or null.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function allowsVisitor(?object $user): bool
	{
		return $user !== null && (int) ($user->id ?? 0) > 0;
	}

	/**
	 * Resolve the published chapter profile for a Joomla user id.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getProfileByUserId(int $userId): ?object
	{
		if ($userId <= 0)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select($db->quoteName(['id', 'user_id', 'member_number']))
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->where($db->quoteName('published') . ' = 1')
			->bind(':userId', $userId, ParameterType::INTEGER);

		$query->setLimit(1, 0);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Own profile id for the given user. Always 0 for guests.
	 *
	 * Request parameters such as profile_id or member_id are never read.
	 *
	 * @param   integer  $userId  Joomla user id from the current identity.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function resolveOwnProfileId(int $userId): int
	{
		$profile = $this->getProfileByUserId($userId);

		return $profile !== null ? (int) $profile->id : 0;
	}

	/**
	 * Per-metric counted totals for the current member (published = 1 only).
	 *
	 * Reuses MetricQuery::profileAggregate so totals match MetricService.
	 * The profile is resolved from the current user; forged ids are ignored.
	 *
	 * @return  object[]  Each row: metric_id, title, unit, decimals, total.
	 *
	 * @since   1.0.0
	 */
	public function getMetricTotals(): array
	{
		$profileId = $this->currentOwnProfileId();

		if ($profileId <= 0)
		{
			return [];
		}

		$db = $this->getDatabase();
		$range = $this->totalsDateRange();
		$aggregate = MetricQuery::profileAggregate($db, $profileId, $range['start'], $range['end']);
		$definitions = $this->loadPublishedDefinitions($db);
		$totals = [];

		foreach ($definitions as $definition)
		{
			$metricId = (int) $definition->id;
			$row = $aggregate[$metricId] ?? null;
			$total = $row === null
				? '0'
				: MetricQuery::pickedTotal($row, (int) $definition->aggregation);

			if ($total === '0' || (float) $total == 0.0)
			{
				continue;
			}

			$totals[] = (object) [
				'metric_id'  => $metricId,
				'title'      => LookupTitle::display((string) $definition->title),
				'unit_label' => (string) $definition->unit_label,
				'decimals'   => (int) $definition->decimals,
				'total'      => $total,
			];
		}

		return $totals;
	}

	/**
	 * Inclusive local-date bounds for all-time totals.
	 *
	 * The end day is today in the site zone via SiteTime.
	 *
	 * @return  array{start: string, end: string}
	 *
	 * @since   1.0.0
	 */
	public function totalsDateRange(): array
	{
		$end = SiteTime::fromApplication()->today();

		if (!MetricPeriod::isDay($end))
		{
			$end = '9999-12-31';
		}

		return [
			'start' => self::TOTALS_START,
			'end'   => $end,
		];
	}

	/**
	 * @param   array  $config  Model config.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			// No profile_id / member_id: those must never be request filters.
			$config['filter_fields'] = [
				'me.entry_date',
				'me.id',
				'd.title',
				'me.published',
			];
		}

		parent::__construct($config);
	}

	/**
	 * List query scoped to the current user's published profile only.
	 *
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->createQuery();
		$profileId = $this->currentOwnProfileId();

		$query->select([
			$db->quoteName('me.id'),
			$db->quoteName('me.profile_id'),
			$db->quoteName('me.metric_id'),
			$db->quoteName('me.entry_date'),
			$db->quoteName('me.value'),
			$db->quoteName('me.published'),
			$db->quoteName('me.note'),
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
	 * Let the parent load list filters, then force own-profile scope.
	 *
	 * Parent populateState copies every request filter[...] key into state and
	 * session user-state. A forged filter[profile_id] must not stick.
	 *
	 * @param   string  $ordering   Default ordering.
	 * @param   string  $direction  Default direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'me.entry_date', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);

		$profileId = $this->currentOwnProfileId();

		foreach (self::SCOPE_FILTER_KEYS as $key)
		{
			$this->setState('filter.' . $key, null);
		}

		// Force own id after clearing so session/user-state cannot widen scope.
		$this->setState('filter.profile_id', $profileId);

		$app = Factory::getApplication();

		if ($this->context && \is_callable([$app, 'setUserState']))
		{
			foreach (self::SCOPE_FILTER_KEYS as $key)
			{
				$app->setUserState($this->context . '.filter.' . $key, null);
			}

			$app->setUserState($this->context . '.filter.profile_id', $profileId);
		}
	}

	/**
	 * @param   string  $id  Store id seed.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function getStoreId($id = ''): string
	{
		$id .= ':' . $this->currentOwnProfileId();

		return parent::getStoreId($id);
	}

	/**
	 * Published profile id for the current identity. Never from request/state.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function currentOwnProfileId(): int
	{
		$userId = (int) Factory::getApplication()->getIdentity()->id;

		return $this->resolveOwnProfileId($userId);
	}

	/**
	 * Published metric definitions in display order.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db  Database driver.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	private function loadPublishedDefinitions($db): array
	{
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				$db->quoteName('unit_label'),
				$db->quoteName('decimals'),
				$db->quoteName('aggregation'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_definitions'))
			->where($db->quoteName('published') . ' = :published')
			->bind(':published', $published, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}
}
