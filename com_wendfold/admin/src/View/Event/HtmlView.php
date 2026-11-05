<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Event Edit View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Event;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;

class HtmlView extends BaseHtmlView
{
	protected $form;

	protected $item;

	protected $state;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EventModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->state = $model->getState();

		EventMetricCredits::setFormRows($model->getMetricCreditRows((int) ($this->item->id ?? 0)));

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$isNew   = ($this->item->id == 0);
		$toolbar = Toolbar::getInstance();

		ToolbarHelper::title(
			$isNew
				? Text::_('COM_WENDFOLD_EVENT_NEW_TITLE')
				: Text::_('COM_WENDFOLD_EVENT_EDIT_TITLE'),
			'calendar'
		);

		$toolbar->apply('event.apply');
		$toolbar->save('event.save');
		$toolbar->save2new('event.save2new');
		$toolbar->divider();

		if ($isNew)
		{
			$toolbar->cancel('event.cancel');
		}
		else
		{
			$toolbar->cancel('event.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}
