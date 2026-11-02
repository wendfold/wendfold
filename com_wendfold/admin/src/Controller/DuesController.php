<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Dues List Controller
 *
 * Handles bulk actions on the Dues Payments list view (publish, unpublish, delete).
 * Extends ListController for honest publish/delete result messages.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

/**
 * Dues List Controller
 *
 * Provides the model name for AdminController's bulk-action methods.
 *
 * @since  1.0.0
 */
class DuesController extends ListController
{
	/**
	 * Returns the model used by AdminController bulk operations.
	 *
	 * @param   string  $name    Model name override.
	 * @param   string  $prefix  Model class prefix override.
	 * @param   array   $config  Optional configuration array.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Duespayment', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
