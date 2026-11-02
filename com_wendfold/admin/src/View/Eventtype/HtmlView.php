<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Eventtype;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Edit one event type.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    \Joomla\CMS\Form\Form
	 * @since  1.0.0
	 */
	protected $form;

	/**
	 * @var    object
	 * @since  1.0.0
	 */
	protected $item;

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
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EventtypeModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->lookup = ['itemTask' => 'eventtype'];

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
		Factory::getApplication()->getInput()->set('hidemainmenu', true);
		$isNew = (int) ($this->item->id ?? 0) === 0;
		$user = Factory::getApplication()->getIdentity();
		$toolbar = Toolbar::getInstance();

		ToolbarHelper::title(
			Text::_($isNew ? 'COM_WENDFOLD_EVENT_TYPE_NEW' : 'COM_WENDFOLD_EVENT_TYPE_EDIT'),
			'calendar'
		);

		$canSave = $isNew
			? $user->authorise('core.create', 'com_wendfold')
			: $user->authorise('core.edit', 'com_wendfold');

		if ($canSave)
		{
			$toolbar->apply('eventtype.apply');
			$toolbar->save('eventtype.save');
		}

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->save2new('eventtype.save2new');
		}

		$toolbar->cancel('eventtype.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}
}
