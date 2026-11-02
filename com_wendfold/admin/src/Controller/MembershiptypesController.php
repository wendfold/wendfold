<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

\defined('_JEXEC') or die;

/**
 * Membership type list actions. Publish, unpublish, delete, and ordering are POST tasks.
 *
 * @since  1.0.0
 */
class MembershiptypesController extends ListController
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	/**
	 * @param   string  $name    Model name.
	 * @param   string  $prefix  Prefix.
	 * @param   array   $config  Config.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Membershiptype', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
