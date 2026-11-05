<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\View\Duespayment;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

class HtmlView extends BaseHtmlView
{
	protected $form;

	protected $item;

	protected $state;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\DuespaymentModel $model */
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
		$isNew = ($this->item->id == 0);
		$toolbar = Toolbar::getInstance();

		$titleKey = $isNew ? 'COM_WENDFOLD_DUESPAYMENT_NEW' : 'COM_WENDFOLD_DUESPAYMENT_EDIT';

		ToolbarHelper::title(
			Text::_($titleKey),
			'wallet'
		);

		$toolbar->apply('duespayment.apply');
		$toolbar->save('duespayment.save');
		$toolbar->save2new('duespayment.save2new');
		$toolbar->divider();
		$toolbar->cancel('duespayment.cancel');
	}
}
