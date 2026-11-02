<?php
/**
 * Wendfold - Site Meeting Attendance View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Meetingattendance;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
	public $event = null;

	public $roster = [];

	public $memberPools = [];

	public $attendanceCount = 0;

	public $expectedCount = 0;

	public $eventOpen = false;

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);

		if (!$user->id)
		{
			$app->enqueueMessage(Text::_('JGLOBAL_YOU_MUST_LOGIN_FIRST'), 'warning');
			$app->redirect(RouteHelper::login(RouteHelper::meetingAttendanceInternal($eventId)));

			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\MeetingattendanceModel $model */
		$model = $this->getModel();
		$denialKey = $model->attendanceEventDenialKey($eventId);
		$event = $denialKey === null ? $model->getEvent($eventId) : null;

		if ($denialKey !== null || !$event)
		{
			$app->enqueueMessage(Text::_($denialKey ?? 'COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		if (!$model->isAuthorized($eventId, (int) $user->id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MEETING_ACCESS_DENIED'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		$this->event = $event;
		$this->roster = $model->getRoster($eventId);
		$this->memberPools = $model->getMemberPools($eventId);
		$this->attendanceCount = $model->getAttendanceCount($eventId);
		$this->expectedCount = $model->getExpectedCount($eventId);
		$this->eventOpen = $model->isEventOpen($eventId);

		$this->document->setTitle(Text::sprintf('COM_WENDFOLD_MEETINGATTENDANCE_TITLE', (string) $event->title));
		$this->document->getWebAssetManager()->useStyle('com_wendfold.site')->useScript('com_wendfold.confirm');

		parent::display($tpl);
	}
}
