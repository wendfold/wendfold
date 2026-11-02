<?php
/**
 * Wendfold - Email Blast View
 *
 * Passes recipient count for the selected filter to the template
 * so the officer can see how many members will receive the email
 * before hitting Send.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\View\Emailblast;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\EmailblastHelper;
use Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel;

class HtmlView extends BaseHtmlView
{
	/** @var int  Number of recipients for the currently selected filter */
	public int $recipientCount = 0;

	/** @var string  Currently selected filter */
	public string $selectedFilter = 'all';

	/**
	 * Display the email blast compose form.
	 *
	 * @param   string|null  $tpl  Optional template override
	 *
	 * @return  void
	 */
	public function display($tpl = null): void
	{
		$app = Factory::getApplication();

		if (!EmailblastHelper::canSend())
		{
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Force-load admin language file (bypasses Joomla cache)
		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);

		$input                = $app->getInput();
		$this->selectedFilter = $input->getCmd('audience', 'all');

		if (!in_array($this->selectedFilter, EmailblastModel::AUDIENCES, true))
		{
			$this->selectedFilter = 'all';
		}

		// Get recipient count for the preview badge
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\EmailblastModel $model */
		$model                = $this->getModel();
		$this->recipientCount = count($model->getRecipients($this->selectedFilter));

		// Toolbar
		ToolbarHelper::title(Text::_('COM_WENDFOLD_EMAILBLAST_TITLE'), 'mail');
		ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_wendfold&view=members');

		parent::display($tpl);
	}
}
