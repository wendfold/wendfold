<?php

/**
 * Wendfold - Profile Edit View (Site)
 *
 * Loads profile and motorcycle data for the edit form.
 * Enforces login guard and no-profile guard before rendering.
 *
 * Language note:
 *   Language is loaded from JPATH_ADMINISTRATOR because the component
 *   boots with admin language files (see WendfoldComponent::boot()).
 *   The true flag forces a reload past Joomla's language cache.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Profileedit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Profile edit HTML view.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Member profile row.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	protected $profile;

	/**
	 * Primary motorcycle, when one exists.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	protected $motorcycle;

	/**
	 * All motorcycles for this member.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	protected $motorcycles = [];

	/**
	 * Granted awards for this member.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	protected $earnedAwards = [];

	/**
	 * Display the profile edit form.
	 *
	 * @param   string|null  $tpl  Template name override
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		$app = Factory::getApplication();

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);

		$user = $app->getIdentity();

		if (!$user || $user->guest)
		{
			$app->redirect(RouteHelper::login(RouteHelper::profileEditInternal()));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\ProfileeditModel $model */
		$model = $this->getModel();

		$this->profile = $model->getProfile();

		if (!$this->profile)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'), 'warning');
			$app->redirect(RouteHelper::dashboard());

			return;
		}

		$this->motorcycles = $model->getMotorcycles((int) $this->profile->id);
		$this->motorcycle  = $this->motorcycles[0] ?? null;
		$this->earnedAwards = $model->getEarnedAwards((int) $this->profile->id);
		$this->getDocument()->setTitle(Text::_('COM_WENDFOLD_PROFILEEDIT_TITLE'));

		parent::display($tpl);
	}
}
