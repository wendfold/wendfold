<?php

/**
 * Wendfold  -  Chapter Management System
 * Administrator Members List View
 *
 * Renders the chapter member roster in the Joomla admin panel.
 * This is the first view an admin sees after clicking
 * Wendfold in the sidebar.
 *
 * Follows Joomla's MVC pattern:
 *   - View classes live in /src/View/ViewName/HtmlView.php
 *   - The class is always named HtmlView regardless of view name
 *   - Layout files live in /tmpl/viewname/default.php
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Members;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * Wendfold Members List View
 *
 * IMPORTANT NAMING RULES (the #1 cause of "View not found" errors):
 *
 *   Directory : admin/src/View/Members/        capital M
 *   File name : HtmlView.php                   exact name required
 *   Namespace : ...Administrator\View\Members  matches directory
 *   Class name: HtmlView                       always HtmlView
 *
 * If any of these four things don't match, Joomla can't find the view.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Array of member records returned by the model.
	 *
	 * @var  array
	 */
	protected $items = [];

	/**
	 * Pagination object for the member list.
	 *
	 * @var  \Joomla\CMS\Pagination\Pagination
	 */
	protected $pagination;

	/**
	 * The current filter/search state from the model.
	 *
	 * @var  \Joomla\Registry\Registry
	 */
	protected $state;

	/**
	 * Displays the Members list view.
	 *
	 * Called by DisplayController::display() after Joomla has
	 * resolved the view name from the URL.
	 *
	 * @param   string  $tpl  Optional template override (rarely used)
	 *
	 * @return  void
	 *
	 * @throws  GenericDataException
	 */
	public function display($tpl = null): void
	{
		// Get data from the Members model.
		// GetModel() is inherited from BaseHtmlView  -  it uses
		// the MVCFactory we registered in provider.php to
		// locate MembersModel in /src/Model/MembersModel.php
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MembersModel $model */
		$model = $this->getModel();

		$this->items      = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state      = $model->getState();

		// Check for errors bubbled up from the model
		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		// Build the admin toolbar (buttons at the top of the page)
		$this->addToolbar();

		// Hand off to the layout file:
		// admin/tmpl/members/default.php
		parent::display($tpl);
	}

	/**
	 * Builds the Joomla admin toolbar for the Members list view.
	 *
	 * The toolbar appears at the top of the admin page and provides
	 * action buttons: New, Edit, Delete, etc.
	 *
	 * Uses Joomla's ToolbarHelper which automatically handles
	 * permission checking  -  buttons only appear if the current
	 * user has the right ACL permissions.
	 *
	 * @return  void
	 */
	protected function addToolbar(): void
	{
		$canDo = ContentHelper::getActions('com_wendfold');
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();

		// Page title with Wendfold icon
		ToolbarHelper::title(
			Text::_('COM_WENDFOLD_MEMBERS_TITLE'),
			'users'
		);

		// New member button  -  links to the single member edit view
		$toolbar->addNew('member.add');

		// Divider
		$toolbar->divider();

		// Publish / Unpublish selected members
		$toolbar->publish('members.publish');
		$toolbar->unpublish('members.unpublish');
		CheckoutAcl::addCheckinButton($toolbar, $user, 'members.checkin');

		// Divider
		$toolbar->divider();

		if ($canDo->get('core.delete'))
		{
			$toolbar->delete('members.delete')
				->message('COM_WENDFOLD_CONFIRM_DELETE_MEMBERS');
		}

		// Options. Toolbar::preferences shows this for core.admin or core.options.
		$toolbar->preferences('com_wendfold');

		// Help button  -  we'll wire this to documentation later
	}
}
