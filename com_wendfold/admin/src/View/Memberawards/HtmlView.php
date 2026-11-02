<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Memberawards;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;

/**
 * Member award grants list.
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
	 * Published award options for the grant form (id => title).
	 *
	 * @var    array<int, string>
	 * @since  1.0.0
	 */
	public $awardOptions = [];

	/**
	 * Member profile options for the grant form (profile_id => name).
	 *
	 * @var    array<int, string>
	 * @since  1.0.0
	 */
	public $memberOptions = [];

	/**
	 * Whether the current user may grant awards.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $canEdit = false;

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MemberawardsModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->awardOptions = $model->getAwardOptions();
		$this->memberOptions = $model->getMemberOptions();
		$this->canEdit = (bool) Factory::getApplication()->getIdentity()->authorise('core.edit', 'com_wendfold');

		if (\count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		$this->getDocument()->getWebAssetManager()->useScript('com_wendfold.confirm');
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
		ToolbarHelper::title(Text::_('COM_WENDFOLD_MEMBERAWARDS_TITLE'), 'trophy');
		ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_wendfold&view=stats');

		if ($user->authorise('core.edit', 'com_wendfold'))
		{
			$toolbar->standardButton('recalculate')
				->text('COM_WENDFOLD_MEMBERAWARDS_BTN_RECALCULATE')
				->task('memberawards.recalculate')
				->icon('icon-refresh')
				->listCheck(false)
				->attributes(
					[
						'data-confirm' => OutputEscape::html(
							Text::_('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_RECALCULATE')
						),
					]
				);
		}
	}
}
