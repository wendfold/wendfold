<?php
/**
 * Wendfold - Site Volunteer Board View
 *
 * Dedicated organizer-facing volunteer assignment page.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Volunteerboard;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
	public $item = null;

	public $volunteerRoles = [];

	public $volunteerBoard = [];

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
			$app->redirect(RouteHelper::login(RouteHelper::volunteerBoardInternal($eventId)));

			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\VolunteerboardModel $model */
		$model = $this->getModel();
		$item = $model->getItem($eventId);

		if (!$item)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(RouteHelper::events());

			return;
		}

		if (!$model->canManageVolunteerAssignments($eventId, (int) $user->id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_FORBIDDEN'), 'warning');
			$app->redirect(RouteHelper::events());

			return;
		}

		$this->item = $item;
		$this->volunteerRoles = $model->getVolunteerRoles($eventId, (int) $user->id);
		$this->volunteerBoard = $model->getVolunteerBoard($eventId, (int) $user->id);
		$this->eventOpen = in_array((int) ($item->status ?? 0), [1, 4, 5], true);

		$this->document->setTitle(Text::sprintf('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_PAGE_TITLE', (string) $item->title));

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		parent::display($tpl);
	}
}
