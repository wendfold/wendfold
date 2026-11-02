<?php
/**
 * Wendfold - Chapter Management System
 * Site Meeting Attendance Controller
 *
 * Handles manual meeting roll-call actions without creating a separate
 * meetings subsystem.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Controller;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\PostGuard;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Model\MeetingattendanceModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

class MeetingattendanceController extends BaseController
{
	private function meetingUrl(int $eventId): string
	{
		return RouteHelper::meetingAttendance($eventId);
	}

	private function authorizeAndGetModel(int $eventId): ?MeetingattendanceModel
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->id)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_LOGIN_REQUIRED'), 'error');
			$app->redirect(RouteHelper::login(RouteHelper::meetingAttendanceInternal($eventId)));

			return null;
		}

		/** @var MeetingattendanceModel $model */
		$model = $this->getModel('Meetingattendance', 'Site');
		$denialKey = $model->attendanceEventDenialKey($eventId);

		if ($denialKey !== null || !$model->isAuthorized($eventId, (int) $user->id))
		{
			$app->enqueueMessage(Text::_($denialKey ?? 'COM_WENDFOLD_MEETING_ACCESS_DENIED'), 'error');
			$app->redirect(RouteHelper::events());

			return null;
		}

		return $model;
	}

	private function respond(int $eventId, string $message, string $type = 'success'): void
	{
		$app = Factory::getApplication();
		$app->enqueueMessage($message, $type);
		$app->redirect($this->meetingUrl($eventId));
	}

	public function add(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$profileId = $input->getInt('profile_id', 0);

		if (!$eventId || !$profileId)
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->addMember($eventId, $profileId, (int) $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_ADD_SUCCESS'));

			return;
		}

		if ($result === 'already')
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_ALREADY_PRESENT'), 'notice');

			return;
		}

		if ($result === 'closed' || $result === 'trashed')
		{
			// phpcs:ignore Generic.Files.LineLength.TooLong -- keep one respond path for closed and trash
			$this->respond($eventId, Text::_($result === 'trashed' ? 'COM_WENDFOLD_ATTENDANCE_EVENT_TRASHED' : 'COM_WENDFOLD_MEETING_ATTENDANCE_CLOSED'), $result === 'trashed' ? 'error' : 'warning');

			return;
		}

		$this->respond($eventId, Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_SAVE_ERROR'), 'error');
	}

	public function remove(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$attendanceId = $input->getInt('attendance_id', 0);

		if (!$eventId || !$attendanceId)
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->removeMember($attendanceId, $eventId);

		if ($result === true)
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_REMOVE_SUCCESS'), 'notice');

			return;
		}

		if ($result === 'closed')
		{
			$this->respond($eventId, Text::_('COM_WENDFOLD_MEETING_ATTENDANCE_CLOSED'), 'warning');

			return;
		}

        // phpcs:ignore Generic.Files.LineLength.TooLong -- keep one respond path for both outcomes
		$this->respond($eventId, Text::_($result === 'not_found' ? 'COM_WENDFOLD_ATTENDANCE_NOT_FOUND' : 'COM_WENDFOLD_MEETING_ATTENDANCE_SAVE_ERROR'), 'error');
	}
}
