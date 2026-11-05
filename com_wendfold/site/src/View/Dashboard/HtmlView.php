<?php
/**
 * Wendfold - Member Dashboard View
 *
 * Loads all data for the member's personal portal landing page.
 * Redirects guests to the login page.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Dashboard;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
	/** @var object|null */
	protected $profile;

	/** @var object */
	protected $stats;

	/** @var array<int, object> */
	protected $upcomingRsvps;

	/** @var object|null */
	protected $awardSnapshot;

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);

		if (!$user || $user->guest)
		{
			$app->redirect(RouteHelper::login(RouteHelper::dashboardInternal()));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\DashboardModel $model */
		$model = $this->getModel();

		$this->profile = $model->getProfile();
		$this->stats = $model->getStats();
		$this->upcomingRsvps = $model->getUpcomingRsvps();
		$this->awardSnapshot = null;

		if ($this->profile)
		{
			$mvcFactory = $app->bootComponent('com_wendfold')->getMVCFactory();

			/** @var \Wendfold\Component\Wendfold\Site\Model\AwardsModel $awardsModel */
			$awardsModel = $mvcFactory->createModel('Awards', 'Site', ['ignore_request' => true]);
			$this->awardSnapshot = $awardsModel->getDashboardSnapshot();
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'), 'warning');
		}

		parent::display($tpl);
	}
}
