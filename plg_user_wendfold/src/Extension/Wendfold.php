<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  User.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Automatically creates (or soft-deletes) a #__wendfold_profiles row whenever
 * a Joomla user account is created or deleted.
 *
 * Events:
 *   onUserAfterSave   - Creates a new profile row on new user registration.
 *                       Re-publishes a soft-deleted profile if the user_id already exists.
 *   onUserAfterDelete - Sets published=0 on the linked profile (soft delete).
 *                       Preserves all historical data; does NOT delete the row.
 *
 * Plugin params:
 *   auto_create_profile  (checkbox, default 1) - Master on/off switch.
 *   default_published    (list 0|1, default 0) - Published state for new profiles.
 *   notify_on_create     (checkbox, default 0) - Email site admin on creation.
 *
 * sync_log sync_type=6 is used for all events fired by this plugin.
 */

namespace Wendfold\Plugin\User\Wendfold\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\MailerErrorRedactor;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Plugin\User\Wendfold\Helper\AdminNotifyMail;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * User-Profile Auto-Link Plugin for Wendfold.
 *
 * @since  1.0.0
 */
class Wendfold extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * sync_type value used for all sync_log rows written by this plugin.
	 *
	 * 1=CSV import, 2=lapsed/transfer, 3=event reminder,
	 * 4=renewal reminder, 5=award recompute, 6=user plugin
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private const SYNC_TYPE = 6;

	// -----------------------------------------------------------------------
	// SubscriberInterface
	// -----------------------------------------------------------------------

	/**
	 * Returns an array of events this plugin subscribes to.
	 *
	 * @return array<string, string>
	 *
	 * @since  1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onUserAfterSave'   => 'onUserAfterSave',
			'onUserAfterDelete' => 'onUserAfterDelete',
		];
	}

	// -----------------------------------------------------------------------
	// Event handlers
	// -----------------------------------------------------------------------

	/**
	 * Fires after a Joomla user record has been saved.
	 *
	 * Creates a new #__wendfold_profiles row when $isNew is true and
	 * auto_create_profile param is enabled.
	 *
	 * Re-registration: if a soft-deleted profile already exists for the
	 * same user_id, the plugin re-publishes it rather than inserting a duplicate.
	 *
	 * @param   Event  $event  Joomla event. Arguments:
	 *                         0 => array  $user    User data (id, name, username, email)
	 *                         1 => bool   $isNew   True only on first-time creation
	 *                         2 => array  $oldUser Previous user data (empty on new)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onUserAfterSave(Event $event): void
	{
		$values = array_values($event->getArguments());

		$user        = $values[0] ?? [];
		$isNew       = (bool) ($values[1] ?? false);
		$savingResult = $values[2] ?? null;

		// Joomla 5+ concrete events pass a boolean saving result as the 3rd argument.
		// Older / legacy dispatchers may pass the $oldUser array there instead.
		if (is_bool($savingResult) && $savingResult === false)
		{
			return;
		}

		// Only act on brand-new Joomla user accounts.
		if (!$isNew)
		{
			return;
		}

		// Master switch.
		if (!(int) $this->params->get('auto_create_profile', 1))
		{
			return;
		}

		$userId = $this->getUserId($user);

		if ($userId <= 0)
		{
			return;
		}

		try
		{
			$db = $this->getDatabase();

			// ------------------------------------------------------------------
			// Check whether a profile row already exists for this user_id.
			// Handles re-registration: same user_id comes back after deletion.
			// ------------------------------------------------------------------
			$query = $db->createQuery()
				->select($db->quoteName(['id', 'published']))
				->from($db->quoteName('#__wendfold_profiles'))
				->where($db->quoteName('user_id') . ' = ' . (int) $userId);

			$existing = $db->setQuery($query)->loadObject();

			if ($existing)
			{
				// Profile exists - re-publish if soft-deleted.
				if ((int) $existing->published === 0)
				{
					$updateQuery = $db->createQuery()
						->update($db->quoteName('#__wendfold_profiles'))
						->set($db->quoteName('published') . ' = 1')
						->where($db->quoteName('id') . ' = ' . (int) $existing->id);

					$db->setQuery($updateQuery)->execute();

					$this->writeLog(
						$db,
						$userId,
						'Re-published existing profile id=' . (int) $existing->id
							. ' for user_id=' . (int) $userId . ' (re-registration)',
						1,
						0,
						0
					);
				}

				return;
			}

			// ------------------------------------------------------------------
			// No profile exists - insert a fresh minimal row.
			// All organization-specific fields left at DB defaults for admin to fill in.
			// ------------------------------------------------------------------
			$defaultPublished = (int) $this->params->get('default_published', 0);
			$now = Factory::getDate()->toSql();
			$componentParams = ComponentHelper::getParams('com_wendfold');

			$object = (object) [
				'user_id'        => (int) $userId,
				'published'      => (int) $defaultPublished,
				'created'        => $now,
				'modified'       => $now,
				'waiver_version' => ComponentOptions::waiverVersion($componentParams->get('waiver_version', '')),
			];

			$db->insertObject('#__wendfold_profiles', $object);

			$newId = (int) $db->insertid();

			$this->writeLog(
				$db,
				$userId,
				'Auto-created profile id=' . (int) $newId
					. ' for user_id=' . (int) $userId
					. ' (published=' . (int) $defaultPublished . ')',
				0,
				1,
				0
			);

			// Optional admin notification email.
			if ((int) $this->params->get('notify_on_create', 0))
			{
				$this->notifyAdmin($user, $newId);
			}
		}
		catch (\Exception $e)
		{
			WendfoldLog::add(
				'plg_user_wendfold onUserAfterSave error: ' . $e->getMessage(),
				Log::ERROR
			);
		}
	}

	/**
	 * Fires after a Joomla user record has been deleted.

	 *
	 * Soft-deletes the linked profile row by setting published=0.
	 * The row is never removed; all historical data is preserved.
	 *
	 * @param   Event  $event  Joomla event. Arguments:
	 *                         0 => array  $user    User data (id, name, username, email)
	 *                         1 => bool   $success Whether the Joomla delete succeeded
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onUserAfterDelete(Event $event): void
	{
		$values = array_values($event->getArguments());

		$user          = $values[0] ?? [];
		$deletingResult = $values[1] ?? true;

		// Joomla 5+ concrete events pass a boolean deleting result as the 2nd argument.
		// Treat non-boolean values as success for backwards compatibility.
		if (is_bool($deletingResult) && $deletingResult === false)
		{
			return;
		}

		$userId = $this->getUserId($user);

		if ($userId <= 0)
		{
			return;
		}

		try
		{
			$db = $this->getDatabase();

			// Find linked profile id.
			$query = $db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_profiles'))
				->where($db->quoteName('user_id') . ' = ' . (int) $userId);

			$profileId = (int) $db->setQuery($query)->loadResult();

			if ($profileId <= 0)
			{
				return;
			}

			// Soft delete: published = 0
			$updateQuery = $db->createQuery()
				->update($db->quoteName('#__wendfold_profiles'))
				->set($db->quoteName('published') . ' = 0')
				->where($db->quoteName('id') . ' = ' . (int) $profileId);

			$db->setQuery($updateQuery)->execute();

			$this->writeLog(
				$db,
				$userId,
				'Soft-deleted profile id=' . (int) $profileId
					. ' for deleted user_id=' . (int) $userId,
				1,
				0,
				0
			);
		}
		catch (\Exception $e)
		{
			WendfoldLog::add(
				'plg_user_wendfold onUserAfterDelete error: ' . $e->getMessage(),
				Log::ERROR
			);
		}
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Safely extracts a Joomla user id from either an array or an object.
	 *
	 * @param   mixed  $user  User payload from the event.
	 *
	 * @return  int
	 *
	 * @since   1.0.0
	 */
	private function getUserId(mixed $user): int
	{
		if (is_array($user))
		{
			return (int) ($user['id'] ?? 0);
		}

		if (is_object($user))
		{
			if (isset($user->id))
			{
				return (int) $user->id;
			}

			if (method_exists($user, 'get'))
			{
				return (int) $user->get('id', 0);
			}
		}

		return 0;
	}

	/**
	 * Reads a user field (name, username, etc.) from either an array or an object.
	 *
	 * @param   mixed   $user     User payload from the event.
	 * @param   string  $field    Field name.
	 * @param   string  $default  Default value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function getUserField(mixed $user, string $field, string $default = ''): string
	{
		if (is_array($user))
		{
			return (string) ($user[$field] ?? $default);
		}

		if (is_object($user))
		{
			if (isset($user->{$field}))
			{
				return (string) $user->{$field};
			}

			if (method_exists($user, 'get'))
			{
				return (string) $user->get($field, $default);
			}
		}

		return $default;
	}


	/**
	 * Writes a row to #__wendfold_sync_log with sync_type=6.
	 *
	 * Column is sync_date (NOT created) -- known schema gotcha.
	 *
	 * @param   DatabaseInterface  $db              Database driver.
	 * @param   int                $performedBy     user_id triggering the event.
	 * @param   string             $logDetail       Human-readable description.
	 * @param   int                $recordsUpdated  Count of updated rows.
	 * @param   int                $recordsAdded    Count of inserted rows.
	 * @param   int                $recordsErrored  Count of errored rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function writeLog(
		DatabaseInterface $db,
		int $performedBy,
		string $logDetail,
		int $recordsUpdated = 0,
		int $recordsAdded = 0,
		int $recordsErrored = 0
	): void {
		try
		{
			$processed = $recordsUpdated + $recordsAdded + $recordsErrored;

			$query = $db->createQuery()
				->insert($db->quoteName('#__wendfold_sync_log'))
				->columns($db->quoteName([
					'sync_type',
					'sync_date',
					'performed_by',
					'records_processed',
					'records_updated',
					'records_added',
					'records_skipped',
					'records_errored',
					'filename',
					'status',
					'log_detail',
				]))
				->values(implode(',', [
					self::SYNC_TYPE,
					$db->quote(gmdate('Y-m-d H:i:s')),
					$performedBy,
					$processed,
					$recordsUpdated,
					$recordsAdded,
					0,
					$recordsErrored,
					$db->quote(''),
					1,
					$db->quote($logDetail),
				]));

			$db->setQuery($query)->execute();
		}
		catch (\Exception $e)
		{
			// Log write failures must never bubble up and break the user workflow.
			WendfoldLog::add(
				'plg_user_wendfold writeLog error: ' . $e->getMessage(),
				Log::WARNING
			);
		}
	}

	/**
	 * Sends a notification email to the site administrator when a new
	 * profile is auto-created.
	 *
	 * @param   array  $user       New Joomla user data (id, name, username, email).
	 * @param   int    $profileId  ID of the newly created profile row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function notifyAdmin(mixed $user, int $profileId): void
	{
		try
		{
			$app       = Factory::getApplication();
			$siteName  = $app->get('sitename', 'Wendfold');
			$adminMail = $app->get('mailfrom', '');
			$adminName = $app->get('fromname', 'Wendfold Admin');

			if (empty($adminMail))
			{
				return;
			}

			[$subject, $body] = AdminNotifyMail::build(
				$siteName,
				(int) $profileId,
				$this->getUserField($user, 'name'),
				$this->getUserField($user, 'username'),
				$this->getUserId($user)
			);

			$mailer = clone Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
			$mailer->setSender([$adminMail, $adminName]);
			$mailer->addRecipient($adminMail, $adminName);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->send();
		}
		catch (\Exception $e)
		{
			WendfoldLog::add(
				MailerErrorRedactor::append('plg_user_wendfold notifyAdmin error for profile ' . $profileId, $e->getMessage()),
				Log::WARNING
			);
		}
	}
}
