<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

// Database column names are snake_case on injected candidate rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\MailerErrorRedactor;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * One sender path for renewal and event reminders.
 *
 * Used by the scheduled task plugin and SyncModel::sendRenewalReminders().
 * Missing reminder_log (or other required schema) logs an error and skips
 * sending instead of throwing.
 *
 * @since  1.0.0
 */
final class ReminderService
{
	/**
	 * @param   ReminderLogStore         $log              Claim/release store.
	 * @param   ReminderMailer           $mailer           Outbound mail.
	 * @param   SiteTime                 $site             Site-local clock.
	 * @param   array                    $windows          Reminder windows from ComponentOptions.
	 * @param   array|null               $renewalMembers   Injected candidates for tests.
	 * @param   array|null               $eventRecipients  Injected RSVP rows for tests.
	 * @param   DatabaseInterface|null   $db               Live DB when not injecting lists.
	 * @param   boolean|null             $logReady         Injected schema readiness for tests.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private ReminderLogStore $log,
		private ReminderMailer $mailer,
		private SiteTime $site,
		private array $windows,
		private ?array $renewalMembers = null,
		private ?array $eventRecipients = null,
		private ?DatabaseInterface $db = null,
		private ?bool $logReady = null
	)
	{
	}

	/**
	 * Live service wired to the component database and Joomla mailer.
	 *
	 * @param   DatabaseInterface  $db         Database.
	 * @param   SiteTime           $site       Site clock.
	 * @param   array              $windows    ComponentOptions::reminderWindows().
	 * @param   string             $fromEmail  mailfrom.
	 * @param   string             $fromName   From name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromDatabase(
		DatabaseInterface $db,
		SiteTime $site,
		array $windows,
		string $fromEmail,
		string $fromName
	): self
	{
		return new self(
			new SqlReminderLogStore($db),
			new JoomlaReminderMailer($fromEmail, $fromName),
			$site,
			$windows,
			null,
			null,
			$db
		);
	}

	/**
	 * Send catch-up renewal reminders. One mail per member per run.
	 *
	 * @return  array{sent_60: int, sent_30: int, failed: int, skipped: int, days_60: int, days_30: int}
	 *
	 * @since   1.0.0
	 */
	public function sendRenewalReminders(): array
	{
		WendfoldLog::ensureCategory();

		$firstDays  = (int) ($this->windows[0]['days'] ?? 60);
		$secondDays = (int) ($this->windows[1]['days'] ?? 30);
		$result     = [
			'sent_60' => 0,
			'sent_30' => 0,
			'failed'  => 0,
			'skipped' => 0,
			'days_60' => $firstDays,
			'days_30' => $secondDays,
		];

		try
		{
			if (!$this->reminderLogIsReady())
			{
				return $result;
			}

			$this->loadReminderLanguage();

			$today   = $this->site->today();
			$maxDays = max($firstDays, $secondDays);
			$sentAt  = $this->site->now()->toSql();
			$members = $this->renewalMembers ?? $this->loadRenewalCandidates($today, $maxDays);

			foreach ($members as $member)
			{
				$profileId   = (int) ($member->id ?? 0);
				$renewalDate = (string) ($member->renewal_date ?? '');
				$email       = trim((string) ($member->email ?? ''));
				$name        = (string) ($member->name ?? '');

				if ($profileId < 1 || $renewalDate === '' || $email === '')
				{
					$result['skipped']++;

					continue;
				}

				if (RenewalReminderOptOut::isOptedOutFromParams($member->user_params ?? null))
				{
					$result['skipped']++;

					continue;
				}

				$legacySent = [];

				foreach ($this->windows as $window)
				{
					$column = (string) ($window['column'] ?? '');

					if ($column === '')
					{
						continue;
					}

					$legacySent[$column] = $member->{$column} ?? null;
				}

				$logged = ReminderPlanner::loggedKeysWithLegacy(
					$renewalDate,
					$this->windows,
					$this->log->keysFor($profileId, $renewalDate),
					$legacySent
				);
				$plan = ReminderPlanner::renewalPlan($today, $renewalDate, $this->windows, $logged);

				if ($plan === null)
				{
					continue;
				}

				$sendKey = ReminderPlanner::renewalKey($plan['send']);

				if (!$this->log->claim($profileId, $sendKey, $renewalDate, $sentAt))
				{
					$result['skipped']++;

					continue;
				}

				$formatted = DateOnly::format($renewalDate, 'F j, Y');
				$subject   = Text::sprintf('PLG_TASK_WENDFOLD_EMAIL_RENEWAL_SUBJECT', $plan['send']);
				$body      = Text::sprintf(
					'PLG_TASK_WENDFOLD_EMAIL_RENEWAL_BODY',
					$name,
					$formatted !== '' ? $formatted : $renewalDate,
					$plan['send']
				);

				if (!$this->mailer->send($email, $name, $subject, $body))
				{
					$this->log->release($profileId, $sendKey, $renewalDate);
					Log::add(
						MailerErrorRedactor::append(
							'Renewal reminder send failed for profile ' . $profileId,
							$this->mailer->lastError()
						),
						Log::ERROR,
						'com_wendfold'
					);
					$result['failed']++;

					continue;
				}

				foreach ($plan['skip'] as $skipDays)
				{
					$this->log->claim(
						$profileId,
						ReminderPlanner::renewalKey((int) $skipDays),
						$renewalDate,
						$sentAt
					);
				}

				$this->markLegacySentColumns($profileId, $plan['send'], $plan['skip'], $today);

				$resultKey = $this->resultKeyForDays($plan['send']);
				$result[$resultKey]++;
			}
		}
		catch (\Throwable $e)
		{
			Log::add(
				MailerErrorRedactor::append('Renewal reminders aborted', $e->getMessage()),
				Log::ERROR,
				'com_wendfold'
			);
		}

		return $result;
	}

