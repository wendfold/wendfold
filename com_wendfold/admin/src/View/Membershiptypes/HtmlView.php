<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Membershiptypes;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * Membership type list.
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
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MembershiptypesModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->lookup = [
			'title'    => 'COM_WENDFOLD_MEMBERSHIP_TYPES_TITLE',
			'itemTask' => 'membershiptype',
			'listTask' => 'membershiptypes',
			'view'     => 'membershiptypes',
			'empty'    => 'COM_WENDFOLD_MEMBERSHIP_TYPES_EMPTY',
			'columns'  => [
				[
					'heading' => 'COM_WENDFOLD_MEMBERSHIP_TYPE_IMPORT_CODE',
					'sort'    => 'a.import_code',
					'field'   => 'import_code',
					'kind'    => 'text',
				],
				[
					'heading' => 'COM_WENDFOLD_MEMBERSHIP_TYPE_EXEMPT',
					'sort'    => 'a.exempt_from_renewal',
					'field'   => 'exempt_from_renewal',
					'kind'    => 'yesno',
				],
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
		ToolbarHelper::title(Text::_('COM_WENDFOLD_MEMBERSHIP_TYPES_TITLE'), 'users');

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->addNew('membershiptype.add');
		}

		if ($user->authorise('core.edit.state', 'com_wendfold'))
		{
			$toolbar->publish('membershiptypes.publish');
			$toolbar->unpublish('membershiptypes.unpublish');
		}

		CheckoutAcl::addCheckinButton($toolbar, $user, 'membershiptypes.checkin');

		if ($user->authorise('core.delete', 'com_wendfold'))
		{
			$toolbar->delete('membershiptypes.delete')->message('COM_WENDFOLD_LOOKUP_CONFIRM_DELETE');
		}
	}
}
