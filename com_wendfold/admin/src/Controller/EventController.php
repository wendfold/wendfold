<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Event Controller (single record)
 *
 * Handles add, edit, save, apply, cancel, and delete for a single
 * chapter event record. Extends FormController which provides all
 * standard CRUD task methods automatically.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

/**
 * Event Controller
 *
 * Single-record controller for chapter events.
 * FormController provides: add(), edit(), save(), apply(), cancel(), delete().
 *
 * @since  1.0.0
 */
class EventController extends FormController
{
	/**
	 * The view name for the list redirect target.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $view_list = 'events';

	/**
	 * The view name for the edit form.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $view_item = 'event';
}
