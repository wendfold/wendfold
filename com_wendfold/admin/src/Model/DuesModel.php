<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Dues Payments List Model
 *
 * Powers the dues payments list view with filtering by member name/member number
 * and by dues year. Results are ordered by payment date descending by default.
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
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * Dues Model
 *
 * List model for dues payment records. Supports search (by member name or
 * member number) and year filter. Sortable on id, year, amount_paid,
 * payment_date, and member_name.
 *
 * @since  1.0.0
 */
class DuesModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * Registers the sortable/filterable column list before calling parent.
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
				'id', 'd.id',
				'year', 'd.year',
				'amount_paid', 'd.amount_paid',
				'payment_date', 'd.payment_date',
				'member_name',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Build the database query for the dues payments list.
	 *
	 * Joins dues_payments with profiles and users. Applies optional
	 * search filter (member name or member number) and year filter.
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
			'd.id', 'd.profile_id', 'd.year', 'd.amount_paid', 'd.payment_date',
			'd.payment_method', 'd.receipt_number', 'd.notes',
			'd.checked_out', 'd.checked_out_time',
			'u.name AS member_name',
			'editor.name AS editor',
			'p.member_number',
			]
		)
			->from($db->quoteName('#__wendfold_dues_payments', 'd'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = d.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__users', 'editor') . ' ON editor.id = d.checked_out');

		// Filter: search by name or member number
		$search = StateValue::get($this->getState(), 'filter.search');

		if (!empty($search))
		{
			$search = $db->quote('%' . $db->escape($search, true) . '%');
			$query->where('(u.name LIKE ' . $search . ' OR p.member_number LIKE ' . $search . ')');
		}

		// Filter: dues year
		$year = StateValue::get($this->getState(), 'filter.year');

		if (is_numeric($year))
		{
			$query->where('d.year = ' . (int) $year);
		}

		// Ordering
		$orderCol  = (string) StateValue::get($this->getState(), 'list.ordering', 'd.payment_date');
		$orderDirn = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$allowedOrderColumns = ['d.id', 'd.year', 'd.amount_paid', 'd.payment_date', 'member_name'];

		if (!in_array($orderCol, $allowedOrderColumns, true))
		{
			$orderCol = 'd.payment_date';
		}

		$query->order($db->escape($orderCol) . ' ' . $orderDirn);

		if ($orderCol !== 'd.payment_date')
		{
			$query->order($db->quoteName('d.payment_date') . ' DESC');
		}

		if ($orderCol !== 'd.id')
		{
			$query->order($db->quoteName('d.id') . ' DESC');
		}

		return $query;
	}

	/**
	 * Populate the model state from request parameters.
	 *
	 * Reads filter_search and filter_year from the request, storing them
	 * in the model state for use by getListQuery().
	 *
	 * @param   string  $ordering   Default ordering column.
	 * @param   string  $direction  Default ordering direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'd.payment_date', $direction = 'DESC')
	{
		$app = Factory::getApplication();

		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		$this->setState(
			'filter.year',
			$app->getUserStateFromRequest($this->context . '.filter.year', 'filter_year', (string) SiteTime::fromApplication()->year(), 'string')
		);

		parent::populateState($ordering, $direction);
	}
}
