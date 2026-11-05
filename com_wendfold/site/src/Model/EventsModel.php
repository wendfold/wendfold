<?php
/**
 * Wendfold - Site Events List Model
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

class EventsModel extends ListModel
{
	protected static $hasPreferredNameColumn = null;

	protected function profilesHavePreferredName(): bool
	{
		if (self::$hasPreferredNameColumn !== null)
		{
			return self::$hasPreferredNameColumn;
		}

		try
		{
			$cols = $this->getDatabase()->getTableColumns('#__wendfold_profiles', false);
			self::$hasPreferredNameColumn = isset($cols['preferred_name']);
		}
		catch (\Throwable $e)
		{
			self::$hasPreferredNameColumn = false;
		}

		return self::$hasPreferredNameColumn;
	}

	protected function getCurrentUserFlags(): ?object
	{
		$userId = (int) Factory::getApplication()->getIdentity()->id;

		if ($userId <= 0)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'p.id AS profile_id',
				'p.is_road_captain',
				'CASE WHEN o.id IS NULL THEN 0 ELSE 1 END AS is_officer',
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin($db->quoteName('#__wendfold_officers', 'o') . ' ON o.profile_id = p.id AND o.is_current = 1 AND o.published = 1')
			->where($db->quoteName('p.user_id') . ' = ' . $userId)
			->where($db->quoteName('p.published') . ' = 1');

		$query->setLimit(1, 0);
		$row = $db->setQuery($query)->loadObject();

		if (!$row)
		{
			return null;
		}

		$row->is_road_captain = !empty($row->is_road_captain);
		$row->is_officer = !empty($row->is_officer);

		return $row;
	}

	protected function applyAudienceScope($query): void
	{
		$db = $this->getDatabase();
		$flags = $this->getCurrentUserFlags();
		$allowed = AudienceHelper::allowedScopes(AudienceHelper::flagsFromProfile($flags));

		$where = [$db->quoteName('e.audience_scope') . ' IN (' . implode(',', $allowed) . ')'];

		if ($flags !== null && !empty($flags->profile_id))
		{
			$profileId = (int) $flags->profile_id;
			$where[] = $db->quoteName('e.volunteer_manager_id') . ' = ' . $profileId;
			$where[] = $db->quoteName('e.volunteer_backup_id') . ' = ' . $profileId;
		}

		$query->where('(' . implode(' OR ', $where) . ')');
	}

	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'e.event_date',
				'e.title',
				'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title',
				'e.status',
			];
		}

		parent::__construct($config);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->createQuery();

		$hasPreferred = $this->profilesHavePreferredName();
		$siteTime = SiteTime::fromApplication();
		$today = $siteTime->today();
		$timeframe = (string) StateValue::get($this->getState(), 'filter.timeframe', 'upcoming');
		$eventType = (int) StateValue::get($this->getState(), 'filter.event_type');
		$status = (int) StateValue::get($this->getState(), 'filter.status');
		$search = trim((string) StateValue::get($this->getState(), 'filter.search', ''));

		$query->select([
			$db->quoteName('e.id'),
			$db->quoteName('e.title'),
			$db->quoteName('e.description'),
			$db->quoteName('e.event_date'),
			$db->quoteName('e.start_date'),
			$db->quoteName('e.end_date'),
			$db->quoteName('e.ksu_time'),
			$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'),
			$db->quoteName('e.workflow_mode'),
			$db->quoteName('e.parent_event_id'),
			$db->quoteName('e.event_location_id'),
			$db->quoteName('e.event_location_custom'),
			$db->quoteName('e.event_city'),
			$db->quoteName('e.event_state'),
			$db->quoteName('e.ksu_location_id'),
			$db->quoteName('e.ksu_location_custom'),
			$db->quoteName('e.ksu_city'),
			$db->quoteName('e.ksu_state'),
			$db->quoteName('e.road_captain_id'),
			$db->quoteName('e.sweep_id'),
			EventMetricCredits::distanceExpression($db, 'e') . ' AS ' . $db->quoteName('distance_credit'),
			$db->quoteName('e.difficulty'),
			$db->quoteName('e.status'),
			$db->quoteName('e.notes'),
			$db->quoteName('loc.name', 'location_name'),
			$db->quoteName('ksu_loc.name', 'ksu_location_name'),
			$db->quoteName('ksu_loc.city', 'ksu_location_city'),
			$db->quoteName('ksu_loc.state', 'ksu_location_state'),
			$db->quoteName('rc_u.name', 'road_captain_name'),
			$db->quoteName('sw_u.name', 'sweep_name'),
			]
		)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->join('LEFT', $db->quoteName('#__wendfold_locations', 'loc') . ' ON ' . $db->quoteName('loc.id') . ' = ' . $db->quoteName('e.event_location_id'))
			->join('LEFT', $db->quoteName('#__wendfold_locations', 'ksu_loc') . ' ON ' . $db->quoteName('ksu_loc.id') . ' = ' . $db->quoteName('e.ksu_location_id'))
			->join('LEFT', $db->quoteName('#__wendfold_profiles', 'rc') . ' ON ' . $db->quoteName('rc.id') . ' = ' . $db->quoteName('e.road_captain_id'))
			->join('LEFT', $db->quoteName('#__users', 'rc_u') . ' ON ' . $db->quoteName('rc_u.id') . ' = ' . $db->quoteName('rc.user_id'))
			->join('LEFT', $db->quoteName('#__wendfold_profiles', 'sw') . ' ON ' . $db->quoteName('sw.id') . ' = ' . $db->quoteName('e.sweep_id'))
			->join('LEFT', $db->quoteName('#__users', 'sw_u') . ' ON ' . $db->quoteName('sw_u.id') . ' = ' . $db->quoteName('sw.user_id'))
			->where($db->quoteName('e.published') . ' = 1')
			->where($db->quoteName('e.show_in_event_list') . ' = 1');

		$this->applyAudienceScope($query);

		$query->where(
			$db->quoteName('e.event_date')
			. ' ' . $siteTime->eventsListOperator($timeframe)
			. ' ' . $db->quote($today)
		);

		if ($eventType > 0)
		{
			$query->where($db->quoteName('e.event_type_id') . ' = ' . $eventType);
		}

		if ($status > 0)
		{
			$query->where($db->quoteName('e.status') . ' = ' . $status);
		}

		if ($search !== '')
		{
			$search = '%' . $db->escape($search, true) . '%';
			$quotedSearch = $db->quote($search, false);
			$query->where(
				'('
				. $db->quoteName('e.title') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.description') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('loc.name') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.event_location_custom') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.event_city') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.event_state') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('ksu_loc.name') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.ksu_location_custom') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.ksu_city') . ' LIKE ' . $quotedSearch
				. ' OR ' . $db->quoteName('e.ksu_state') . ' LIKE ' . $quotedSearch
				. ')'
			);
		}

		if ($hasPreferred)
		{
			$query->select([
				$db->quoteName('rc.preferred_name', 'road_captain_preferred'),
				$db->quoteName('sw.preferred_name', 'sweep_preferred'),
				]
			);
		}

		$direction = $timeframe === 'past' ? 'DESC' : 'ASC';
		$query->order($db->quoteName('e.event_date') . ' ' . $direction . ', ' . $db->quoteName('e.id') . ' ' . $direction);

		return $query;
	}

	protected function populateState($ordering = 'e.event_date', $direction = 'ASC'): void
	{
		$app = Factory::getApplication();
		$input = $app->getInput();

		$timeframe = strtolower((string) $input->getCmd('filter_timeframe', 'upcoming'));

		if (!in_array($timeframe, ['upcoming', 'past'], true))
		{
			$timeframe = 'upcoming';
		}

		$view = strtolower(trim((string) $input->getCmd('filter_view', '')));

		if (!in_array($view, ['cards', 'list'], true))
		{
			$view = '';
		}

		$this->setState('filter.search', trim((string) $input->getString('filter_search', '')));
		$this->setState('filter.timeframe', $timeframe);
		$this->setState('filter.event_type', $input->getInt('filter_event_type', 0));
		$this->setState('filter.status', $input->getInt('filter_status', 0));
		$this->setState('filter.view', $view);

		parent::populateState($ordering, $direction);
		$this->setState('list.limit', 1000);
		$this->setState('list.start', 0);
	}

	protected function getStoreId($id = ''): string
	{
		$id .= ':' . (string) StateValue::get($this->getState(), 'filter.search');
		$id .= ':' . (string) StateValue::get($this->getState(), 'filter.timeframe');
		$id .= ':' . (string) StateValue::get($this->getState(), 'filter.event_type');
		$id .= ':' . (string) StateValue::get($this->getState(), 'filter.status');
		$id .= ':' . (string) StateValue::get($this->getState(), 'filter.view');

		return parent::getStoreId($id);
	}
}
