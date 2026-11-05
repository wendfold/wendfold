<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * National Sync Controller
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Wendfold\Component\Wendfold\Administrator\Helper\EmailblastHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\PostGuard;

/**
 * Sync Controller
 *
 * @since  1.0.0
 */
class SyncController extends BaseController
{
	/**
	 * Default view
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $default_view = 'sync';

	// =========================================================
	// CSV Upload
	// =========================================================

	/**
	 * Process uploaded CSV file.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upload()
	{
		$this->requirePostToken();
		$this->checkAccess();
		$this->requireCoreAction('core.create');
		$this->requireCoreAction('core.edit');

		$app   = Factory::getApplication();
		$input = $app->getInput();
		$model = $this->getModel('Sync');

		$file    = $input->files->get('csv_file') ?: $input->files->get('sync_csv');
		$dryRun  = $input->getInt('dry_run', 0) === 1;
		$preview = $input->getInt('preview', 0) === 1;

		if ($preview)
		{
			$dryRun = true;
		}

		if (!$file || empty($file['tmp_name']))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_NO_FILE'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

			return;
		}

				// Validate CSV upload (size/extension/MIME)
		$maxBytes = 5 * 1024 * 1024; // 5 MB

		if (!empty($file['size']) && (int) $file['size'] > $maxBytes)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_FILE_TOO_LARGE'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

			return;
		}

		$name = strtolower((string) ($file['name'] ?? ''));

		if ($name === '' || pathinfo($name, PATHINFO_EXTENSION) !== 'csv')
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_INVALID_FILE_TYPE'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

			return;
		}

		if (function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime  = finfo_file($finfo, $file['tmp_name']);
			unset($finfo);

			$allowed = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];

			if ($mime && !in_array($mime, $allowed, true))
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_INVALID_FILE_TYPE'), 'error');
				$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

				return;
			}
		}

		$result = $model->processCsvUpload($file, $dryRun);

		if ($app instanceof \Joomla\CMS\Application\CMSWebApplicationInterface)
		{
			$app->setUserState('com_wendfold.sync.import_preview', $result['preview'] ?? null);
		}

		if (!empty($result['row_errors']) && is_array($result['row_errors']))
		{
			$shown = 0;

			foreach ($result['row_errors'] as $rowError)
			{
				if ($shown >= 12)
				{
					$app->enqueueMessage(
						Text::sprintf('COM_WENDFOLD_SYNC_MORE_ERRORS', count($result['row_errors']) - $shown),
						'error'
					);
					break;
				}

				if (is_string($rowError) && $rowError !== '')
				{
					$app->enqueueMessage($rowError, 'error');
					$shown++;
				}
			}
		}

		if (!empty($result['row_warnings']) && is_array($result['row_warnings']))
		{
			$shown = 0;

			foreach ($result['row_warnings'] as $rowWarning)
			{
				if ($shown >= 12)
				{
					$app->enqueueMessage(
						Text::sprintf('COM_WENDFOLD_SYNC_MORE_WARNINGS', count($result['row_warnings']) - $shown),
						'warning'
					);
					break;
				}

				if (is_string($rowWarning) && $rowWarning !== '')
				{
					$app->enqueueMessage($rowWarning, 'warning');
					$shown++;
				}
			}
		}

		if ($result['success'])
		{
			$msg = Text::sprintf(
				'COM_WENDFOLD_SYNC_SUCCESS',
				$result['processed'],
				$result['updated'],
				$result['added'],
				$result['skipped']
			);

			if ($preview)
			{
				$msg = Text::_('COM_WENDFOLD_SYNC_PREVIEW_PREFIX') . ' ' . $msg;
			}
			elseif ($dryRun)
			{
				$msg = Text::_('COM_WENDFOLD_SYNC_DRY_RUN_PREFIX') . ' ' . $msg;
			}

			if ($result['flagged'] > 0)
			{
				$msg .= ' ' . Text::sprintf('COM_WENDFOLD_SYNC_FLAGGED_COUNT', $result['flagged']);
			}

			$app->enqueueMessage($msg, 'success');

			if (empty($result['preview']) && !empty($result['notes']) && is_array($result['notes']))
			{
				foreach ($result['notes'] as $note)
				{
					if ($note !== '')
					{
						$app->enqueueMessage($note, 'info');
					}
				}
			}
		}
		else
		{
			$app->enqueueMessage($result['error'], 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));
	}

	// =========================================================
	// Renewal Reminders
	// =========================================================

	/**
	 * Send renewal reminder emails.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function sendReminders()
	{
		$this->requirePostToken();
		$this->checkAccess();
		$this->requireEmailBlast();

		$app   = Factory::getApplication();
		$model = $this->getModel('Sync');

		$result = $model->sendRenewalReminders();

		$msg = Text::sprintf(
			'COM_WENDFOLD_SYNC_REMINDERS_SENT',
			$result['sent_60'],
			$result['days_60'],
			$result['sent_30'],
			$result['days_30']
		);

		$app->enqueueMessage($msg, 'info');
		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));
	}

	// =========================================================
	// Lapsed Member Cleanup
	// =========================================================

	/**
	 * Unpublish members whose renewal_date has lapsed.
	 *
	 * Reads grace_days from POST, passes to model, stores results
	 * in the user session so the view can display the summary.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function runLapsedCleanup()
	{
		$this->requirePostToken();
		$this->checkAccess();
		$this->requireCoreAction('core.edit.state');

		$app       = Factory::getApplication();
		$input     = $app->getInput();
		$model     = $this->getModel('Sync');
		$graceDays = $input->getInt('grace_days', 30);

		$result = $model->runLapsedCleanup($graceDays);

		$app->setUserState('com_wendfold.sync.lapsed_result', $result);

		$msg = Text::sprintf('COM_WENDFOLD_SYNC_LAPSED_DONE', $result['unpublished']);
		$app->enqueueMessage($msg, $result['unpublished'] > 0 ? 'warning' : 'info');

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));
	}

	// =========================================================
	// Chapter Transfer Resolution
	// =========================================================

	/**
	 * Remove a flagged member from the roster.
	 *
	 * Sets published = 0 and clears chapter_switch_flagged.
	 * Requires Chapter Officer group or higher.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function removeFromRoster()
	{
		$this->requirePostToken();
		$this->checkAccess();
		$this->requireCoreAction('core.delete');

		$app   = Factory::getApplication();
		$id    = $app->getInput()->getInt('profile_id', 0);
		$model = $this->getModel('Sync');

		if (!$id)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_INVALID_ID'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

			return;
		}

		if ($model->removeFromRoster($id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_REMOVED'), 'success');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_NOT_FLAGGED'), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));
	}

	/**
	 * Keep a flagged member on the roster and clear the flag.
	 *
	 * Clears chapter_switch_flagged, leaves published unchanged.
	 * Requires Chapter Officer group or higher.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function keepMember()
	{
		$this->requirePostToken();
		$this->checkAccess();
		$this->requireCoreAction('core.edit');

		$app   = Factory::getApplication();
		$id    = $app->getInput()->getInt('profile_id', 0);
		$model = $this->getModel('Sync');

		if (!$id)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_INVALID_ID'), 'error');
			$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));

			return;
		}

		if ($model->keepMember($id))
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_KEPT'), 'success');
		}
		else
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_SYNC_TRANSFER_NOT_FLAGGED'), 'error');
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false));
	}

	// =========================================================
	// Private helpers
	// =========================================================

	/**
	 * Verify the current user may manage the component (core.manage)
	 * or is a Super User.
	 *
	 * Adjust the group ID to match your Joomla installation's officer
	 * user group if it differs.
	 *
	 * @return  void  Redirects and exits if access is denied.
	 *
	 * @since   1.0.0
	 */
	private function checkAccess(): void
	{
		$user = Factory::getApplication()->getIdentity();

		// Super Users always pass
		if ($user->authorise('core.admin'))
		{
			return;
		}

		// Check component-level manage permission (assigned to the officer group
		// in the component's access settings)
		if ($user->authorise('core.manage', 'com_wendfold'))
		{
			return;
		}

		$app = Factory::getApplication();
		$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
		$app->redirect(Route::_('index.php?option=com_wendfold&view=sync', false));
		$app->close();
	}

	/**
	 * Reject anything other than POST with a valid CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function requirePostToken(): void
	{
		if (PostGuard::passes())
		{
			return;
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false), Text::_('JINVALID_TOKEN'), 'error');
		$this->redirect();
	}

	/**
	 * Require one core component action in addition to checkAccess().
	 *
	 * @param   string  $action  core.create, core.edit, core.delete, or core.edit.state.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function requireCoreAction(string $action): void
	{
		$user = Factory::getApplication()->getIdentity();

		if ($user && $user->authorise($action, 'com_wendfold'))
		{
			return;
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');
		$this->redirect();
	}

	/**
	 * Require permission to send bulk email.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function requireEmailBlast(): void
	{
		if (EmailblastHelper::canSend())
		{
			return;
		}

		$this->setRedirect(Route::_('index.php?option=com_wendfold&view=sync', false), Text::_('JERROR_ALERTNOAUTHOR'), 'error');
		$this->redirect();
	}
}
