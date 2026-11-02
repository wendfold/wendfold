<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Activity list and approval.
 *
 * @since  1.0.0
 */
class ActivitiesController extends ListController
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	/**
	 * @param   string  $name    Model name.
	 * @param   string  $prefix  Prefix.
	 * @param   array   $config  Config.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Activities', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Publish pending metric entries for the checked activities.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function approve(): void
	{
		$this->decide('approve', 'COM_WENDFOLD_ACTIVITIES_APPROVED');
	}

	/**
	 * Return counted entries for the checked activities to pending.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function unapprove(): void
	{
		$this->decide('unapprove', 'COM_WENDFOLD_ACTIVITIES_UNAPPROVED');
	}

	/**
	 * Trash pending entries for the checked activities.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function reject(): void
	{
		$this->decide('reject', 'COM_WENDFOLD_ACTIVITIES_REJECTED');
	}

	/**
	 * Apply one approval decision to the checked activities.
	 *
	 * @param   string  $action   approve, unapprove, or reject.
	 * @param   string  $message  Language key for the success count.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function decide(string $action, string $message): void
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();

		if (strtoupper($app->getInput()->getMethod()) !== 'POST' || !Session::checkToken('post'))
		{
			$app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activities', false));

			return;
		}

		$user = $app->getIdentity();

		if (!$user->authorise('core.manage', 'com_wendfold')
			|| !$user->authorise('core.edit', 'com_wendfold')
		)
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=activities', false));

			return;
		}

		$ids = $app->getInput()->get('cid', [], 'array');
		$ids = array_map('intval', $ids);

		try
		{
			/** @var \Wendfold\Component\Wendfold\Administrator\Model\ActivitiesModel $model */
			$model = $this->getModel();
			$count = match ($action)
			{
				'unapprove' => $model->unapprove($ids),
				'reject' => $model->reject($ids),
				default => $model->approve($ids),
			};

			$app->enqueueMessage(Text::sprintf($message, $count), 'success');
		}
		catch (\Throwable $error)
		{
			Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=activities', false));
	}
}
