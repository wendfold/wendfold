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
 * @since       1.0.0
 *
 * Officer item model for managing individual officer appointments
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Officer model.
 *
 * @since  1.0.0
 */
class OfficerModel extends AdminModel
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD_OFFICER';

	/**
	 * Method to get the record form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.officer',
			'officer',
			[
				'control'   => 'jform',
				'load_data' => $loadData,
			]
		);

		if (empty($form))
		{
			return false;
		}

		// Limit the member dropdown to profiles not already assigned as CURRENT officers.
		// Editing an existing record keeps the currently selected member available.
		$pk = (int) ($data['id'] ?? $this->getState($this->getName() . '.id'));
		$currentProfileId = 0;

		if (!empty($data['profile_id']))
		{
			$currentProfileId = (int) $data['profile_id'];
		}
		elseif ($pk > 0)
		{
			$item = parent::getItem($pk);
			$currentProfileId = (int) ($item->profile_id ?? 0);
		}

		$profileQuery = "SELECT p.id AS value, CONCAT(u.name, ' (', p.member_number, ')') AS text "
			. "FROM #__wendfold_profiles AS p "
			. "INNER JOIN #__users AS u ON u.id = p.user_id "
			. "WHERE p.published = 1 "
			. "AND (p.id = " . (int) $currentProfileId
			. " OR p.id NOT IN (SELECT o.profile_id FROM #__wendfold_officers AS o WHERE o.published = 1 AND o.is_current = 1)) "
			. "ORDER BY u.name";

		$form->setFieldAttribute('profile_id', 'query', $profileQuery);

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		$data = Factory::getApplication()->getUserState('com_wendfold.edit.officer.data', []);

		if (empty($data))
		{
			$data = $this->getItem();
		}

		return $data;
	}

	/**
	 * Method to get a table object, load it if necessary.
	 *
	 * @param   string  $name     The table name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $options  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 *
	 * @since   1.0.0
	 */
	public function getTable($name = 'Officer', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * Method to save the form data.
	 *
	 * NOTE: The #__wendfold_officers table has NO created_by or modified_by columns.
	 * Do NOT set $data['created_by'] here  -  it will cause a MySQL "Unknown column" error
	 * on INSERT. Timestamps are handled entirely by OfficerTable::store().
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		unset($data['role_custom'], $data['role']);

		return parent::save($data);
	}

	/**
	 * Method to get officer data with joined member information for the edit form.
	 *
	 * @param   integer  $pk  The id of the officer.
	 *
	 * @return  mixed  Object on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		if ($item && $item->id)
		{
			$db    = $this->getDatabase();
			$query = $db->createQuery()
				->select([
					'u.name AS member_name',
					'p.member_number',
					'p.photo',
					]
				)
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->where($db->quoteName('p.id') . ' = ' . (int) $item->profile_id);

			$db->setQuery($query);
			$memberData = $db->loadObject();

			if ($memberData)
			{
				$item->member_name = $memberData->member_name;
				$item->member_number  = $memberData->member_number;
				$item->photo       = $memberData->photo;
			}
		}

		return $item;
	}
}
