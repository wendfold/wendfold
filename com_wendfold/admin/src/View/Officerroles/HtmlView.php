<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Officerroles;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * Officer role list.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    array
	 * @since  1.0.0
	 */
	protected $items;

	/**
	 * @var    \Joomla\CMS\Pagination\Pagination
	 * @since  1.0.0
	 */
	protected $pagination;

	/**
	 * @var    \Joomla\Registry\Registry
	 * @since  1.0.0
	 */
	protected $state;

	/**
	 * @var    array
	 * @since  1.0.0
	 */
	protected $lookup = [];

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\OfficerrolesModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->lookup = [
			'title'    => 'COM_WENDFOLD_OFFICER_ROLES_TITLE',
			'itemTask' => 'officerrole',
			'listTask' => 'officerroles',
			'view'     => 'officerroles',
			'empty'    => 'COM_WENDFOLD_OFFICER_ROLES_EMPTY',
			'columns'  => [
				['heading' => 'COM_WENDFOLD_OFFICER_ROLE_SYSTEM_KEY', 'sort' => 'a.system_key', 'field' => 'system_key', 'kind' => 'text'],
			],
		];

		if (\count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar(): void
	{
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();
		ToolbarHelper::title(Text::_('COM_WENDFOLD_OFFICER_ROLES_TITLE'), 'user');

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->addNew('officerrole.add');
		}

		if ($user->authorise('core.edit.state', 'com_wendfold'))
		{
			$toolbar->publish('officerroles.publish');
			$toolbar->unpublish('officerroles.unpublish');
		}

		CheckoutAcl::addCheckinButton($toolbar, $user, 'officerroles.checkin');

		if ($user->authorise('core.delete', 'com_wendfold'))
		{
			$toolbar->delete('officerroles.delete')->message('COM_WENDFOLD_LOOKUP_CONFIRM_DELETE');
		}
	}
}
