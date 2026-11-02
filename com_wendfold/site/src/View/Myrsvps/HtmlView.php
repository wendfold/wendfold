<?php
/**
 * Wendfold - My RSVPs HtmlView (Site)
 *
 * Guards: guest -> login redirect; no-profile -> dashboard redirect.
 * Language loaded from JPATH_ADMINISTRATOR to bypass site cache issues.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Myrsvps;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

class HtmlView extends BaseHtmlView
{
	/** @var object|null  Profile row for the logged-in member */
	public $profile = null;

	/** @var array  Upcoming RSVP rows (event_date >= today) */
	public $upcoming = [];

	/** @var array  Past RSVP rows (event_date < today, last 20) */
	public $past = [];

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);

		if (!$user->id)
		{
			$app->redirect(RouteHelper::login(RouteHelper::myRsvpsInternal()));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\MyrsvpsModel $model */
		$model = $this->getModel();

		$this->profile = $model->getProfileByUserId($user->id);

		if (!$this->profile)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MYRSVPS_NO_PROFILE'), 'warning');
			$app->redirect(RouteHelper::dashboard());

			return;
		}

		$this->upcoming = $model->getUpcoming($this->profile->id);
		$this->past = $model->getPast($this->profile->id);

		$this->getDocument()->setTitle(Text::_('COM_WENDFOLD_MYRSVPS_PAGE_TITLE'));

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site')->useScript('com_wendfold.confirm');

		parent::display($tpl);
	}
}