	/**
	 * Send day-of event reminders, deduped via the reminder log.
	 *
	 * @return  array{sent: int, skipped: int, failed: int}
	 *
	 * @since   1.0.0
	 */
	public function sendEventReminders(): array
	{
		WendfoldLog::ensureCategory();

		$result = [
			'sent'    => 0,
			'skipped' => 0,
			'failed'  => 0,
		];

		try
		{
			if (!$this->reminderLogIsReady())
			{
				return $result;
			}

			$this->loadReminderLanguage();

			$today      = $this->site->today();
			$sentAt     = $this->site->now()->toSql();
			$recipients = $this->eventRecipients ?? $this->loadEventRecipients($today);

			foreach ($recipients as $row)
			{
				$profileId = (int) ($row->profile_id ?? 0);
				$eventId   = (int) ($row->event_id ?? 0);
				$email     = trim((string) ($row->email ?? ''));
				$name      = (string) ($row->name ?? '');
				$title     = (string) ($row->title ?? '');
				$eventDate = (string) ($row->event_date ?? $today);
				$response  = (int) ($row->response ?? 0);
				$published = \array_key_exists('published', (array) $row)
					? (int) $row->published
					: 1;

				if ($profileId < 1 || $eventId < 1 || $email === '')
				{
					$result['skipped']++;

					continue;
				}

				if (!ReminderPlanner::eventEligibleForReminder($published))
				{
					$result['skipped']++;

					continue;
				}

				if (!ReminderPlanner::receivesEventReminder($response))
				{
					$result['skipped']++;

					continue;
				}

				$key = ReminderPlanner::eventKey($eventId, 0);

				if (!$this->log->claim($profileId, $key, $eventDate, $sentAt))
				{
					$result['skipped']++;

					continue;
				}

				$ksu = '';
				$clock = DateOnly::time((string) ($row->ksu_time ?? ''), 'g:i A');

				if ($clock !== '')
				{
					$ksu = ' ' . Text::_('COM_WENDFOLD_EMAIL_EVENT_REMINDER_LABEL') . ' ' . $clock . '.';
				}

				$subject = Text::sprintf('PLG_TASK_WENDFOLD_EMAIL_EVENT_SUBJECT', $title);
				$body    = Text::sprintf('PLG_TASK_WENDFOLD_EMAIL_EVENT_BODY', $name, $title, $ksu);

				if (!$this->mailer->send($email, $name, $subject, $body))
				{
					$this->log->release($profileId, $key, $eventDate);
					Log::add(
						MailerErrorRedactor::append(
							'Event reminder send failed for profile ' . $profileId . ' event ' . $eventId,
							$this->mailer->lastError()
						),
						Log::ERROR,
						'com_wendfold'
					);
					$result['failed']++;

					continue;
				}

				$result['sent']++;
			}
		}
		catch (\Throwable $e)
		{
			Log::add(
				MailerErrorRedactor::append('Event reminders aborted', $e->getMessage()),
				Log::ERROR,
				'com_wendfold'
			);
		}

		return $result;
	}

