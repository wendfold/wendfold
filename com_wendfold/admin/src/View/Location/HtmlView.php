<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\View\Location;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
	protected $form;

	protected $item;

	protected $state;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\LocationModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->state = $model->getState();

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
				? Text::_('COM_WENDFOLD_LOCATION_NEW_TITLE')
				: Text::_('COM_WENDFOLD_LOCATION_EDIT_TITLE'),
			'map-marker'
		);

		$toolbar->apply('location.apply');
		$toolbar->save('location.save');
		$toolbar->save2new('location.save2new');
		$toolbar->divider();

		// Use JTOOLBAR_CANCEL for new records, JTOOLBAR_CLOSE when editing existing
		if ($isNew)
		{
			$toolbar->cancel('location.cancel', 'JTOOLBAR_CANCEL');
		}
		else
		{
			$toolbar->cancel('location.cancel', 'JTOOLBAR_CLOSE');
		}
	}
}
