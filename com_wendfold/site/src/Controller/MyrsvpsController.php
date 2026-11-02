<?php
/**
 * Wendfold - My RSVPs Controller (Site)
 *
 * Handles:
 *   task=myrsvps.display  (default)
 *   task=myrsvps.cancel   (POST - cancel a single RSVP)
 *
 * Ownership is always resolved server-side from the DB.
 * The POST event_id is treated as untrusted input.
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
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class MyrsvpsController extends BaseController
{
	/**
	 * Default task  -  renders the view.
	 */
	public function display($cachable = false, $urlparams = []): static
	{
		parent::display($cachable, $urlparams);

		return $this;
	}

	/**
	 * Cancel a single RSVP.
	 *
	 * Expects POST fields:
	 *   event_id   - int, untrusted
	 *   {csrf_token} - Joomla form token
	 */
	public function cancel(): void
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();

		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		// Login guard
		$user = $app->getIdentity();

		if (!$user->id)
		{
			$app->redirect(RouteHelper::login(RouteHelper::myRsvpsInternal()));

			return;
		}

		// Resolve profile server-side  -  never trust POST
		/** @var \Wendfold\Component\Wendfold\Site\Model\MyrsvpsModel $model */
		$model   = $this->getModel('Myrsvps', 'Site');
		$profile = $model->getProfileByUserId($user->id);

		$returnUrl = RouteHelper::myRsvps();

		if (!$profile)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MYRSVPS_NO_PROFILE'), 'warning');
			$app->redirect($returnUrl);

			return;
		}

		$eventId = $input->getInt('event_id', 0);

		if ($model->cancelRsvp($eventId, $profile->id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MYRSVPS_CANCEL_SUCCESS'), 'success');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MYRSVPS_CANCEL_ERROR'), 'error');
		}

		$app->redirect($returnUrl);
	}
}
