<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Locations List Model
 *
 * Powers the saved chapter locations list view. Supports filtering
 * by location type and name search, with default ordering by name.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * Locations List Model
 *
 * Supports filter_location_type (int) and filter_search (name LIKE).
 * Sortable on id, name, location_type, published.
 *
 * @since  1.0.0
 */
class LocationsModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * Registers sortable/filterable column list before calling parent.
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
				'id', 'l.id',
				'name', 'l.name',
				'location_type', 'l.location_type',
				'is_default', 'l.is_default',
				'published', 'l.published',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Build the database query for the locations list.
	 *
	 * Applies optional location_type filter and name search.
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(['l.id', 'l.name', 'l.location_type', 'l.address', 'l.city',
					  'l.state', 'l.zip', 'l.is_default', 'l.published', 'l.notes',
					  'l.checked_out', 'l.checked_out_time', 'editor.name AS editor']
			)
			->from($db->quoteName('#__wendfold_locations', 'l'))
			->leftJoin($db->quoteName('#__users', 'editor') . ' ON editor.id = l.checked_out');

		// Filter: location type
		$type = StateValue::get($this->getState(), 'filter.location_type');

		if ($type !== '' && $type !== null)
		{
			$query->where($db->quoteName('l.location_type') . ' = ' . (int) $type);
		}

		// Filter: name search
		$search = StateValue::get($this->getState(), 'filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where($db->quoteName('l.name') . ' LIKE ' . $search);
		}

		// Ordering
		$orderCol  = (string) StateValue::get($this->getState(), 'list.ordering', 'l.name');
		$orderDirn = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
		$allowedOrderColumns = ['l.id', 'l.name', 'l.location_type', 'l.is_default', 'l.published'];

		if (!in_array($orderCol, $allowedOrderColumns, true))
		{
			$orderCol = 'l.name';
		}

		$query->order($db->quoteName($orderCol) . ' ' . $orderDirn);

		if ($orderCol !== 'l.name')
		{
			$query->order($db->quoteName('l.name') . ' ASC');
		}

		return $query;
	}

	/**
	 * Populate the model state from request parameters.
	 *
	 * @param   string  $ordering   Default ordering column.
	 * @param   string  $direction  Default ordering direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'l.name', $direction = 'ASC')
	{
		$app = Factory::getApplication();

		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		$this->setState(
			'filter.location_type',
			$app->getUserStateFromRequest($this->context . '.filter.location_type', 'filter_location_type', '', 'string')
		);

		parent::populateState($ordering, $direction);
	}
}
