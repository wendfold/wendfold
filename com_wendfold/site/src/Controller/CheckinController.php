<?php
/**
 * Wendfold - Chapter Management System
 * Site QR Check-In Controller
 *
 * Handles the self-service QR code check-in flow:
 *   1. Member scans the event QR code and lands on the check-in page.
 *   2. Member confirms their attendance via the form.
 *   3. This controller validates the submission, then calls CheckinModel::checkIn().
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Controller;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\PostGuard;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * QR Check-In Controller
 *
 * Single-task controller that processes the member self check-in confirmation form.
 * Check-in method 1 = self check-in via QR scan.
 *
 * @since  1.0.0
 */
class CheckinController extends BaseController
{
	/**
	 * Handle the QR scan check-in POST form submission.
	 *
	 * Validates CSRF token, verifies the user is logged in and that all
	 * required fields (token, event_id) are present, then delegates the
	 * actual check-in logic to CheckinModel::checkIn().
	 *
	 * Possible outcomes:
	 *   - true    : checked in successfully
	 *   - 'already' : member was already checked in (shown as notice)
	 *   - string  : error message from the model
	 *
	 * Expects POST: event_id, token, plus a valid Joomla CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function confirm(): void
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();

		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$token   = $input->getString('token', '');
		$eventId = $input->getInt('event_id', 0);
		$user    = $app->getIdentity();

		if (!$user->id || !$token || !$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_FAILED_TRY_AGAIN'), 'error');
			$app->redirect(RouteHelper::checkin($token));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\CheckinModel $model */
		$model  = $this->getModel('Checkin', 'Site');
		$result = $model->checkIn($eventId, $user->id, $token, 1); // method 1 = self check-in via QR

		if ($result === true)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_SUCCESS'), 'success');
		}
		elseif ($result === 'already')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ALREADY'), 'notice');
		}
		else
		{
			switch ((string) $result)
			{
				case 'invalid_token':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_INVALID_TOKEN'), 'error');
					break;
				case 'event_trashed':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_TRASHED'), 'error');
					break;
				case 'no_profile':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_NO_PROFILE'), 'error');
					break;
				case 'waiver_required':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_WAIVER_REQUIRED'), 'warning');
					break;
				case 'event_closed':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_EVENT_CLOSED'), 'warning');
					break;
				case 'event_not_today':
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_NOT_TODAY'), 'warning');
					break;
				default:
					$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ERROR_DB'), 'error');
					break;
			}
		}

		$app->redirect(RouteHelper::checkin($token));
	}
}
