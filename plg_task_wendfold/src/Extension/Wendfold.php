<?php

/**
 * @package     Wendfold
 * @subpackage  plg_task_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Plugin\Task\Wendfold\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Component\Wendfold\Administrator\Service\AwardEngine;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderService;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Wendfold Task Scheduler Plugin
 *
 * Registers and executes the following scheduled tasks:
 *
 *   wendfold.lapsed_cleanup   - Unpublishes members whose renewal_date has passed
 *   wendfold.reminder_emails  - Sends catch-up renewal reminder emails
 *   wendfold.event_reminders  - Sends day-of event reminder emails to RSVPd members
 *   wendfold.award_recompute  - Recomputes configurable awards for all members
 *
 * All task results are logged to #__wendfold_sync_log.
 *
 * These routines do not check the current user's ACL. Joomla's lazy
 * scheduler runs a due task during an ordinary page view, so a logged-in
 * user without wendfold.emailblast must not abort the send. Who may run
 * or configure a task is com_scheduler's own permission. Manual web mail
 * stays on the controllers (POST, CSRF token, wendfold.emailblast).
 *
 * @since  1.0.0
 */
class Wendfold extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;
	use TaskPluginTrait;

	/**
	 * @inheritDoc
	 */
	protected const TASKS_MAP = [
		'wendfold.lapsed_cleanup'  => [
			'langConstPrefix' => 'PLG_TASK_WENDFOLD_LAPSED_CLEANUP',
			'method'          => 'runLapsedCleanup',
			'form'            => 'lapsed_cleanup',
		],
		'wendfold.reminder_emails' => [
			'langConstPrefix' => 'PLG_TASK_WENDFOLD_REMINDER_EMAILS',
			'method'          => 'runRenewalReminders',
			'form'            => 'reminder_emails',
		],
		'wendfold.event_reminders' => [
			'langConstPrefix' => 'PLG_TASK_WENDFOLD_EVENT_REMINDERS',
			'method'          => 'runEventReminders',
			'form'            => 'event_reminders',
		],
		'wendfold.award_recompute' => [
			'langConstPrefix' => 'PLG_TASK_WENDFOLD_AWARD_RECOMPUTE',
			'method'          => 'runAwardRecompute',
			'form'            => 'award_recompute',
		],
	];

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns the events this plugin subscribes to.
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			// Advertise task routines based on TASKS_MAP
			'onTaskOptionsList'    => 'advertiseRoutines',
			// Execute a scheduled task routine
			'onExecuteTask'        => 'standardRoutineHandler',
			// Attach the per-task parameter forms defined in /forms
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	// =========================================================================
	// TASK 1: Lapsed Member Cleanup
	// =========================================================================

	/**
	 * Unpublish members whose renewal_date is in the past.
	 *
	 * @param   ExecuteTaskEvent  $event  The scheduled task event
	 *
	 * @return  int
	 * @since   1.0.0
	 */
	public function runLapsedCleanup(ExecuteTaskEvent $event): int
	{
		$db    = $this->getDatabase();
		$taskParams = $event->getArgument('params');
		$graceDays  = (int) $this->getTaskParam($taskParams, 'lapsed_grace_days', 30);
		$cutoff    = SiteTime::fromApplication()->shiftDays(-$graceDays);

		// Find profiles that are still published but have a past renewal_date
		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('published') . ' = 1')
			->where($db->quoteName('renewal_date') . ' IS NOT NULL')
			->where($db->quoteName('renewal_date') . ' < :cutoff')
			->bind(':cutoff', $cutoff);

		$db->setQuery($query);
		$lapsedIds = $db->loadColumn();

		$updated = 0;

		if (!empty($lapsedIds))
		{
			$safeIds = implode(',', array_map('intval', $lapsedIds));

			$updateQuery = $db->createQuery()
				->update($db->quoteName('#__wendfold_profiles'))
				->set($db->quoteName('published') . ' = 0')
				->where($db->quoteName('id') . ' IN (' . $safeIds . ')');

			$db->setQuery($updateQuery)->execute();
			$updated = (int) $db->getAffectedRows();
		}

		$this->writeLog(
			2,
			count($lapsedIds),
			$updated,
			0,
			count($lapsedIds) - $updated,
			0,
			'lapsed_cleanup'
		);

		return Status::OK;
	}

	/**
	 * Send catch-up renewal reminder emails through ReminderService.
	 *
	 * @param   ExecuteTaskEvent  $event  The scheduled task event
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function runRenewalReminders(ExecuteTaskEvent $event): int
	{
		WendfoldLog::ensureCategory();

		$app        = Factory::getApplication();
		$fromEmail  = (string) $app->get('mailfrom');
		$taskParams = $event->getArgument('params');
		$fromName   = (string) $this->getTaskParam(
			$taskParams,
			'reminder_from_name',
			(string) $app->get('fromname', 'Wendfold')
		);
		$params  = ComponentHelper::getParams('com_wendfold');
		$windows = ComponentOptions::reminderWindows(
			$params->get('reminder_days_60', 60),
			$params->get('reminder_days_30', 30)
		);

		$service = ReminderService::fromDatabase(
			$this->getDatabase(),
			SiteTime::fromApplication(),
			$windows,
			$fromEmail,
			$fromName
		);
		$result = $service->sendRenewalReminders();
		$sent   = (int) $result['sent_60'] + (int) $result['sent_30'];
		$skip   = (int) $result['skipped'];
		$errors = (int) $result['failed'];

		$this->writeLog(4, $sent + $skip + $errors, $sent, 0, $skip, $errors, 'reminder_emails');

		return Status::OK;
	}

	/**
	 * Send day-of event reminder emails through ReminderService.
	 *
	 * @param   ExecuteTaskEvent  $event  The scheduled task event
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function runEventReminders(ExecuteTaskEvent $event): int
	{
		WendfoldLog::ensureCategory();

		$app       = Factory::getApplication();
		$fromEmail = (string) $app->get('mailfrom');
		$fromName  = (string) $app->get('fromname', 'Wendfold');
		$params    = ComponentHelper::getParams('com_wendfold');
		$windows   = ComponentOptions::reminderWindows(
			$params->get('reminder_days_60', 60),
			$params->get('reminder_days_30', 30)
		);

		$service = ReminderService::fromDatabase(
			$this->getDatabase(),
			SiteTime::fromApplication(),
			$windows,
			$fromEmail,
			$fromName
		);
		$result = $service->sendEventReminders();
		$sent   = (int) $result['sent'];
		$skip   = (int) $result['skipped'];
		$errors = (int) $result['failed'];

		$this->writeLog(3, $sent + $skip + $errors, $sent, 0, $skip, $errors, 'event_reminders');

		return Status::OK;
	}

	// =========================================================================
	// TASK 4: Award Recompute
	// =========================================================================

	/**
	 * Recompute configurable awards for all members.
	 *
	 * Logs the result to sync_log with sync_type=5.
	 *
	 * @param   ExecuteTaskEvent  $event  The scheduled task event
	 *
	 * @return  int
	 * @since   1.0.0
	 */
	public function runAwardRecompute(ExecuteTaskEvent $event): int
	{
		try
		{
			$changed = AwardEngine::fromDatabase($this->getDatabase())->recompute();

			$this->writeLog(
				5,
				$changed,
				$changed,
				0,
				0,
				0,
				'award_recompute'
			);

			return Status::OK;
		}
		catch (\Throwable $e)
		{
			WendfoldLog::throwable('Scheduled award recompute failed', $e);
			$this->writeLog(5, 0, 0, 0, 0, 1, 'award_recompute_error');

			return Status::KNOCKOUT;
		}
	}

	// =========================================================================
	// SHARED HELPER
	// =========================================================================


	/**
	 * Get a task parameter value.
	 *
	 * Scheduled Task parameters are stored per task instance (not per plugin).
	 * The Scheduler may pass params as a Registry, an array, or a stdClass.
	 *
	 * @param   mixed   $params   The task params container.
	 * @param   string  $key      The param key.
	 * @param   mixed   $default  Default value.
	 *
	 * @return  mixed
	 * @since   1.0.0
	 */
	private function getTaskParam(mixed $params, string $key, mixed $default = null): mixed
	{
		if ($params instanceof \Joomla\Registry\Registry)
		{
			return $params->get($key, $default);
		}

		if (is_array($params))
		{
			return $params[$key] ?? $default;
		}

		if (is_object($params) && isset($params->$key))
		{
			return $params->$key;
		}

		return $default;
	}


	/**
	 * Write an entry to the Wendfold sync log.
	 *
	 * @param   int     $syncType    sync_type value (1-5)
	 * @param   int     $processed   Total records processed
	 * @param   int     $updated     Records successfully updated/sent
	 * @param   int     $added       Records newly added
	 * @param   int     $skipped     Records skipped
	 * @param   int     $errored     Records that errored
	 * @param   string  $filename    Task identifier string (used in filename column)
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function writeLog(
		int    $syncType,
		int    $processed,
		int    $updated,
		int    $added,
		int    $skipped,
		int    $errored = 0,
		string $filename = ''
	): void {
		$db  = $this->getDatabase();
		$now = Factory::getDate()->toSql();

		$query = $db->createQuery()
			->insert($db->quoteName('#__wendfold_sync_log'))
			->columns([
				$db->quoteName('sync_type'),
				$db->quoteName('sync_date'),
				$db->quoteName('performed_by'),
				$db->quoteName('records_processed'),
				$db->quoteName('records_updated'),
				$db->quoteName('records_added'),
				$db->quoteName('records_skipped'),
				$db->quoteName('records_errored'),
				$db->quoteName('filename'),
				$db->quoteName('status'),
			])
			->values(implode(',', [
				$db->quote($syncType),
				$db->quote($now),
				0,
				$db->quote($processed),
				$db->quote($updated),
				$db->quote($added),
				$db->quote($skipped),
				$db->quote($errored),
				$db->quote($filename),
				$db->quote(1),
			]));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// Logging failure should not crash the task
		}
	}
}
