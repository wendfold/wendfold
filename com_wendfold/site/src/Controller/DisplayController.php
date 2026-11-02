<?php

/**
 * Wendfold  -  Chapter Management System
 * Site Display Controller
 *
 * Front-end counterpart to the admin DisplayController.
 * Handles routing for all public and member-facing views:
 *   - Public event listings
 *   - Member portal dashboard
 *   - QR check-in page
 *   - Member profile view
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Controller;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Wendfold Site Display Controller
 *
 * This class lives at:
 *   site/src/Controller/DisplayController.php
 *
 * Default view is 'events'  -  when a visitor hits the
 * Wendfold front-end menu item with no additional
 * parameters they see the upcoming events list.
 *
 * This is the most logical public-facing landing view.
 * Members will navigate deeper via menu items or links.
 */
class DisplayController extends BaseController
{
	/**
	 * The default front-end view.
	 *
	 * Public visitors land on the events list.
	 * Logged-in members can navigate to dashboard, profile, etc.
	 *
	 * @var    string
	 */
	protected $default_view = 'events';

	/**
	 * Displays the requested front-end view.
	 *
	 * Front-end views CAN be cached for public (non-logged-in)
	 * visitors to improve performance  -  e.g. the public events
	 * list doesn't change on every page load.
	 *
	 * Member-specific views (dashboard, profile, check-in) must
	 * NOT be cached  -  we handle this per-view in the View classes.
	 *
	 * @param   bool   $cachable   Whether the view output can be cached
	 * @param   array  $urlparams  Safe URL parameters for cache keying
	 *
	 * @return  BaseController
	 */
	public function display($cachable = false, $urlparams = []): BaseController
	{
		// Safe URL parameters we allow for cache keying
		// These are the only GET params that affect what this
		// component renders  -  anything else is ignored for caching.
		$safeurlparams = [
			'id'       => 'INT',
			'event_id' => 'INT',
			'start'    => 'INT',
			'limit'    => 'INT',
			'layout'   => 'CMD',
			'view'     => 'CMD',
			'format'   => 'CMD',
			'lang'     => 'CMD',
		];

		return parent::display($cachable, $safeurlparams);
	}
}
