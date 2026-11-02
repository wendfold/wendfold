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

// Database column names are snake_case on injected recipient rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\EventCancelTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\EventStatusTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\MailerErrorRedactor;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;

/**
 * Email Yes/Maybe RSVPs when an event becomes Cancelled.
 *
 * Audience matches day-of event reminders (Going and Maybe only) and
 * respects the per-member reminder opt-out. Callers must invoke notify()
 * only after the cancel transaction has committed, and only when the
 * event actually became Cancelled (see becameCancelled() / shouldNotify()).
 * An EventStatusTransition::OK no-op (already Cancelled), a refused cancel
 * (check-ins present), a failed status persist, or a rolled-back failure
 * must not notify.
 *
 * @since  1.0.0
 */
final class EventCancelNoticeService
{
	/**
	 * @param   ReminderMailer          $mailer           Outbound mail (one recipient each).
	 * @param   string                  $siteName         Site name for the mail body.
	 * @param   array|null              $recipients       Injected rows for tests.
	 * @param   DatabaseInterface|null  $db               Live DB when not injecting rows.
	 * @param   string|null             $eventAbsoluteUrl Injected absolute event URL for tests.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private ReminderMailer $mailer,
		private string $siteName,
		private ?array $recipients = null,
		private ?DatabaseInterface $db = null,
		private ?string $eventAbsoluteUrl = null
	)
	{
	}

	/**
	 * Live service wired to the component database and Joomla mailer.
	 *
	 * @param   DatabaseInterface  $db         Database.
	 * @param   string             $fromEmail  mailfrom.
	 * @param   string             $fromName   From name.
	 * @param   string             $siteName   Site name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromDatabase(
		DatabaseInterface $db,
		string $fromEmail,
		string $fromName,
		string $siteName
	): self
	{
		return new self(
			new JoomlaReminderMailer($fromEmail, $fromName),
			$siteName,
			null,
			$db
		);
	}

	/**
	 * True when status moved into Cancelled (was not Cancelled, now is).
	 *
	 * Shared gate for edit-save (previous vs persisted status) and for
	 * dedicated cancel actions that already know a real row change occurred.
	 *
	 * @param   integer  $previousStatus  Status before the cancel attempt.
	 * @param   integer  $currentStatus   Status persisted after the attempt.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function becameCancelled(int $previousStatus, int $currentStatus): bool
	{
		return $previousStatus !== EventCancelTransition::STATUS_CANCELLED
			&& $currentStatus === EventCancelTransition::STATUS_CANCELLED;
	}

	/**
	 * True when a conditional cancel UPDATE changed the row.
	 *
	 * EventStatusTransition::OK includes already-Cancelled no-ops (0 rows).
	 * Affected rows === 1 means the row became Cancelled under the lock.
	 *
	 * @param   integer  $affectedRows  Driver affected-row count from the UPDATE.
	 * @param   string   $outcome       EventStatusTransition::resolve() result.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function shouldNotify(int $affectedRows, string $outcome): bool
	{
		return $outcome === EventStatusTransition::OK && $affectedRows === 1;
	}

	/**
	 * Conditionally mark an event Cancelled; true when this call changed it.
	 *
	 * Prefer the lock + EventStatusTransition cancel paths in production.
	 * Kept for unit tests of the affected-rows send-once pattern.
	 *
	 * @param   DatabaseInterface  $db       Database.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function transition(DatabaseInterface $db, int $eventId): bool
	{
		return EventCancelTransition::apply($db, $eventId);
	}

	/**
	 * Send cancel notices for one event. Never throws; mail failures are logged.
	 *
	 * @param   integer  $eventId  Event that just became Cancelled.
	 *
	 * @return  array{sent: int, skipped: int, failed: int}
	 *
	 * @since   1.0.0
	 */
	public function notify(int $eventId): array
	{
		$result = [
			'sent'    => 0,
			'skipped' => 0,
			'failed'  => 0,
		];

		if ($eventId < 1)
		{
			return $result;
		}

		try
		{
			$this->loadLanguage();

			$recipients = $this->recipients ?? $this->loadRecipients($eventId);

			foreach ($recipients as $row)
			{
				$profileId = (int) ($row->profile_id ?? 0);
				$email     = trim((string) ($row->email ?? ''));
				$name      = (string) ($row->name ?? '');
				$title     = (string) ($row->title ?? '');
				$eventDate = (string) ($row->event_date ?? '');
				$response  = (int) ($row->response ?? 0);

				if ($profileId < 1 || $email === '')
				{
					$result['skipped']++;

					continue;
				}

				if (!ReminderPlanner::receivesEventReminder($response))
				{
					$result['skipped']++;

					continue;
				}

				if (RenewalReminderOptOut::isOptedOutFromParams($row->user_params ?? null))
				{
					$result['skipped']++;

					continue;
				}

				$safeName  = self::plainText($name);
				$safeTitle = self::plainText($title);
				$safeSite  = self::plainText($this->siteName);
				$formatted = DateOnly::format($eventDate, 'F j, Y');
				$safeDate  = self::plainText($formatted !== '' ? $formatted : $eventDate);
				$safeUrl   = $this->absoluteEventUrl($eventId);

				$subject = Text::sprintf('COM_WENDFOLD_EMAIL_EVENT_CANCEL_SUBJECT', $safeTitle);
				$body    = Text::sprintf(
					'COM_WENDFOLD_EMAIL_EVENT_CANCEL_BODY',
					$safeName,
					$safeTitle,
					$safeDate,
					$safeUrl,
					$safeSite
				);

				if (!$this->mailer->send($email, $name, $subject, $body))
				{
					Log::add(
						MailerErrorRedactor::append(
							'Event cancel notice failed for profile ' . $profileId . ' event ' . $eventId,
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
				MailerErrorRedactor::append('Event cancel notices aborted', $e->getMessage()),
				Log::ERROR,
				'com_wendfold'
			);
		}

		return $result;
	}

	/**
	 * Notify after a committed cancel transition. Never throws.
	 *
	 * @param   DatabaseInterface  $db             Database.
	 * @param   integer            $eventId        Event id.
	 * @param   boolean            $transitioned   Result of EventCancelTransition::apply().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function notifyAfterTransition(
		DatabaseInterface $db,
		int $eventId,
		bool $transitioned
	): void
	{
		if (!$transitioned || $eventId < 1)
		{
			return;
		}

		try
		{
			$app = Factory::getApplication();
			self::fromDatabase(
				$db,
				(string) $app->get('mailfrom'),
				(string) $app->get('fromname', 'Wendfold'),
				(string) $app->get('sitename', '')
			)->notify($eventId);
		}
		catch (\Throwable $e)
		{
			Log::add(
				MailerErrorRedactor::append(
					'Event cancel notice dispatch failed for event ' . $eventId,
					$e->getMessage()
				),
				Log::ERROR,
				'com_wendfold'
			);
		}
	}

	/**
	 * Absolute public event URL for the plain-text body.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function absoluteEventUrl(int $eventId): string
	{
		if ($this->eventAbsoluteUrl !== null)
		{
			return self::plainText($this->eventAbsoluteUrl);
		}

		try
		{
			return self::plainText(
				RouteHelper::site(
					[
						'view' => 'event',
						'id'   => $eventId,
					],
					true
				)
			);
		}
		catch (\Throwable $e)
		{
			return '';
		}
	}

	/**
	 * Strip CR/LF so title, URL, and name cannot break mail headers.
	 *
	 * @param   string  $value  Untrusted text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function plainText(string $value): string
	{
		return str_replace(["\r", "\n"], ' ', $value);
	}

	/**
	 * Load component language strings for cancel mail templates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadLanguage(): void
	{
		try
		{
			$lang = Factory::getApplication()->getLanguage();
			$lang->load('com_wendfold', JPATH_ADMINISTRATOR)
				|| $lang->load('com_wendfold', JPATH_SITE);
		}
		catch (\Throwable $e)
		{
			// Text keys fall back to their literal names when language is unavailable.
		}
	}

	/**
	 * Yes/Maybe RSVP recipients for one event, with user params for opt-out.
	 *
	 * Same audience shape as ReminderService::loadEventRecipients, scoped to
	 * a single event id (any calendar day). Cancelled status is expected.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	private function loadRecipients(int $eventId): array
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
						$db->quoteName('e.event_date'),
						$db->quoteName('p.id', 'profile_id'),
						$db->quoteName('r.response'),
						$db->quoteName('u.name'),
						$db->quoteName('u.email'),
						$db->quoteName('u.params', 'user_params'),
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
				->where($db->quoteName('e.id') . ' = :eventId')
				->where($db->quoteName('p.published') . ' = 1')
				->where($db->quoteName('u.block') . ' = 0')
				->where(
					$db->quoteName('r.response') . ' IN ('
					. ReminderPlanner::RSVP_GOING . ',' . ReminderPlanner::RSVP_MAYBE . ')'
				)
				->bind(':eventId', $eventId, ParameterType::INTEGER);

			$rows = $db->setQuery($query)->loadObjectList();

			return \is_array($rows) ? $rows : [];
		}
		catch (\Throwable $e)
		{
			Log::add(
				'Event cancel notice recipient query failed: ' . $e->getMessage(),
				Log::ERROR,
				'com_wendfold'
			);

			return [];
		}
	}
}
