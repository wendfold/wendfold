<?php
/**
 * Wendfold - Chapter Management System
 * Site Event Controller
 *
 * Handles actions on the single event detail page.
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
use Joomla\CMS\Application\WebApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

class EventController extends BaseController
{
	protected function getDetailViewName(int $eventId): string
	{
		if ($eventId <= 0)
		{
			return 'event';
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel('Event', 'Site', ['ignore_request' => true]);
		$event = $model ? $model->getItem($eventId) : null;

		return RouteHelper::detailViewName($event);
	}

	protected function getReturnUrl(int $eventId, string $encodedReturn = ''): string
	{
		$detailView = $this->getDetailViewName($eventId);
		$redirectUrl = $eventId
			? RouteHelper::view($detailView, ['id' => $eventId])
			: RouteHelper::events();

		if ($encodedReturn !== '')
		{
			$decoded = base64_decode($encodedReturn, true);

			if ($decoded && strpos($decoded, 'index.php') === 0)
			{
				$redirectUrl = Route::_($decoded, false);
			}
		}

		return $redirectUrl;
	}

	protected function requireLogin(string $redirectUrl): bool
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if ($user->id)
		{
			return true;
		}

		$app->enqueueMessage(Text::_('COM_WENDFOLD_RSVP_LOGIN_REQUIRED'), 'warning');
		$app->redirect(RouteHelper::login($redirectUrl));

		return false;
	}

	public function rsvp(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);
		$return = $input->get('return', '', 'BASE64');
		$redirectUrl = $this->getReturnUrl($eventId, $return);

		if (!$this->requireLogin($redirectUrl))
		{
			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		$going = $input->getInt('going', -1);

		if ($going !== 0 && $going !== 1)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
		}
		else
		{
			/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
			$model = $this->getModel('Event', 'Site');
			$event = $model->getItem($eventId);
			$isMeeting = $event && (\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0))); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			$result = $model->toggleRsvp($eventId, $user->id, $going === 1);

			if ($result === 'added')
			{
				$app->enqueueMessage(Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_ADDED' : 'COM_WENDFOLD_RSVP_ADDED'), 'success');
			}
			elseif ($result === 'removed')
			{
				$app->enqueueMessage(Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_REMOVED' : 'COM_WENDFOLD_RSVP_REMOVED'), 'notice');
			}
			elseif ($result === 'full')
			{
				$app->enqueueMessage(Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_FULL' : 'COM_WENDFOLD_RSVP_EVENT_FULL'), 'warning');
			}
			elseif ($result === 'closed')
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_RSVP_EVENT_CLOSED'), 'warning');
			}
			elseif ($result === 'disabled')
			{
				$app->enqueueMessage(Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_ROLLCALL_ONLY' : 'COM_WENDFOLD_EVENT_RSVP_DISABLED'), 'warning');
			}
			else
			{
				$app->enqueueMessage(Text::_($isMeeting ? 'COM_WENDFOLD_MEETING_RSVP_ERROR' : 'COM_WENDFOLD_RSVP_ERROR'), 'error');
			}
		}

		$app->redirect($redirectUrl);
	}

	public function volunteer(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);
		$roleId = $input->getInt('role_id', 0);
		$return = $input->get('return', '', 'BASE64');
		$redirectUrl = $this->getReturnUrl($eventId, $return);

		if (!$this->requireLogin($redirectUrl))
		{
			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel('Event', 'Site');
		$result = $model->toggleVolunteer($eventId, $roleId, $user->id);

		if ($result === 'added')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_ADDED'), 'success');
		}
		elseif ($result === 'waitlisted')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_WAITLISTED'), 'success');
		}
		elseif ($result === 'removed')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REMOVED'), 'notice');
		}
		elseif ($result === 'full')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_FULL'), 'warning');
		}
		elseif ($result === 'closed')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_EVENT_CLOSED'), 'warning');
		}
		elseif ($result === 'ineligible')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_NOT_ELIGIBLE'), 'warning');
		}
		elseif ($result === 'alreadyassigned')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_ALREADY_ASSIGNED'), 'warning');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SAVE_ERROR'), 'error');
		}

		$app->redirect($redirectUrl);
	}

	public function volunteermanage(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);
		$return = $input->get('return', '', 'BASE64');
		$redirectUrl = $this->getReturnUrl($eventId, $return);

		if (!$this->requireLogin($redirectUrl))
		{
			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel('Event', 'Site');
		$action = $input->getCmd('volunteer_action', 'save');

		if ($action === 'remove')
		{
			$assignmentId = $input->getInt('assignment_id', 0);
			$result = $model->removeVolunteerAssignment($eventId, $assignmentId, $user->id);
		}
		else
		{
			$roleId = $input->getInt('role_id', 0);
			$profileId = $input->getInt('profile_id', 0);
			$assignmentStatus = $input->getInt('assignment_status', 2);
			$result = $model->saveVolunteerAssignment($eventId, $roleId, $profileId, $assignmentStatus, $user->id);
		}

		if ($result === 'saved')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_SAVED'), 'success');
		}
		elseif ($result === 'removed')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_REMOVED'), 'notice');
		}
		elseif ($result === 'full')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_FULL'), 'warning');
		}
		elseif ($result === 'ineligible')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_INELIGIBLE'), 'warning');
		}
		elseif ($result === 'forbidden')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_FORBIDDEN'), 'warning');
		}
		elseif ($result === 'notfound')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_NOT_FOUND'), 'warning');
		}
		elseif ($result === 'closed')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_EVENT_CLOSED'), 'warning');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SAVE_ERROR'), 'error');
		}

		$app->redirect($redirectUrl);
	}

	public function tripstatus(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);
		$return = $input->get('return', '', 'BASE64');
		$redirectUrl = $this->getReturnUrl($eventId, $return);

		if (!$this->requireLogin($redirectUrl))
		{
			return;
		}

		$status = $input->getInt('lodging_status', 0);
		$notes = $input->getString('lodging_notes', '');

		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel('Event', 'Site');
		$result = $model->saveTripStatus($eventId, $user->id, $status, $notes);

		if ($result === 'saved')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_SAVED'), 'success');
		}
		elseif ($result === 'notrsvped')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_NOT_RSVPED'), 'warning');
		}
		elseif ($result === 'invalid')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_REQUIRES_OVERNIGHT'), 'warning');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_TRIP_STATUS_SAVE_ERROR'), 'error');
		}

		$app->redirect($redirectUrl);
	}

	/**
	 * Stream an event GPX file when the current user can see that event's scope.
	 *
	 * Read-only GET, so it does not require a CSRF token. The event id is cast
	 * to int. The browser receives a fixed, escaped filename on an attachment
	 * Content-Disposition header. A hidden event, a missing file, and a path
	 * outside the GPX directory all return the same 404. The stored file name
	 * is not sent to the browser.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function gpx(): void
	{
		$app = Factory::getApplication();

		if (!$app instanceof WebApplication)
		{
			return;
		}

		$eventId = (int) $app->getInput()->getInt('id', 0);

		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel('Event', 'Site');
		$path = $model->getAccessibleGpxPath($eventId);

		if ($path === null)
		{
			$app->setHeader('status', '404 Not Found', true);
			$app->sendHeaders();
			$app->close();

			return;
		}

		while (ob_get_level() > 0)
		{
			ob_end_clean();
		}

		$size = filesize($path);
		$filename = htmlspecialchars('route.gpx', ENT_QUOTES, 'UTF-8');

		$app->setHeader('Content-Type', 'application/gpx+xml', true);
		$app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$app->setHeader('Cache-Control', 'private, no-store', true);

		if ($size !== false)
		{
			$app->setHeader('Content-Length', (string) $size, true);
		}

		$app->sendHeaders();
		readfile($path);
		$app->close();
	}
}
