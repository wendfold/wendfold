<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Activities;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Member activity list.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    array
	 * @since  1.0.0
	 */
	public $items;

	/**
	 * @var    \Joomla\CMS\Pagination\Pagination
	 * @since  1.0.0
	 */
	public $pagination;

	/**
	 * @var    \Joomla\Registry\Registry
	 * @since  1.0.0
	 */
	public $state;

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\ActivitiesModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();

		if (\count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$user = Factory::getApplication()->getIdentity();

		if (!$user->authorise('core.manage', 'com_wendfold'))
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		ToolbarHelper::title(Text::_('COM_WENDFOLD_ACTIVITIES_TITLE'), 'list');

		if ($user->authorise('core.edit', 'com_wendfold'))
		{
			ToolbarHelper::custom('activities.approve', 'publish', '', 'COM_WENDFOLD_ACTIVITIES_APPROVE', true);
			ToolbarHelper::custom('activities.unapprove', 'unpublish', '', 'COM_WENDFOLD_ACTIVITIES_UNAPPROVE', true);
			ToolbarHelper::custom('activities.reject', 'delete', '', 'COM_WENDFOLD_ACTIVITIES_REJECT', true);
		}

		parent::display($tpl);
	}
}