	/**
	 * Load plg_task_wendfold language strings for shared mail templates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadReminderLanguage(): void
	{
		WendfoldLog::ensureCategory();

		try
		{
			$app         = Factory::getApplication();
			$lang        = $app->getLanguage();
			$siteDefault = (string) $app->get('language', 'en-GB');

			$lang->load('plg_task_wendfold', JPATH_ADMINISTRATOR)
				|| $lang->load('plg_task_wendfold', JPATH_ADMINISTRATOR, $siteDefault)
				|| $lang->load('plg_task_wendfold', JPATH_PLUGINS . '/task/wendfold')
				|| $lang->load('plg_task_wendfold', JPATH_PLUGINS . '/task/wendfold', $siteDefault);
			$lang->load('com_wendfold', JPATH_ADMINISTRATOR)
				|| $lang->load('com_wendfold', JPATH_ADMINISTRATOR, $siteDefault);
		}
		catch (\Throwable $e)
		{
			// Text keys fall back to their literal names when language is unavailable.
		}
	}

	/**
	 * True when #__wendfold_reminder_log is present (or injected as ready).
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function reminderLogIsReady(): bool
	{
		if ($this->logReady !== null)
		{
			if (!$this->logReady)
			{
				Log::add(
					'Reminder log is unavailable; reminder emails were not sent.',
					Log::ERROR,
					'com_wendfold'
				);
			}

			return $this->logReady;
		}

		if ($this->db === null)
		{
			// Injected log store in tests without a DB.
			return true;
		}

		try
		{
			$table = $this->db->replacePrefix('#__wendfold_reminder_log');
			$tables = $this->db->getTableList();

			if (!\in_array($table, $tables, true))
			{
				Log::add(
					'Table #__wendfold_reminder_log is missing; reminder emails were not sent.',
					Log::ERROR,
					'com_wendfold'
				);

				return false;
			}
		}
		catch (\Throwable $e)
		{
			Log::add(
				'Could not verify reminder log table: ' . $e->getMessage(),
				Log::ERROR,
				'com_wendfold'
			);

			return false;
		}

		return true;
	}

	/**
	 * Eligible renewal candidates: published, not blocked, not life-exempt.
	 * Opt-out is filtered in PHP from #__users.params.
	 *
	 * @param   string   $today    Site-local Y-m-d.
	 * @param   integer  $maxDays  Outer reminder window.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	private function loadRenewalCandidates(string $today, int $maxDays): array
	{
		if ($this->db === null)
		{
			return [];
		}

		try
		{
			$horizon = $this->site->shiftDays($maxDays);
			$db      = $this->db;
			$query   = $db->createQuery()
				->select([
					$db->quoteName('p.id'),
					$db->quoteName('p.renewal_date'),
					$db->quoteName('p.reminder_60_sent'),
					$db->quoteName('p.reminder_30_sent'),
					$db->quoteName('u.name'),
					$db->quoteName('u.email'),
					$db->quoteName('u.params', 'user_params'),
					]
				)
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->join(
					'INNER',
					$db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
				)
				->join(
					'LEFT',
					$db->quoteName('#__wendfold_membership_types', 'mt')
						. ' ON ' . $db->quoteName('mt.id') . ' = ' . $db->quoteName('p.membership_type_id')
				)
				->where($db->quoteName('p.published') . ' = 1')
				->where($db->quoteName('u.block') . ' = 0')
				->where($db->quoteName('p.renewal_date') . ' IS NOT NULL')
				->where($db->quoteName('p.renewal_date') . ' > :today')
				->where($db->quoteName('p.renewal_date') . ' <= :horizon')
				->where(
					'(' . $db->quoteName('mt.exempt_from_renewal') . ' IS NULL OR '
					. $db->quoteName('mt.exempt_from_renewal') . ' = 0)'
				)
				->bind(':today', $today)
				->bind(':horizon', $horizon)
				->order($db->quoteName('p.renewal_date') . ' ASC');

			$rows = $db->setQuery($query)->loadObjectList();

			return \is_array($rows) ? $rows : [];
		}
		catch (\Throwable $e)
		{
			Log::add(
				'Renewal reminder candidate query failed: ' . $e->getMessage(),
				Log::ERROR,
				'com_wendfold'
			);

			return [];
		}
	}

	/**
	 * RSVP recipients for published events on the site-local day.
	 *
	 * Unpublished and trashed events are excluded (aligned with
	 * MetricService attendanceEntryPublished for live day-of mail).
	 * Archived still counts for credits but is not a reminder target.
	 *
	 * @param   string  $today  Site-local Y-m-d.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	private function loadEventRecipients(string $today): array
	{
		if ($this->db === null)
		{
			return [];
		}

		try
		{
			$db    = $this->db;
			$query = $db->createQuery()
				->select(
					[
						$db->quoteName('e.id', 'event_id'),
						$db->quoteName('e.title'),
						$db->quoteName('e.ksu_time'),
						$db->quoteName('e.event_date'),
						$db->quoteName('e.published'),
						$db->quoteName('p.id', 'profile_id'),
						$db->quoteName('r.response'),
						$db->quoteName('u.name'),
						$db->quoteName('u.email'),
					]
				)
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join(
					'INNER',
					$db->quoteName('#__wendfold_rsvps', 'r')
						. ' ON ' . $db->quoteName('r.event_id') . ' = ' . $db->quoteName('e.id')
				)
				->join(
					'INNER',
					$db->quoteName('#__wendfold_profiles', 'p')
						. ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('r.profile_id')
				)
				->join(
					'INNER',
					$db->quoteName('#__users', 'u')
						. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
				)
				->where($db->quoteName('e.published') . ' = 1')
				->where($db->quoteName('e.status') . ' IN (1,4)')
				->where($db->quoteName('e.event_date') . ' = :today')
				->where($db->quoteName('p.published') . ' = 1')
				->where($db->quoteName('u.block') . ' = 0')
				->where(
					$db->quoteName('r.response') . ' IN ('
					. ReminderPlanner::RSVP_GOING . ',' . ReminderPlanner::RSVP_MAYBE . ')'
				)
				->bind(':today', $today);

			$rows = $db->setQuery($query)->loadObjectList();

			return \is_array($rows) ? $rows : [];
		}
		catch (\Throwable $e)
		{
			Log::add(
				'Event reminder recipient query failed: ' . $e->getMessage(),
				Log::ERROR,
				'com_wendfold'
			);

			return [];
		}
	}

	/**
	 * Map a send offset to the sync result key.
	 *
	 * @param   integer  $days  Offset that was sent.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function resultKeyForDays(int $days): string
	{
		foreach ($this->windows as $window)
		{
			if ((int) $window['days'] === $days)
			{
				return (string) $window['result'];
			}
		}

		return 'sent_60';
	}

	/**
	 * Keep legacy display columns in sync on a successful send.
	 *
	 * @param   integer             $profileId  Profile id.
	 * @param   integer             $sendDays   Offset that was emailed.
	 * @param   array<int, integer> $skipDays   Offsets logged as skipped.
	 * @param   string              $today      Site-local Y-m-d.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function markLegacySentColumns(int $profileId, int $sendDays, array $skipDays, string $today): void
	{
		if ($this->db === null)
		{
			return;
		}

		$marked = array_merge([$sendDays], $skipDays);

		foreach ($this->windows as $window)
		{
			$days   = (int) $window['days'];
			$column = (string) $window['column'];

			if (!\in_array($days, $marked, true))
			{
				continue;
			}

			if (!\in_array($column, ['reminder_60_sent', 'reminder_30_sent'], true))
			{
				continue;
			}

			try
			{
				$query = $this->db->createQuery()
					->update($this->db->quoteName('#__wendfold_profiles'))
					->set($this->db->quoteName($column) . ' = :today')
					->where($this->db->quoteName('id') . ' = :pid')
					->where($this->db->quoteName($column) . ' IS NULL')
					->bind(':today', $today)
					->bind(':pid', $profileId, ParameterType::INTEGER);

				$this->db->setQuery($query)->execute();
			}
			catch (\Throwable $e)
			{
				Log::add(
					'Could not update legacy reminder column ' . $column . ': ' . $e->getMessage(),
					Log::WARNING,
					'com_wendfold'
				);
			}
		}
	}
}
