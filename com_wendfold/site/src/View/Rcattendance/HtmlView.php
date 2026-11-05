<?php
/**
 * Wendfold - Chapter Management System
 * Site RC Attendance HtmlView
 *
 * Loads all data needed by the RC attendance and ride-group workflow.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Rcattendance;

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

	public $memberList = [];

	public $rsvpCount = 0;

	public $userId = 0;

	public $eventOpen = false;

	public $groups = [];

	public $groupRoleOptions = [];

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$input = $app->getInput();
		$user = $app->getIdentity();
		$eventId = $input->getInt('event_id', 0);

		if (!$user->id)
		{
			$app->enqueueMessage(Text::_('JGLOBAL_YOU_MUST_LOGIN_FIRST'), 'warning');
			$app->redirect(RouteHelper::login(RouteHelper::rcAttendanceInternal($eventId)));

			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\RcattendanceModel $model */
		$model = $this->getModel();
		$denialKey = $model->attendanceEventDenialKey($eventId);
		$event = $denialKey === null ? $model->getEvent($eventId) : null;

		if ($denialKey !== null || !$event)
		{
			$app->enqueueMessage(Text::_($denialKey ?? 'COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		if (!$model->isAuthorized($eventId, $user->id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_CHECKIN_ACCESS_DENIED'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		$this->event = $event;
		$this->userId = $user->id;
		$this->roster = $model->getRoster($eventId);
		$this->memberList = $model->getMemberList($eventId);
		$this->rsvpCount = $model->getRsvpCount($eventId);
		$this->eventOpen = in_array((int) ($event->status ?? 0), [1, 4, 5], true);
		$this->groups = !empty($event->is_riding_event) ? $model->getGroups($eventId) : [];
		$this->groupRoleOptions = !empty($event->is_riding_event) ? $model->getGroupRoleOptions() : [
			'road_captains' => [],
			'sweeps'        => [],
		];

		$this->document->setTitle(Text::sprintf('COM_WENDFOLD_RCATTENDANCE_TITLE', (string) $event->title));

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site')
			->useScript('com_wendfold.rcattendance');

		parent::display($tpl);
	}
}
