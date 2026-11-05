<?php

/**
 * Wendfold  -  Chapter Management System
 * Administrator Members List Model
 *
 * UPDATED: Added filter_renewal_status support
 *   'verified'  -> national_verified = 1 AND renewal_date >= TODAY
 *   'expiring'  -> renewal_date BETWEEN TODAY AND TODAY+60 days
 *   'expired'   -> renewal_date < TODAY (or national_verified = 0 with past date)
 *   'no_date'   -> renewal_date IS NULL
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Wendfold Members List Model
 */
class MembersModel extends ListModel
{
	/**
	 * Constructor.
	 *
	 * @param   array  $config  Optional configuration array
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id',
				'p.id',
				'user_id',
				'p.user_id',
				'member_number',
				'p.member_number',
				'membership_type',
				'p.membership_type_id',
			'mt.title AS membership_type_title',
				'dues_paid',
				'p.dues_paid',
				'renewal_date',
				'p.renewal_date',
				'member_since',
				'p.member_since',
				'published',
				'p.published',
				'name',
				'u.name',
				'username',
				'u.username',
				'email',
				'u.email',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Builds the database query for the members list.
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery();

		//----------------------------------------------------
		// SELECT
		// Pulls profile data + user account data in one query.
		//----------------------------------------------------
		$query->select([
			'p.id',
			'p.user_id',
			'p.member_number',
			'p.membership_type_id',
			'mt.title AS membership_type_title',
			'p.member_since',
			'p.renewal_date',
			'p.dues_paid',
			'p.dues_paid_date',
			'p.national_verified',
			'p.national_verified_date',
			'p.national_sync_date',
			'p.waiver_signed',
			'p.photo',
			'p.published',
			'p.checked_out',
			'p.checked_out_time',
			'u.name',
			'u.username',
			'u.email',
			'editor.name AS editor',
		])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->join('LEFT', $db->quoteName('#__users', 'editor') . ' ON editor.id = p.checked_out')
			->join('LEFT', $db->quoteName('#__wendfold_membership_types', 'mt') . ' ON mt.id = p.membership_type_id');

		//----------------------------------------------------
		// FILTER: Published / Active status
		//----------------------------------------------------
		$published = StateValue::get($this->getState(), 'filter.published');

		if ($published !== '' && $published !== null)
		{
			$query->where(
				$db->quoteName('p.published') . ' = :published'
			)->bind(':published', $published, ParameterType::INTEGER);
		}

		//----------------------------------------------------
		// FILTER: Membership type
		//----------------------------------------------------
		$membershipType = StateValue::get($this->getState(), 'filter.membership_type');

		if ($membershipType !== '' && $membershipType !== null)
		{
			$query->where(
				$db->quoteName('p.membership_type_id') . ' = :membership_type'
			)->bind(':membership_type', $membershipType, ParameterType::INTEGER);
		}

		//----------------------------------------------------
		// FILTER: Dues paid status
		//----------------------------------------------------
		$duesPaid = StateValue::get($this->getState(), 'filter.dues_paid');

		if ($duesPaid !== '' && $duesPaid !== null)
		{
			$query->where(
				$db->quoteName('p.dues_paid') . ' = :dues_paid'
			)->bind(':dues_paid', $duesPaid, ParameterType::INTEGER);
		}

		// Renewal filters compare the DATE column to the site-local calendar day.
		$renewalStatus = StateValue::get($this->getState(), 'filter.renewal_status');

		if ($renewalStatus !== '' && $renewalStatus !== null)
		{
			$siteTime = SiteTime::fromApplication();
			$renewalToday = $siteTime->today();
			$renewalPlus60 = $siteTime->shiftDays(60);

			switch ($renewalStatus)
			{
				case 'verified':
				case 'current':
					// Nationally verified AND not yet expired
					$query->where($db->quoteName('p.national_verified') . ' = 1');
					$query->where($db->quoteName('p.renewal_date') . ' >= :renewalToday')->bind(':renewalToday', $renewalToday);
					break;

				case 'expiring':
					// Renewal date is upcoming AND within 60 days
					$query->where($db->quoteName('p.renewal_date') . ' >= :renewalToday')->bind(':renewalToday', $renewalToday);
					$query->where($db->quoteName('p.renewal_date') . ' <= :renewalPlus60')->bind(':renewalPlus60', $renewalPlus60);
					break;

				case 'expired':
					// Renewal date is in the past
					$query->where($db->quoteName('p.renewal_date') . ' < :renewalToday')->bind(':renewalToday', $renewalToday);
					break;

				case 'no_date':
				case 'no_record':
					// No renewal date set at all
					$query->where($db->quoteName('p.renewal_date') . ' IS NULL');
					break;
			}
		}

		//----------------------------------------------------
		// SEARCH
		// Searches across name, username, email, and member number
		//----------------------------------------------------
		$search = StateValue::get($this->getState(), 'filter.search');

		if (!empty($search))
		{
			$searchVal = '%' . $db->escape($search, true) . '%';
			$query->where(
				'(' .
					$db->quoteName('u.name') . ' LIKE :search1 OR ' .
					$db->quoteName('u.username') . ' LIKE :search2 OR ' .
					$db->quoteName('u.email') . ' LIKE :search3 OR ' .
					$db->quoteName('p.member_number') . ' LIKE :search4' .
					')'
			)
				->bind(':search1', $searchVal, ParameterType::STRING)
				->bind(':search2', $searchVal, ParameterType::STRING)
				->bind(':search3', $searchVal, ParameterType::STRING)
				->bind(':search4', $searchVal, ParameterType::STRING);
		}

		//----------------------------------------------------
		// ORDERING
		//----------------------------------------------------
		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'u.name');
		$orderDir = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
		$allowedOrderColumns = [
			'u.name',
			'p.member_number',
			'p.membership_type_id',
			'mt.title AS membership_type_title',
			'p.renewal_date',
			'p.dues_paid',
			'p.published',
			'u.username',
			'u.email',
			'p.member_since',
		];

		if (!in_array($orderCol, $allowedOrderColumns, true))
		{
			$orderCol = 'u.name';
		}

		$query->order($db->quoteName($orderCol) . ' ' . $orderDir);

		if ($orderCol !== 'u.name')
		{
			$query->order($db->quoteName('u.name') . ' ASC');
		}

		if ($orderCol !== 'p.id')
		{
			$query->order($db->quoteName('p.id') . ' ASC');
		}

		return $query;
	}

	/**
	 * Populates the model state from the request.
	 *
	 * @param   string  $ordering   Default ordering column
	 * @param   string  $direction  Default ordering direction
	 *
	 * @return  void
	 */
	protected function populateState($ordering = 'u.name', $direction = 'ASC'): void
	{
		$app = Factory::getApplication();

		// Search filter
		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		// Published filter
		$this->setState(
			'filter.published',
			$app->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '', 'string')
		);

