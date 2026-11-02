<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;
use Wendfold\Component\Wendfold\Site\Helper\PostGuard;

/**
 * Save a member-logged activity.
 *
 * @since  1.0.0
 */
class ActivitylogController extends BaseController
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function save(): void
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();

		if (!PostGuard::passes())
		{
			$app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activitylog', false));

			return;
		}

		$user = $app->getIdentity();

		if (!$user || $user->guest)
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activitylog', false));

			return;
		}

		$allowed = $user->authorise('wendfold.logactivity', 'com_wendfold')
			|| $user->authorise('core.manage', 'com_wendfold')
			|| $user->authorise('core.admin', 'com_wendfold');

		if (!$allowed)
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activitylog', false));

			return;
		}

		/** @var \Wendfold\Component\Wendfold\Site\Model\ActivitylogModel $model */
		$model = $this->getModel('Activitylog', 'Site');
		$profile = $model->getProfile();

		if ($profile === null)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_DASHBOARD_NO_PROFILE'), 'warning');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=dashboard', false));

			return;
		}

		$post = $app->getInput()->post;
		$data = [
			'activity_date'  => $post->getString('activity_date', ''),
			'title'          => $post->getString('title', ''),
			'description'    => $post->getString('description', ''),
			'start_location' => $post->getString('start_location', ''),
			'end_location'   => $post->getString('end_location', ''),
			'motorcycle_id'  => $post->getInt('motorcycle_id', 0),
			'metrics'        => $post->get('metrics', [], 'array'),
		];

		if ($model->storeActivity((int) $profile->id, $data))
		{
			$message = StateValue::get($model->getState(), 'activity.pending')
				? 'COM_WENDFOLD_ACTIVITY_SAVED_PENDING'
				: 'COM_WENDFOLD_ACTIVITY_SAVED';
			$app->enqueueMessage(Text::_($message), 'success');
		}
		else
		{
			$app->enqueueMessage(Text::_((string) $model->getError()), 'error');
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=activitylog', false));
	}
}
