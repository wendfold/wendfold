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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\PostGuard;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Component\Wendfold\Administrator\Service\AwardEngine;

/**
 * Member award grants: recalculate, manual grant, lock, unlock, presented.
 *
 * @since  1.0.0
 */
class MemberawardsController extends BaseController
{
	/**
	 * Recompute awards from the current metric ledger.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function recalculate(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if (!$user || !$user->authorise('core.edit', 'com_wendfold'))
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect('index.php?option=com_wendfold');

			return;
		}

		try
		{
			/** @var DatabaseInterface $db */
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$changed = AwardEngine::fromDatabase($db)->recompute();
			$app->enqueueMessage(
				Text::sprintf('COM_WENDFOLD_MEMBERAWARDS_MSG_RECALCULATED', $changed),
				'success'
			);
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards recalculate failed', $error);
			$app->enqueueMessage(
				Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_RECALCULATE_FAILED'),
				'error'
			);
		}

		$this->setRedirect('index.php?option=com_wendfold&view=memberawards');
	}

	/**
	 * Manually grant a locked award to a member.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function grant(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$input = $app->getInput();

		if (!$user || !$user->authorise('core.edit', 'com_wendfold'))
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect('index.php?option=com_wendfold');

			return;
		}

		$awardId = $input->post->getInt('award_id', 0);
		$profileId = $input->post->getInt('profile_id', 0);
		$periodKey = (string) $input->post->getString('period_key', '');
		$tier = max(1, $input->post->getInt('tier', 1));

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MemberawardsModel $model */
		$model = $this->getModel('Memberawards', 'Administrator');

		if ($model->grantManual($awardId, $profileId, $periodKey, $tier))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MEMBERAWARDS_MSG_GRANTED'), 'success');
		}
		else
		{
			$app->enqueueMessage(
				$model->getError() ?: Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'),
				'error'
			);
		}

		$this->setRedirect('index.php?option=com_wendfold&view=memberawards');
	}

	/**
	 * Lock a grant so recompute will not change it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function lock(): void
	{
		$this->setLockFlag(1);
	}

	/**
	 * Unlock a grant so recompute may change it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function unlock(): void
	{
		$this->setLockFlag(0);
	}

	/**
	 * Record the date an award was presented.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setPresented(): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$input = $app->getInput();

		if (!$user || !$user->authorise('core.edit.state', 'com_wendfold'))
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect('index.php?option=com_wendfold');

			return;
		}

		$id = $input->post->getInt('id', 0);
		$presentedOn = (string) $input->post->getString('presented_on', '');

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MemberawardsModel $model */
		$model = $this->getModel('Memberawards', 'Administrator');

		if ($model->setPresented($id, $presentedOn))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_MEMBERAWARDS_MSG_PRESENTED'), 'success');
		}
		else
		{
			$app->enqueueMessage(
				$model->getError() ?: Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_FAILED'),
				'error'
			);
		}

		$this->setRedirect('index.php?option=com_wendfold&view=memberawards');
	}

	/**
	 * @param   string  $name    Model name.
	 * @param   string  $prefix  Prefix.
	 * @param   array   $config  Config.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Memberawards', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Set is_locked on one grant row.
	 *
	 * @param   integer  $locked  1 lock, 0 unlock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function setLockFlag(int $locked): void
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$input = $app->getInput();

		if (!$user || !$user->authorise('core.edit.state', 'com_wendfold'))
		{
			$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
			$this->setRedirect('index.php?option=com_wendfold');

			return;
		}

		$id = $input->post->getInt('id', 0);

		/** @var \Wendfold\Component\Wendfold\Administrator\Model\MemberawardsModel $model */
		$model = $this->getModel('Memberawards', 'Administrator');

		if ($model->setLocked($id, $locked === 1))
		{
			$app->enqueueMessage(
				Text::_($locked === 1
					? 'COM_WENDFOLD_MEMBERAWARDS_MSG_LOCKED'
					: 'COM_WENDFOLD_MEMBERAWARDS_MSG_UNLOCKED'
				),
				'success'
			);
		}
		else
		{
			$app->enqueueMessage(
				$model->getError() ?: Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_LOCK_FAILED'),
				'error'
			);
		}

		$this->setRedirect('index.php?option=com_wendfold&view=memberawards');
	}
}
