<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Officers List Model
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * Officers list model for the admin panel.
 *
 * Returns all officer appointments (current and historical),
 * joined with profile and user account data for display.
 *
 * @since  1.0.0
 */
class OfficersModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  Optional configuration array.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id',         'o.id',
				'role_id',    'o.role_id',
				'role_title', 'r.title',
				'role_order', 'o.role_order',
				'term_start', 'o.term_start',
				'term_end',   'o.term_end',
				'is_current', 'o.is_current',
				'published',  'o.published',
				'name',       'u.name',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Build the database query for the officers list.
	 *
	 * Table alias is 'o' throughout. The filterofficers.xml fullordering
	 * option values must use 'o.' prefix to match (e.g. 'o.role_order ASC').
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery();

		$query->select([
			'o.id',
			'o.profile_id',
			'o.role_id',
			'r.title AS role_title',
			'r.system_key AS role_system_key',
			'o.role_order',
			'o.term_start',
			'o.term_end',
			'o.is_current',
			'o.published',
			'o.created',
			'o.checked_out',
			'o.checked_out_time',
			'u.name AS name',
			'editor.name AS editor',
			'p.member_number',
			'p.photo',
			]
		)
			->from($db->quoteName('#__wendfold_officers', 'o'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = o.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__users', 'editor') . ' ON editor.id = o.checked_out')
			->leftJoin($db->quoteName('#__wendfold_officer_roles', 'r') . ' ON r.id = o.role_id');

		// Filter: published state
		$published = StateValue::get($this->getState(), 'filter.published', '');

		if ($published !== '')
		{
			$query->where($db->quoteName('o.published') . ' = ' . (int) $published);
		}

		// Filter: current / historical
		$isCurrent = StateValue::get($this->getState(), 'filter.is_current', '');

		if ($isCurrent !== '')
		{
			$query->where($db->quoteName('o.is_current') . ' = ' . (int) $isCurrent);
		}

		// Filter: role by exact match
		// FIX: was missing entirely  -  role dropdown silently did nothing before this
		$roleId = (int) StateValue::get($this->getState(), 'filter.role_id', 0);

		if ($roleId > 0)
		{
			$query->where($db->quoteName('o.role_id') . ' = ' . $roleId);
		}

		// Filter: search by member name or role
		$search = StateValue::get($this->getState(), 'filter.search', '');

		if ($search !== '')
		{
			$searchVal = '%' . $db->escape($search, true) . '%';
			$query->where(
				'(' . $db->quoteName('r.title') . ' LIKE ' . $db->quote($searchVal) .
				' OR ' . $db->quoteName('u.name') . ' LIKE ' . $db->quote($searchVal) . ')'
			);
		}

		// Primary ordering from filter form (fullordering field)
		$ordering  = StateValue::get($this->getState(), 'list.ordering', 'o.is_current');
		$direction = StateValue::get($this->getState(), 'list.direction', 'DESC');

		$query->order($db->escape($ordering) . ' ' . $db->escape($direction));

		// Secondary sorts for consistent tie-breaking
		if ($ordering !== 'o.role_order')
		{
			$query->order($db->quoteName('o.role_order') . ' ASC');
		}

		if ($ordering !== 'u.name')
		{
			$query->order($db->quoteName('u.name') . ' ASC');
		}

		return $query;
	}

	/**
	 * Populate the model state from the request.
	 *
	 * @param   string  $ordering   Default ordering field.
	 * @param   string  $direction  Default ordering direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'o.is_current', $direction = 'DESC')
	{
		$app = Factory::getApplication();

		$search = $app->getUserStateFromRequest(
			$this->context . '.filter.search',
			'filter_search',
			'',
			'string'
		);
		$this->setState('filter.search', $search);

		$published = $app->getUserStateFromRequest(
			$this->context . '.filter.published',
			'filter_published',
			'',
			'string'
		);
		$this->setState('filter.published', $published);

		$isCurrent = $app->getUserStateFromRequest(
			$this->context . '.filter.is_current',
			'filter_is_current',
			'',
			'string'
		);
		$this->setState('filter.is_current', $isCurrent);

		// FIX: role filter was never read from request  -  dropdown silently did nothing
		$roleId = $app->getUserStateFromRequest(
			$this->context . '.filter.role_id',
			'filter_role_id',
			0,
			'int'
		);
		$this->setState('filter.role_id', $roleId);

		parent::populateState($ordering, $direction);
	}
}
