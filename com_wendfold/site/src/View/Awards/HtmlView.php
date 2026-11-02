<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Awards;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Site awards catalog and earned list.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public $catalog = [];

	/**
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $profile = null;

	/**
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public $earned = [];

	/**
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $isLoggedIn = false;

	/**
	 * @param   string  $tpl  Layout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();

		$this->document->getWebAssetManager()->useStyle('com_wendfold.site');
		$app->getLanguage()->load('com_wendfold', JPATH_ADMINISTRATOR, null, true);

		/** @var \Wendfold\Component\Wendfold\Site\Model\AwardsModel $model */
		$model = $this->getModel();

		$this->catalog = $model->getCatalog();
		$this->isLoggedIn = !$user->guest;

		if ($this->isLoggedIn)
		{
			$this->profile = $model->getMemberProfileByUserId((int) $user->id);

			if ($this->profile)
			{
				$this->earned = $model->getMemberEarnedAwards((int) $this->profile->id);
			}
			else
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_AWARDS_NO_PROFILE'), 'warning');
			}
		}

		$this->getDocument()->setTitle(Text::_('COM_WENDFOLD_AWARDS_PAGE_TITLE'));
		parent::display($tpl);
	}
}
