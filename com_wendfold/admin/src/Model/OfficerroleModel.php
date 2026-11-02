<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

/**
 * One officer role.
 *
 * @since  1.0.0
 */
class OfficerroleModel extends LookupModel
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD_OFFICER_ROLE'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	/**
	 * @param   array    $data      Form data.
	 * @param   boolean  $loadData  Load the stored row.
	 *
	 * @return  \Joomla\CMS\Form\Form|false
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.officerrole',
			'officerrole',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return $form;
	}

	/**
	 * @param   string  $name     Table name.
	 * @param   string  $prefix   Class prefix.
	 * @param   array   $options  Options.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 *
	 * @since   1.0.0
	 */
	public function getTable($name = 'Officerrole', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function usageTable(): string
	{
		return '#__wendfold_officers';
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function usageColumn(): string
	{
		return 'role_id';
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function editContext(): string
	{
		return 'com_wendfold.edit.officerrole.data';
	}
}
