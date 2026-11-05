<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Membershiptype;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Edit one membership type.
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
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MembershiptypeModel $model */
		$model = $this->getModel();

		$this->form = $model->getForm();
		$this->item = $model->getItem();
		$this->lookup = ['itemTask' => 'membershiptype'];

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
			Text::_($isNew ? 'COM_WENDFOLD_MEMBERSHIP_TYPE_NEW' : 'COM_WENDFOLD_MEMBERSHIP_TYPE_EDIT'),
			'users'
		);

		$canSave = $isNew
			? $user->authorise('core.create', 'com_wendfold')
			: $user->authorise('core.edit', 'com_wendfold');

		if ($canSave)
		{
			$toolbar->apply('membershiptype.apply');
			$toolbar->save('membershiptype.save');
		}

		if ($user->authorise('core.create', 'com_wendfold'))
		{
			$toolbar->save2new('membershiptype.save2new');
		}

		$toolbar->cancel('membershiptype.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}
}
