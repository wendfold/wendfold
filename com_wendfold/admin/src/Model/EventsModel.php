<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Events List Model
 *
 * Schema verified against admin/sql/install.mysql.utf8.sql.
 *
 * Key fields:
 *   - distance credit from event_metric_credits
 *   - ksu_* and ksd_* fields for start/end locations
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

class EventsModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id',            'e.id',
				'title',         'e.title',
				'event_date',    'e.event_date',
				'location_sort', 'location_sort',
				'distance_credit', 'distance_credit',
				'status',        'e.status',
				'published',     'e.published',
			];
		}

		parent::__construct($config);
	}

	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery();

		$query->select([
			$db->quoteName('e.id'),
			$db->quoteName('e.title'),
			$db->quoteName('e.event_date'),
			$db->quoteName('e.ksu_time'),
			$db->quoteName('e.ksd_time'),
			$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'),
			$db->quoteName('e.is_riding_event'),
			// KSU location fields (v1.0.0)
			$db->quoteName('e.ksu_location_id'),
			$db->quoteName('e.ksu_location_custom'),
			$db->quoteName('e.ksu_city'),
			$db->quoteName('e.ksu_state'),
			EventMetricCredits::distanceExpression($db, 'e') . ' AS ' . $db->quoteName('distance_credit'),
			$db->quoteName('e.road_captain_id'),
			$db->quoteName('e.sweep_id'),
			$db->quoteName('e.published'),
			$db->quoteName('e.status'),
			$db->quoteName('e.created'),
			$db->quoteName('e.notes'),
			$db->quoteName('e.checked_out'),
			$db->quoteName('e.checked_out_time'),
			$db->quoteName('editor.name', 'editor'),
			// Joined: KSU saved location name
			$db->quoteName('ksu_loc.name', 'ksu_location_name'),
			'COALESCE(NULLIF(' . $db->quoteName('e.ksu_city') . ", ''), NULLIF(" . $db->quoteName('ksu_loc.name') . ", ''), NULLIF(" . $db->quoteName('e.ksu_location_custom') . ", ''), '') AS " . $db->quoteName('location_sort'),
			// Joined: road captain and sweep display names
			$db->quoteName('rc_u.name', 'road_captain_name'),
			$db->quoteName('sw_u.name', 'sweep_name'),
			// Joined: attendance count
			'COUNT(DISTINCT ' . $db->quoteName('a.id') . ') AS attendance_count',
			]
		);

		$query->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->join('LEFT', $db->quoteName('#__users', 'editor') . ' ON ' . $db->quoteName('editor.id') . ' = ' . $db->quoteName('e.checked_out'));

		// KSU saved location name
		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_locations', 'ksu_loc')
			. ' ON ' . $db->quoteName('ksu_loc.id') . ' = ' . $db->quoteName('e.ksu_location_id')
		);

		// Road captain: profile -> user
		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_profiles', 'rc')
			. ' ON ' . $db->quoteName('rc.id') . ' = ' . $db->quoteName('e.road_captain_id')
		);
		$query->join(
			'LEFT',
			$db->quoteName('#__users', 'rc_u')
			. ' ON ' . $db->quoteName('rc_u.id') . ' = ' . $db->quoteName('rc.user_id')
		);

		// Sweep: profile -> user
		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_profiles', 'sw')
			. ' ON ' . $db->quoteName('sw.id') . ' = ' . $db->quoteName('e.sweep_id')
		);
		$query->join(
			'LEFT',
			$db->quoteName('#__users', 'sw_u')
			. ' ON ' . $db->quoteName('sw_u.id') . ' = ' . $db->quoteName('sw.user_id')
		);

		// Attendance count
		$query->join(
			'LEFT',
			$db->quoteName('#__wendfold_attendance', 'a')
			. ' ON ' . $db->quoteName('a.event_id') . ' = ' . $db->quoteName('e.id')
		);

		$query->group($db->quoteName('e.id'));

		// Filters
		$published = StateValue::get($this->getState(), 'filter.published');

		if ($published !== '' && $published !== null)
		{
			$query->where($db->quoteName('e.published') . ' = ' . (int) $published);
		}

		$status = StateValue::get($this->getState(), 'filter.status');

		if ($status !== '' && $status !== null)
		{
			$query->where($db->quoteName('e.status') . ' = ' . (int) $status);
		}

		$eventType = StateValue::get($this->getState(), 'filter.event_type');

		if ($eventType !== '' && $eventType !== null)
		{
			$query->where($db->quoteName('e.event_type_id') . ' = ' . (int) $eventType);
		}

		$search = StateValue::get($this->getState(), 'filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where($db->quoteName('e.title') . ' LIKE ' . $search);
		}

		// Ordering
		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'e.event_date');
		$orderDir = (string) StateValue::get($this->getState(), 'list.direction', 'DESC');

		$allowedCols = ['e.id', 'e.title', 'e.event_date', 'location_sort', 'distance_credit', 'e.status', 'e.published'];

		if (!in_array($orderCol, $allowedCols, true))
		{
			$orderCol = 'e.event_date';
		}

		$orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

		if ($orderCol === 'location_sort')
		{
			$query->order($db->quoteName('location_sort') . ' ' . $orderDir . ', ' . $db->quoteName('e.ksu_state') . ' ' . $orderDir . ', ' . $db->quoteName('e.event_date') . ' DESC');
		}
		else
		{
			$query->order($db->quoteName($orderCol) . ' ' . $orderDir);
		}

		return $query;
	}

	protected function populateState($ordering = 'e.event_date', $direction = 'DESC')
	{
		$app = Factory::getApplication();

		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		$this->setState(
			'filter.published',
			$app->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '', 'string')
		);

		$this->setState(
			'filter.status',
			$app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string')
		);

		$this->setState(
			'filter.event_type',
			$app->getUserStateFromRequest($this->context . '.filter.event_type', 'filter_event_type', '', 'string')
		);

		parent::populateState($ordering, $direction);
	}
}
