<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       9.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

/**
 * Front-end Officers model.
 * Returns published, current officers ordered by role_order.
 *
 * @since  9.0.0
 */
class OfficersModel extends ListModel
{
	/**
	 * Build the query  -  only published, current officers.
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 *
	 * @since   9.0.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery();

		$query->select([
			'o.id',
			'r.title AS role_title',
			'r.system_key AS role_system_key',
			'o.role_order',
			'o.term_start',
			'o.term_end',
			'o.is_current',
			'u.name AS member_name',
			'p.photo',
			'p.member_number',
			]
		)
			->from($db->quoteName('#__wendfold_officers', 'o'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = o.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_officer_roles', 'r') . ' ON r.id = o.role_id')
			->where($db->quoteName('o.published') . ' = 1')
			->where($db->quoteName('o.is_current') . ' = 1')
			->order($db->quoteName('o.role_order') . ' ASC, ' . $db->quoteName('u.name') . ' ASC');

		return $query;
	}

	/**
	 * Get all officers (current + historical) for the timeline view.
	 *
	 * @return  array
	 *
	 * @since   9.0.0
	 */
	public function getHistoricalOfficers(): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery();

		$query->select([
			'o.id',
			'r.title AS role_title',
			'r.system_key AS role_system_key',
			'o.role_order',
			'o.term_start',
			'o.term_end',
			'o.is_current',
			'u.name AS member_name',
			'p.photo',
			'p.member_number',
			]
		)
			->from($db->quoteName('#__wendfold_officers', 'o'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = o.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_officer_roles', 'r') . ' ON r.id = o.role_id')
			->where($db->quoteName('o.published') . ' = 1')
			->where($db->quoteName('o.is_current') . ' = 0')
			->order($db->quoteName('o.term_start') . ' DESC, ' . $db->quoteName('o.role_order') . ' ASC');

		$db->setQuery($query);

		return $db->loadObjectList() ?: [];
	}

	/**
	 * No pagination needed  -  chapters rarely have more than 20 officers.
	 *
	 * @param   string  $ordering   Ordering field.
	 * @param   string  $direction  Ordering direction.
	 *
	 * @return  void
	 *
	 * @since   9.0.0
	 */
	protected function populateState($ordering = 'o.role_order', $direction = 'ASC')
	{
		$this->setState('list.limit', 100);
		parent::populateState($ordering, $direction);
	}
}
