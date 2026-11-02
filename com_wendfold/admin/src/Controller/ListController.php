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

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Utilities\ArrayHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\BulkListMessages;
use Wendfold\Component\Wendfold\Administrator\Helper\PublishRowState;

/**
 * Admin list controller with honest publish and delete result messages.
 *
 * Runs each selected primary key individually so a mid-batch database failure
 * cannot over-count successes, and reports only translated plurals.
 *
 * @since  1.0.0
 */
class ListController extends AdminController
{
	/**
	 * Check in selected items. Requires POST with a CSRF token.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function checkin()
	{
		$this->checkToken();

		return parent::checkin();
	}

	/**
	 * Publish, unpublish, archive, or trash selected items.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function publish()
	{
		$this->checkToken();

		$cid = array_values(array_filter((array) $this->app->getInput()->get('cid', [], 'int')));
		$data = ['publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2, 'report' => -3];
		$task = $this->getTask();
		$value = ArrayHelper::getValue($data, $task, 0, 'int');

		if ($cid === [])
		{
			$this->getLogger()->warning(
				Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				['category' => 'jerror']
			);
			$this->setRedirect($this->getRedirectUrlToList());

			return;
		}

		$model = $this->getModel();

		if (!$model instanceof AdminModel)
		{
			$this->setMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
			$this->setRedirect($this->getRedirectUrlToList());

			return;
		}

		$requested = \count($cid);
		$succeeded = 0;
		$failed = 0;
		$userId = $this->currentUserId();

		foreach ($cid as $pk)
		{
			$pk = (int) $pk;

			try
			{
				$verdict = $this->classifyPublishRow($model, $pk, $value, $userId);

				if ($verdict === PublishRowState::UNCHANGED)
				{
					continue;
				}

				if ($verdict === PublishRowState::CHECKED_OUT
					|| $verdict === PublishRowState::MISSING)
				{
					$failed++;

					continue;
				}

				$one = [$pk];
				$ok = (bool) $model->publish($one, $value);

				if ($ok)
				{
					$after = $this->classifyPublishRow($model, $pk, $value, $userId);

					if ($after === PublishRowState::UNCHANGED && $one !== [])
					{
						$succeeded++;
					}
					elseif ($after !== PublishRowState::UNCHANGED)
					{
						/*
						 * Publish returned true but the row is missing, checked out,
						 * still needing a change, or was ACL-pruned without reaching
						 * the target. Count as failed; only a verified at-target row
						 * with a non-empty key list counts as changed.
						 */
						$failed++;
					}
				}
				else
				{
					$failed++;
					$this->logModelErrors($model);
				}
			}
			catch (\Throwable $error)
			{
				$failed++;
				Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
			}
		}

		$this->enqueueBulkMessages(
			BulkListMessages::forPublish(
				(string) $this->text_prefix, // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				$value,
				$requested,
				$succeeded,
				$failed
			)
		);

		$this->setRedirect($this->getRedirectUrlToList());
	}

	/**
	 * Delete selected items one primary key at a time.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function delete()
	{
		$this->checkToken();

		$cid = array_values(array_filter((array) $this->app->getInput()->get('cid', [], 'int')));

		if ($cid === [])
		{
			$this->getLogger()->warning(
				Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				['category' => 'jerror']
			);
			$this->setRedirect($this->getRedirectUrlToList());

			return;
		}

		$model = $this->getModel();

		if (!$model instanceof AdminModel)
		{
			$this->setMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
			$this->setRedirect($this->getRedirectUrlToList());

			return;
		}

		$requested = \count($cid);
		$succeeded = 0;
		$failed = 0;
		$deleted = [];

		foreach ($cid as $pk)
		{
			$one = [$pk];

			try
			{
				$ok = (bool) $model->delete($one);

				if ($ok)
				{
					$succeeded++;
					$deleted[] = $pk;
				}
				else
				{
					$failed++;
					$this->logModelErrors($model);
				}
			}
			catch (\Throwable $error)
			{
				$failed++;
				Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
			}
		}

		$this->enqueueBulkMessages(
			BulkListMessages::forDelete(
				(string) $this->text_prefix, // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				$requested,
				$succeeded,
				$failed
			)
		);

		$this->postDeleteHook($model, $deleted);
		$this->setRedirect($this->getRedirectUrlToList());
	}

	/**
	 * Read and classify one row before a publish attempt.
	 *
	 * @param   AdminModel  $model   Model for getTable().
	 * @param   integer     $pk      Primary key.
	 * @param   integer     $value   Target published state.
	 * @param   integer     $userId  Current user id.
	 *
	 * @return  string  PublishRowState constant.
	 *
	 * @since   1.0.0
	 */
	private function classifyPublishRow(AdminModel $model, int $pk, int $value, int $userId): string
	{
		try
		{
			$table = $model->getTable();
		}
		catch (\Throwable $error)
		{
			return PublishRowState::MISSING;
		}

		return PublishRowState::classifyTable($table, $pk, $value, $userId);
	}

	/**
	 * Current user id, or 0 when anonymous.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function currentUserId(): int
	{
		$identity = $this->app->getIdentity();

		if ($identity === null)
		{
			return 0;
		}

		return (int) $identity->id;
	}

	/**
	 * Apply BulkListMessages rows to the application message queue.
	 *
	 * @param   list<array{key: string, count: int, type: string}>  $messages  Planned messages.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function enqueueBulkMessages(array $messages): void
	{
		$firstSuccess = true;

		foreach ($messages as $message)
		{
			if ($message['key'] === '')
			{
				continue;
			}

			if ($message['type'] === BulkListMessages::TYPE_NOTICE)
			{
				if ($message['count'] < 1)
				{
					continue;
				}

				$this->app->enqueueMessage(Text::_($message['key']), BulkListMessages::TYPE_NOTICE);

				continue;
			}

			if ($message['count'] < 1)
			{
				continue;
			}

			$text = Text::plural($message['key'], $message['count']);

			if ($message['type'] === BulkListMessages::TYPE_SUCCESS && $firstSuccess)
			{
				$this->setMessage($text);
				$firstSuccess = false;

				continue;
			}

			$this->app->enqueueMessage($text, $message['type']);
		}
	}

	/**
	 * Write model errors to the component log; never enqueue them for users.
	 *
	 * @param   AdminModel  $model  Model that just failed an item.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logModelErrors(AdminModel $model): void
	{
		$logged = false;

		foreach ($model->getErrors() as $error)
		{
			$error = trim((string) $error);

			if ($error === '')
			{
				continue;
			}

			Log::add($error, Log::ERROR, 'com_wendfold');
			$logged = true;
		}

		if (!$logged)
		{
			$single = trim((string) $model->getError());

			if ($single !== '')
			{
				Log::add($single, Log::ERROR, 'com_wendfold');
			}
		}
	}
}
