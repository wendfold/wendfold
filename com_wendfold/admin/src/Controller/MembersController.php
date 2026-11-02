<?php

/**
 * Wendfold - Chapter Management System
 * Administrator Members List Controller
 *
 * Handles bulk actions on the Members list view:
 * publish, unpublish, delete.
 *
 * Extends AdminController which provides all standard
 * list-level task methods automatically.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;

/**
 * Wendfold Members List Controller
 *
 * This class lives at:
 *   admin/src/Controller/MembersController.php
 *
 * NAMING CONVENTION:
 *   MembersController (plural) = list actions
 *   MemberController  (singular) = single record edit/save
 *
 * AdminController automatically provides:
 *   - publish()   : sets published = 1 for checked items
 *   - unpublish() : sets published = 0 for checked items
 *   - delete()    : removes checked items
 *   - checkin()   : releases checked-out records
 *
 * These map to toolbar button tasks:
 *   task=members.publish
 *   task=members.unpublish
 *   task=members.delete
 */
class MembersController extends ListController
{
	/**
	 * Returns the model name for this controller.
	 *
	 * AdminController uses this to find the right model
	 * when performing bulk operations.
	 *
	 * @param   string  $name    Model name override
	 * @param   string  $prefix  Model prefix override
	 * @param   array   $config  Model config
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 */
	public function getModel($name = 'Member', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
