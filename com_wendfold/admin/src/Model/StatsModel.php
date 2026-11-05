<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricQuery;
use Wendfold\Component\Wendfold\Administrator\Service\MetricAggregation;
use Wendfold\Component\Wendfold\Administrator\Service\MetricPeriod;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * Member statistics and leaderboards from metric_entries.
 *
 * Totals are read from that table alone. Names are attached afterwards.
 *
 * @since  1.0.0
 */
class StatsModel extends ListModel
{
	/**
	 * Cached member rows for the current period.
	 *
	 * @var    array|null
	 * @since  1.0.0
	 */
	private ?array $memberCache = null;

	/**
	 * @param   array  $config  Configuration.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'member_name',
				'total_miles_ytd',
				'events_attended_ytd',
			];
		}

		parent::__construct($config);
	}

	/**
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getItems()
	{
		$rows = $this->memberRows();
		$limit = (int) StateValue::get($this->getState(), 'list.limit');
		$start = (int) StateValue::get($this->getState(), 'list.start');

		if ($limit > 0)
		{
			return \array_slice($rows, $start, $limit);
		}

		return $rows;
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getTotal()
	{
		return \count($this->memberRows());
	}

	/**
	 * Top members for the distance metric.
	 *
	 * @param   integer  $limit  Row limit.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getTopMiles($limit = 10)
	{
		return $this->topForKey('distance', (int) $limit, 'total_miles_ytd');
	}

	/**
	 * Top members for the events-attended metric.
	 *
	 * @param   integer  $limit  Row limit.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getTopAttendance($limit = 10)
	{
		return $this->topForKey('events_attended', (int) $limit, 'events_attended_ytd');
	}

	/**
	 * Chapter totals for the site calendar year.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getChapterSummary()
	{
		$db = $this->getDatabase();
		$period = $this->period();
		$distance = $this->metric($db, 'distance');
		$events = $this->metric($db, 'events_attended');
		$miles = $distance['id'] > 0
			? MetricQuery::chapterTotal($db, $distance['id'], $period['start'], $period['end'], $distance['aggregation'])
			: (object) ['total' => 0, 'members' => 0];
		$attended = $events['id'] > 0
			? MetricQuery::chapterTotal($db, $events['id'], $period['start'], $period['end'], $events['aggregation'])
			: (object) ['total' => 0, 'members' => 0];
		$published = 1;
		$count = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('published') . ' = :published')
			->bind(':published', $published, ParameterType::INTEGER);

		return (object) [
			'total_miles' => $miles->total,
			'total_participations' => $attended->total,
			'total_members' => (int) $db->setQuery($count)->loadResult(),
		];
	}

	/**
	 * @param   array    $data      Form data.
	 * @param   boolean  $loadData  Load stored filters.
	 *
	 * @return  \Joomla\CMS\Form\Form|boolean
	 *
	 * @since   1.0.0
	 */
	public function getFilterForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.stats.filter',
			'filterstats',
			[
				'control' => '',
				'load_data' => $loadData,
			]
		);

		return $form;
	}

	/**
	 * @param   string  $ordering   Default column.
	 * @param   string  $direction  Default direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'total_miles_ytd', $direction = 'DESC')
	{
		$search = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
		$this->setState('filter.search', $search);
		parent::populateState($ordering, $direction);
	}

	/**
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		return $this->getDatabase()->createQuery()->select('1');
	}

	/**
	 * Published members with this year's metric totals.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	private function memberRows(): array
	{
		if ($this->memberCache !== null)
		{
			return $this->memberCache;
		}

		$db = $this->getDatabase();
		$period = $this->period();
		$distance = $this->metric($db, 'distance');
		$events = $this->metric($db, 'events_attended');
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('p.id', 'profile_id'),
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'INNER',
				$db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.published') . ' = :published')
			->bind(':published', $published, ParameterType::INTEGER);
		$search = trim((string) StateValue::get($this->getState(), 'filter.search'));

		if ($search !== '')
		{
			$likeName = '%' . $db->escape($search, true) . '%';
			$likeNumber = $likeName;
			$query->where(
				'(' . $db->quoteName('u.name') . ' LIKE :searchName OR '
				. $db->quoteName('p.member_number') . ' LIKE :searchNumber)'
			);
			$query->bind(':searchName', $likeName);
			$query->bind(':searchNumber', $likeNumber);
		}

		$profiles = $db->setQuery($query)->loadObjectList('profile_id') ?: [];
		$ids = array_map('intval', array_keys($profiles));
		$miles = $this->totalsFor($db, $distance, $period);
		$attended = $this->totalsFor($db, $events, $period);
		$dates = MetricQuery::latestDates($db, $period['start'], $period['end'], $ids);
		$rows = [];

		foreach ($profiles as $id => $profile)
		{
			$id = (int) $id;
			$profile->total_miles_ytd = $miles[$id] ?? 0;
			$profile->events_attended_ytd = $attended[$id] ?? 0;
			$profile->last_entry_date = $dates[$id] ?? '';
			$rows[] = $profile;
		}

		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'total_miles_ytd');
		$allowed = ['member_name', 'total_miles_ytd', 'events_attended_ytd'];

		if (!\in_array($orderCol, $allowed, true))
		{
			$orderCol = 'total_miles_ytd';
		}

		$direction = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'DESC')) === 'ASC' ? 1 : -1;
		usort(
			$rows,
			static function (object $left, object $right) use ($orderCol, $direction): int {
				$cmp = $left->$orderCol <=> $right->$orderCol;

				return $cmp * $direction;
			}
		);
		$this->memberCache = $rows;

		return $rows;
	}

	/**
	 * @param   string   $key    metric_key.
	 * @param   integer  $limit  Row limit.
	 * @param   string   $field  Property the template reads.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	private function topForKey(string $key, int $limit, string $field): array
	{
		$db = $this->getDatabase();
		$metric = $this->metric($db, $key);

		if ($metric['id'] < 1)
		{
			return [];
		}

		$period = $this->period();
		$rows = MetricQuery::leaderboard(
			$db,
			$metric['id'],
			$period['start'],
			$period['end'],
			$metric['aggregation'],
			$limit
		);
		$rows = MetricQuery::withNames($db, $rows);

		foreach ($rows as $row)
		{
			$row->$field = $row->total;
		}

		return $rows;
	}

	/**
	 * Profile id to total for one metric.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db      Database driver.
	 * @param   array{id: int, aggregation: int}    $metric  Metric id and aggregation.
	 * @param   array{start: string, end: string}   $period  Inclusive dates.
	 *
	 * @return  array<int, float>
	 *
	 * @since   1.0.0
	 */
	private function totalsFor($db, array $metric, array $period): array
	{
		$totals = [];

		if ($metric['id'] < 1)
		{
			return $totals;
		}

		foreach (MetricQuery::leaderboard(
			$db,
			$metric['id'],
			$period['start'],
			$period['end'],
			$metric['aggregation']
		) as $row
		)
		{
			$totals[(int) $row->profile_id] = (float) $row->total;
		}

		return $totals;
	}

	/**
	 * @param   \Joomla\Database\DatabaseInterface  $db   Database driver.
	 * @param   string                              $key  metric_key.
	 *
	 * @return  array{id: int, aggregation: int}
	 *
	 * @since   1.0.0
	 */
	private function metric($db, string $key): array
	{
		$id = MetricQuery::idForKey($db, $key);
		$aggregation = MetricAggregation::Sum->value;

		if ($id > 0)
		{
			$stored = 0;
			$query = $db->createQuery()
				->select($db->quoteName('aggregation'))
				->from($db->quoteName('#__wendfold_metric_definitions'))
				->where($db->quoteName('id') . ' = :metricId')
				->bind(':metricId', $id, ParameterType::INTEGER);
			$stored = (int) $db->setQuery($query)->loadResult();
			$aggregation = MetricAggregation::fromStored($stored)->value;
		}

		return ['id' => $id, 'aggregation' => $aggregation];
	}

	/**
	 * @return  array{start: string, end: string, year: int}
	 *
	 * @since   1.0.0
	 */
	private function period(): array
	{
		return MetricPeriod::calendarYear(MetricPeriod::siteZone());
	}
}
