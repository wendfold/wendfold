<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Activitylog;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Form for logging a personal activity.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $profile;

	/**
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $metrics = [];

	/**
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $motorcycles = [];

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	public $today = '';

	/**
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $recentEntries = [];

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);
		$this->document->getWebAssetManager()->useStyle('com_wendfold.site');

		if (!$user || $user->guest)
		{
			$app->redirect(RouteHelper::login('index.php?option=com_wendfold&view=activitylog'));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\ActivitylogModel $model */
		$model = $this->getModel();
		$this->profile = $model->getProfile();
		$this->today = SiteTime::fromApplication()->today();

		if ($this->profile)
		{
			$this->metrics = $model->getMemberMetrics();
			$this->motorcycles = $model->getMotorcycles((int) $this->profile->id);
			$this->recentEntries = $model->getRecentEntries((int) $this->profile->id);
		}

		$this->document->setTitle(Text::_('COM_WENDFOLD_ACTIVITY_PAGE_TITLE'));
		parent::display($tpl);
	}
}
