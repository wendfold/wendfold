<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Eventtypes;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\CheckoutAcl;

/**
 * Event type list.
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
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EventtypesModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->lookup = [
			'title'    => 'COM_WENDFOLD_EVENT_TYPES_TITLE',
			'itemTask' => 'eventtype',
			'listTask' => 'eventtypes',
			'view'     => 'eventtypes',
			'empty'    => 'COM_WENDFOLD_EVENT_TYPES_EMPTY',
			'columns'  => [
				[
					'heading' => 'COM_WENDFOLD_EVENT_TYPE_BEHAVIOR',
					'sort'    => 'a.behavior',
					'field'   => 'behavior',
					'kind'    => 'behavior',
				],
				[
					'heading' => 'COM_WENDFOLD_EVENT_TYPE_DEFAULT_RIDING',
					'sort'    => 'a.default_riding',
					'field'   => 'default_riding',
					'kind'    => 'yesno',
				],
				[
					'heading' => 'COM_WENDFOLD_EVENT_TYPE_DEFAULT_WORKFLOW',
					'sort'    => 'a.default_workflow_mode',
					'field'   => 'default_workflow_mode',
					'kind'    => 'workflow',
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
		ToolbarHelper::title(Text::_('COM_WENDFOLD_EVENT_TYPES_TITLE'), 'calendar');

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->addNew('eventtype.add');
		}

		if ($user->authorise('core.edit.state', 'com_wendfold'))
		{
			$toolbar->publish('eventtypes.publish');
			$toolbar->unpublish('eventtypes.unpublish');
		}

		CheckoutAcl::addCheckinButton($toolbar, $user, 'eventtypes.checkin');

		if ($user->authorise('core.delete', 'com_wendfold'))
		{
			$toolbar->delete('eventtypes.delete')->message('COM_WENDFOLD_LOOKUP_CONFIRM_DELETE');
		}
	}
}
