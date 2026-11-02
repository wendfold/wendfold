<?php

/**
 * Wendfold - My Activities HtmlView (Site)
 *
 * Guards: guest -> login redirect; no-profile -> dashboard redirect.
 * Language loaded from JPATH_ADMINISTRATOR to bypass site cache issues.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\View\Myactivities;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Model\MyactivitiesModel;

/**
 * Read-only list of the logged-in member's metric entries.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Profile row for the logged-in member.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $profile = null;

	/**
	 * Metric entry rows for the current page.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $items = [];

	/**
	 * Pagination object.
	 *
	 * @var    \Joomla\CMS\Pagination\Pagination|null
	 * @since  1.0.0
	 */
	public $pagination = null;

	/**
	 * Model state.
	 *
	 * @var    \Joomla\Registry\Registry|null
	 * @since  1.0.0
	 */
	public $state = null;

	/**
	 * Per-metric counted totals.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $totals = [];

	/**
	 * Display the member activity list.
	 *
	 * @param   string|null  $tpl  Template name.
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

		if (!MyactivitiesModel::allowsVisitor($user))
		{
			$app->redirect(RouteHelper::login(RouteHelper::myActivitiesInternal()));

			return;
		}

		/** @var MyactivitiesModel $model */
		$model = $this->getModel();
		$this->profile = $model->getProfileByUserId((int) $user->id);

		if (!$this->profile)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MYACTIVITIES_NO_PROFILE'), 'warning');
			$app->redirect(RouteHelper::dashboard());

			return;
		}

		$this->items = $model->getItems();
		$this->pagination = $model->getPagination();
		$this->state = $model->getState();
		$this->totals = $model->getMetricTotals();

		if (\count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->getDocument()->setTitle(Text::_('COM_WENDFOLD_MYACTIVITIES_PAGE_TITLE'));

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site');

		parent::display($tpl);
	}
}
