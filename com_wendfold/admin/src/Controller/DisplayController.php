<?php

/**
 * Wendfold  -  Chapter Management System
 * Administrator Display Controller
 *
 * This is the default controller for the admin side.
 * Joomla routes here when no specific task is given in the URL,
 * e.g. when an admin clicks "Wendfold" in the sidebar menu.
 *
 * It does one job: decide which View to display.
 * By default it shows the Members list view.
 *
 * Extends Joomla's BaseController which handles all the
 * boilerplate request routing, token checking, and task
 * dispatching automatically.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Wendfold Default Display Controller
 *
 * This class lives at:
 *   admin/src/Controller/DisplayController.php
 *
 * Joomla looks for a controller named "DisplayController"
 * as the entry point for any component. The name is not
 * arbitrary  -  it must be exactly DisplayController.
 */
class DisplayController extends BaseController
{
	/**
	 * The default view to show when no view is specified in the URL.
	 *
	 * When an admin clicks the Wendfold menu item with no
	 * additional parameters, Joomla calls display() and this
	 * property tells it which view to load.
	 *
	 * We default to 'members' so the first thing an admin sees
	 * is the chapter member roster.
	 *
	 * @var    string
	 */
	protected $default_view = 'members';

	/**
	 * Displays the requested view.
	 *
	 * Delegates to Joomla's BaseController::display() which handles:
	 *   - Reading the 'view' parameter from the URL
	 *   - Loading the correct View class from /src/View/
	 *   - Loading the correct tmpl layout file
	 *   - Passing the Model to the View
	 *
	 * We override here only to set cachable = false for admin views
	 * (admin pages should never be served from cache).
	 *
	 * @param   bool   $cachable   Whether the view output can be cached
	 * @param   array  $urlparams  Safe URL parameters for cache keying
	 *
	 * @return  BaseController  Returns $this to support method chaining
	 */
	public function display($cachable = false, $urlparams = []): BaseController
	{
		// Admin views are never cached  -  always serve fresh data
		return parent::display(false, $urlparams);
	}
}