		// Membership type filter
		$this->setState(
			'filter.membership_type',
			$app->getUserStateFromRequest($this->context . '.filter.membership_type', 'filter_membership_type', '', 'int')
		);

		// Dues paid filter
		$this->setState(
			'filter.dues_paid',
			$app->getUserStateFromRequest($this->context . '.filter.dues_paid', 'filter_dues_paid', '', 'string')
		);

		// Renewal / expiration status filter   NEW
		$this->setState(
			'filter.renewal_status',
			$app->getUserStateFromRequest($this->context . '.filter.renewal_status', 'filter_renewal_status', '', 'string')
		);

		parent::populateState($ordering, $direction);
	}

	/**
	 * Returns a store id to help ListModel cache results properly.
	 * Must include all filter state values that affect the query.
	 *
	 * @param   string  $id  A prefix for the store id.
	 *
	 * @return  string
	 */
	protected function getStoreId($id = ''): string
	{
		$id .= ':' . StateValue::get($this->getState(), 'filter.search');
		$id .= ':' . StateValue::get($this->getState(), 'filter.published');
		$id .= ':' . StateValue::get($this->getState(), 'filter.membership_type');
		$id .= ':' . StateValue::get($this->getState(), 'filter.dues_paid');
		$id .= ':' . StateValue::get($this->getState(), 'filter.renewal_status');
		$id .= ':' . StateValue::get($this->getState(), 'list.ordering');
		$id .= ':' . StateValue::get($this->getState(), 'list.direction');

		return parent::getStoreId($id);
	}
}
