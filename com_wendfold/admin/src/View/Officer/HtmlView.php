<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * Officer edit view
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Officer;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * View class for editing a single officer record.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Form object
	 *
	 * @var  \Joomla\CMS\Form\Form
	 */
	protected $form;

	/**
	 * The officer item
	 *
	 * @var  object
	 */
	protected $item;

	/**
	 * Model state
	 *
	 * @var  \Joomla\Registry\Registry
	 */
	protected $state;

	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 * @throws  \Exception
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\OfficerModel $model */
		$model = $this->getModel();

		$this->form  = $model->getForm();
		$this->item  = $model->getItem();
		$this->state = $model->getState();

		if (count($errors = $model->getErrors()))
		{
			throw new \Exception(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar(): void
	{
		$isNew = ($this->item->id == 0);

		// Use existing language keys to avoid duplicates (and avoid relying on core JNEW/JEDIT keys).
		$title = $isNew ? Text::_('COM_WENDFOLD_OFFICER_NEW') : Text::_('COM_WENDFOLD_OFFICER_EDIT');

		$this->document->setTitle($title);

		$toolbar = Toolbar::getInstance('toolbar');

		// Visual heading (still supported in Joomla 6)
		ToolbarHelper::title(
			$title,
			'user'
		);

		// Save / Apply buttons
		$toolbar->apply('officer.apply');
		$toolbar->save('officer.save');

		if ($isNew)
		{
			$toolbar->save2new('officer.save2new');
		}

		$toolbar->cancel('officer.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}
}
