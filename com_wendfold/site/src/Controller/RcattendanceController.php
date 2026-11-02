<?php
/**
 * Wendfold - Chapter Management System
 * Site RC Attendance Controller
 *
 * Handles all RC Attendance workflow actions, including ride-group setup and
 * rider assignment for larger rides.
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
use Wendfold\Component\Wendfold\Site\Model\RcattendanceModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

class RcattendanceController extends BaseController
{
	/**
	 * Build the redirect URL back to the RC view for a given event.
	 */
	private function rcUrl(int $eventId): string
	{
		return RouteHelper::rcAttendance($eventId);
	}

	/**
	 * Detect an AJAX request from the RC page.
	 */
	private function isAjaxRequest(): bool
	{
		$input = Factory::getApplication()->getInput();

		return $input->getInt('ajax', 0) === 1
			|| strtolower((string) $input->server->getString('HTTP_X_REQUESTED_WITH')) === 'xmlhttprequest'
			|| $input->server->getString('HTTP_X_WENDFOLD_AJAX') === '1';
	}

	/**
	 * Send a JSON response and close the application.
	 */
	private function sendJson(bool $success, string $message = '', array $data = []): void
	{
		$app = Factory::getApplication();
		$payload = array_merge([
			'success' => $success,
			'message' => $message,
		], $data);

		$app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		echo json_encode($payload);
		$app->close();
	}

	/**
	 * Shared authorization check used by all actions.
	 */
	private function authorizeAndGetModel(int $eventId): ?RcattendanceModel
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user->id)
		{
			if ($this->isAjaxRequest())
			{
				$this->sendJson(false, Text::_('COM_WENDFOLD_ERROR_LOGIN_REQUIRED'), [
					'redirect' => RouteHelper::login(RouteHelper::rcAttendanceInternal($eventId)),
				]);
			}

			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_LOGIN_REQUIRED'), 'error');
			$app->redirect(RouteHelper::login(RouteHelper::rcAttendanceInternal($eventId)));

			return null;
		}

		/** @var RcattendanceModel $model */
		$model = $this->getModel('Rcattendance', 'Site');
		$denialKey = $model->attendanceEventDenialKey($eventId);
		$denyKey = $denialKey ?? 'COM_WENDFOLD_RCACCESS_DENIED';

		if ($denialKey !== null || !$model->isAuthorized($eventId, $user->id))
		{
			if ($this->isAjaxRequest())
			{
				$this->sendJson(false, Text::_($denyKey), [
					'redirect' => RouteHelper::events(),
				]);
			}

			$app->enqueueMessage(Text::_($denyKey), 'error');
			$app->redirect(RouteHelper::events());

			return null;
		}

		return $model;
	}

	/**
	 * Require HTTP POST and a valid CSRF token.
	 *
	 * Event assignment is enforced separately by authorizeAndGetModel().
	 * core.* component actions are not applied here: road captains are not
	 * component administrators.
	 *
	 * @return  void
	 */
	private function requirePostToken(): void
	{
		if (!PostGuard::passes())
		{
			$this->handleInvalidToken();
		}
	}

	/**
	 * Handle an invalid POST token or a non-POST request.
	 */
	private function handleInvalidToken(): void
	{
		if ($this->isAjaxRequest())
		{
			$this->sendJson(false, Text::_('JINVALID_TOKEN'));
		}

		jexit(Text::_('JINVALID_TOKEN'));
	}

	/**
	 * Respond consistently for action results.
	 */
	private function respond(int $eventId, bool $success, string $message, string $type = 'success', ?string $redirect = null): void
	{
		$app = Factory::getApplication();
		$redirect = $redirect ?: $this->rcUrl($eventId);

		if ($this->isAjaxRequest())
		{
			$payload = [];

			if ($redirect !== $this->rcUrl($eventId))
			{
				$payload['redirect'] = $redirect;
			}

			$this->sendJson($success, $message, $payload);
		}

		$app->enqueueMessage($message, $type);
		$app->redirect($redirect);
	}

	/**
	 * Add a member to the roster.
	 */
	public function add(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$profileId = $input->getInt('profile_id', 0);

		if (!$eventId || !$profileId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->addMember($eventId, $profileId, $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_RCATTENDANCE_CHECKIN_SUCCESS'));

			return;
		}

		if ($result === 'already')
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_RCATTENDANCE_ALREADY_ON_ROSTER'), 'notice');

			return;
		}

		if ($result === 'waiver_required')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_WAIVER_REQUIRED'), 'warning');

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		// phpcs:ignore Generic.Files.LineLength.TooLong -- keep one respond path for trash and other failures
		$this->respond($eventId, false, Text::_($result === 'trashed' ? 'COM_WENDFOLD_ATTENDANCE_EVENT_TRASHED' : 'COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Remove a member from the roster.
	 */
	public function remove(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$attendanceId = $input->getInt('id', 0);

		if (!$eventId || !$attendanceId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

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
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_RCATTENDANCE_REMOVE_SUCCESS'));

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

        // phpcs:ignore Generic.Files.LineLength.TooLong -- keep one respond path for both outcomes
		$this->respond($eventId, false, Text::_($result === 'invalid_request' ? 'COM_WENDFOLD_ATTENDANCE_NOT_FOUND' : 'COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Update the public ride status.
	 */
	public function setstatus(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$status = $input->getInt('status', 0);

		if (!$eventId || !in_array($status, [1, 4, 5], true))
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->setEventStatus($eventId, $status);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_STATUS_UPDATED'));

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_STATUS_UPDATE_FAILED'), 'error');
	}

	/**
	 * Seed the initial ride groups for an event.
	 */
	public function creategroups(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$groupCount = $input->getInt('group_count', 2);

		if (!$eventId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->createGroups($eventId, $groupCount, $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_CREATED'));

			return;
		}

		if ($result === 'already_exists')
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_ALREADY_EXIST'), 'notice');

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		if ($result === 'not_riding_event')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_RIDE_ONLY'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Append one additional ride group.
	 */
	public function addgroup(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$eventId = $app->getInput()->getInt('event_id', 0);

		if (!$eventId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->addGroup($eventId, $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_GROUP_ADDED'));

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		if ($result === 'not_riding_event')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_RIDE_ONLY'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Remove one ride group and clear its rider assignments.
	 */
	public function removegroup(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$groupId = $input->getInt('group_id', 0);

		if (!$eventId || !$groupId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->removeGroup($eventId, $groupId, $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_GROUP_REMOVED'));

			return;
		}

		if ($result === 'group_required')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUP_REMOVE_LAST_BLOCKED'), 'warning');

			return;
		}

		if ($result === 'invalid_group')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUP_INVALID'), 'warning');

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		if ($result === 'not_riding_event')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_RIDE_ONLY'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Save ride-group definitions.
	 */
	public function savegroups(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$groups = $input->post->get('groups', [], 'array');

		if (!$eventId || empty($groups))
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->saveGroups($eventId, $groups, $app->getIdentity()->id);

		if ($result === true)
		{
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_UPDATED'));

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		if ($result === 'not_riding_event')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_RIDE_ONLY'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Assign or clear a rider's ride group.
	 */
	public function assigngroup(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$input = $app->getInput();
		$eventId = $input->getInt('event_id', 0);
		$profileId = $input->getInt('profile_id', 0);
		$groupId = $input->getInt('event_group_id', 0);

		if (!$eventId || !$profileId)
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_REQUEST'), 'error');

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->assignGroup($eventId, $profileId, $groupId, $app->getIdentity()->id);

		if ($result === true)
		{
			$message = $groupId > 0
				? Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNMENT_UPDATED')
				: Text::_('COM_WENDFOLD_CHECKIN_GROUP_ASSIGNMENT_CLEARED');
			$this->respond($eventId, true, $message);

			return;
		}

		if ($result === 'event_closed')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');

			return;
		}

		if ($result === 'not_riding_event')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUPS_RIDE_ONLY'), 'warning');

			return;
		}

		if ($result === 'invalid_group')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUP_INVALID'), 'warning');

			return;
		}

		if ($result === 'invalid_member')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_CHECKIN_GROUP_MEMBER_INVALID'), 'warning');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Mark the event Completed and generate ride logs.
	 */
	public function complete(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$eventId = $app->getInput()->getInt('event_id', 0);

		if (!$eventId)
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error', $redirect);

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->completeEvent($eventId, $app->getIdentity()->id);

		if ($result === true)
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_RCATTENDANCE_COMPLETE_SUCCESS'), 'success', $redirect);

			return;
		}

		if ($result === 'event_closed')
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED'), 'warning', $redirect);

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}

	/**
	 * Mark the event Cancelled. No ride logs generated.
	 */
	public function cancel(): void
	{
		$this->requirePostToken();

		$app = Factory::getApplication();
		$eventId = $app->getInput()->getInt('event_id', 0);

		if (!$eventId)
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error', $redirect);

			return;
		}

		$model = $this->authorizeAndGetModel($eventId);

		if (!$model)
		{
			return;
		}

		$result = $model->cancelEvent($eventId);

		if ($result === true)
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, true, Text::_('COM_WENDFOLD_RCATTENDANCE_CANCEL_SUCCESS'), 'warning', $redirect);

			return;
		}

		if ($result === 'event_closed')
		{
			$redirect = RouteHelper::events();
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED'), 'warning', $redirect);

			return;
		}

		if ($result === 'has_checkins')
		{
			$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_CANCEL_HAS_CHECKINS'), 'error');

			return;
		}

		$this->respond($eventId, false, Text::_('COM_WENDFOLD_RCATTENDANCE_OPERATION_FAILED'), 'error');
	}
}
