<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\View\Attendance;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected $items;

	protected $event;

	protected $state;

	/**
	 * Published profiles for the Add Member modal.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	protected $availableMembers = [];

	/**
	 * RSVP count for the selected event.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	protected $rsvpCount = 0;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Administrator\Model\AttendanceModel $model */
		$model = $this->getModel();

		$this->items = $model->getItems();
		$this->event = $model->getEvent();
		$this->state = $model->getState();
		$this->availableMembers = $model->getAvailableMembers();
		$this->rsvpCount = $model->getRsvpCount();

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();
		$this->getDocument()->getWebAssetManager()->useScript('com_wendfold.confirm');
		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$title = $this->event
			? Text::_('COM_WENDFOLD_ATTENDANCE_TITLE') . ': ' . $this->event->title
			: Text::_('COM_WENDFOLD_ATTENDANCE_TITLE');

		ToolbarHelper::title($title, 'users');
		ToolbarHelper::back('COM_WENDFOLD_BACK_TO_EVENTS', 'index.php?option=com_wendfold&view=events');
	}
}
