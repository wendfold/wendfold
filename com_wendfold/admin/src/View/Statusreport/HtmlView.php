<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * Wendfold - Chapter Status Report HtmlView
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Statusreport;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;

class HtmlView extends BaseHtmlView
{
	/** @var array */
	protected $members = [];

	/**
	 * Display the view.
	 *
	 * @param   string|null  $tpl  Template override
	 * @return  void
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\StatusreportModel $model */
		$model = $this->getModel();

		$this->members = $model->getExpiringMembers();

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Build the admin toolbar.
	 */
	protected function addToolbar(): void
	{
		ToolbarHelper::title(
			Text::_('COM_WENDFOLD_STATUSREPORT_TITLE'),
			'icon-list-2'
		);

		ToolbarHelper::back(
			'COM_WENDFOLD_BACK',
			'index.php?option=com_wendfold&view=sync'
		);
	}
}
