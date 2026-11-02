<?php

/**
 * Wendfold - ReminderService catch-up, dedupe, exclusions, timezone.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Log\Log;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderPlanner;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderService;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Wendfold\Tests\Support\MemoryReminderLogStore;
use Wendfold\Tests\Support\RecordingReminderMailer;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ComponentOptions.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MailerErrorRedactor.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RenewalReminderOptOut.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderPlanner.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderLogStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderMailer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderService.php';
require_once __DIR__ . '/support/MemoryReminderLogStore.php';
require_once __DIR__ . '/support/RecordingReminderMailer.php';
require_once __DIR__ . '/support/RegistryStub.php';
require_once __DIR__ . '/support/ReminderTextStub.php';

/**
 * Shared renewal and event reminder sender behaviour.
 *
 * @since  1.0.0
 */
final class ReminderServiceTest extends TestCase
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $previousTimezone;

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		$this->previousTimezone = date_default_timezone_get();
		date_default_timezone_set('UTC');
		Log::$messages = [];
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function tearDown(): void
	{
		date_default_timezone_set($this->previousTimezone);
	}

	/**
	 * Catch-up after a missed week sends only the most urgent offset.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCatchUpAfterMissedWeekSendsMostUrgentOnly(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-20 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(1, $result['sent_30']);
		$this->assertCount(1, $mailer->sent);
		$this->assertSame(
			[ReminderPlanner::renewalKey(30), ReminderPlanner::renewalKey(60)],
			$log->keysFor(4, '2026-11-01')
		);
	}

	/**
	 * A second run in the same membership year sends nothing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoResendInSameYear(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$members = [
			(object) [
				'id' => 4,
				'renewal_date' => '2026-11-01',
				'name' => 'Ada',
				'email' => 'ada@example.com',
				'user_params' => '',
			],
		];
		$service = $this->service($log, $mailer, '2026-09-02 12:00:00', $members);
		$service->sendRenewalReminders();
		$mailer->sent = [];
		$service->sendRenewalReminders();

		$this->assertSame([], $mailer->sent);
	}

	/**
	 * A new expiry date is reminded again the following year.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResendNextYearForNewExpiry(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$log->claim(4, ReminderPlanner::renewalKey(60), '2025-11-01', '2025-09-02 12:00:00');
		$log->claim(4, ReminderPlanner::renewalKey(30), '2025-11-01', '2025-10-02 12:00:00');

		$service = $this->service(
			$log,
			$mailer,
			'2026-09-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(1, $result['sent_60']);
		$this->assertCount(1, $mailer->sent);
	}

	/**
	 * Life, blocked (absent from candidates), and opted-out members are skipped.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOptedOutMemberIsSkipped(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$key = RenewalReminderOptOut::PARAM_KEY;
		$service = $this->service(
			$log,
			$mailer,
			'2026-09-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '{"' . $key . '":"1"}',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame(1, $result['skipped']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * A failed send releases the claim so the next run can retry.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFailedSendIsNotLoggedAsSent(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$mailer->fail = true;
		$mailer->failureDetail = 'recipients failed: Ada@Example.com <other+tag@example.org>';
		$service = $this->service(
			$log,
			$mailer,
			'2026-09-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(1, $result['failed']);
		$this->assertSame([], $log->keysFor(4, '2026-11-01'));
		$this->assertNotEmpty(Log::$messages);

		$joined = '';

		foreach (Log::$messages as $entry)
		{
			$message = (string) $entry[0];
			$joined .= $message . "\n";
			$this->assertStringNotContainsString('Ada@Example.com', $message);
			$this->assertStringNotContainsString('other+tag@example.org', $message);
			$this->assertStringNotContainsString('@example.', $message);
		}

		$this->assertStringContainsString('profile 4', $joined);
		$this->assertStringContainsString('[redacted]', $joined);
	}

	/**
	 * Event reminder failures redact mailer addresses and keep profile/event ids.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventReminderFailureRedactsMailerAddresses(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$mailer->fail = true;
		$mailer->failureDetail = 'SMTP recipients failed: yes@example.com, maybe@example.net';
		$recipients = [
			(object) [
				'event_id'    => 12,
				'title'       => 'Ride',
				'event_date'  => '2026-10-31',
				'ksu_time'    => '',
				'profile_id'  => 8,
				'name'        => 'Pat',
				'email'       => 'yes@example.com',
				'response'    => ReminderPlanner::RSVP_GOING,
				'published'   => 1,
				'user_params' => '',
			],
		];

		$service = $this->service($log, $mailer, '2026-10-31 12:00:00', [], $recipients);
		$result  = $service->sendEventReminders();

		$this->assertSame(1, $result['failed']);
		$this->assertNotEmpty(Log::$messages);

		$joined = '';

		foreach (Log::$messages as $entry)
		{
			$message = (string) $entry[0];
			$joined .= $message . "\n";
			$this->assertStringNotContainsString('yes@example.com', $message);
			$this->assertStringNotContainsString('maybe@example.net', $message);
			$this->assertStringNotContainsString('@example.', $message);
		}

		$this->assertStringContainsString('profile 8', $joined);
		$this->assertStringContainsString('event 12', $joined);
		$this->assertStringContainsString('[redacted]', $joined);
	}

	/**
	 * Upgrade path: legacy reminder_60_sent with no reminder_log row does not
	 * resend the 60-day mail; the 30-day offset still sends when due.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtySentNoLogDoesNotResendSixty(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$member = (object) [
			'id' => 4,
			'renewal_date' => '2026-11-01',
			'name' => 'Ada',
			'email' => 'ada@example.com',
			'user_params' => '',
			'reminder_60_sent' => '2026-09-02',
			'reminder_30_sent' => null,
		];

		$midWindow = $this->service($log, $mailer, '2026-09-17 12:00:00', [$member]);
		$mid = $midWindow->sendRenewalReminders();

		$this->assertSame(0, $mid['sent_60']);
		$this->assertSame(0, $mid['sent_30']);
		$this->assertSame([], $mailer->sent);

		$thirtyDue = $this->service($log, $mailer, '2026-10-02 12:00:00', [$member]);
		$result = $thirtyDue->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(1, $result['sent_30']);
		$this->assertCount(1, $mailer->sent);
	}

	/**
	 * Renewal moved earlier after a legacy 60-day send must not reopen that stage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtyDoesNotResendWhenRenewalMovedEarlier(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-31 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-12-15',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => '2026-10-31',
					'reminder_30_sent' => null,
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * Renewal moved later after a legacy 60-day send must not reopen that stage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacySixtyDoesNotResendWhenRenewalMovedLater(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-31 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-12-30',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => '2026-10-16',
					'reminder_30_sent' => null,
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * Renewal moved later after a legacy 30-day send must not reopen that stage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacyThirtyDoesNotResendWhenRenewalMovedLater(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-31 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-30',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => null,
					'reminder_30_sent' => '2026-10-16',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * Upgrade path: legacy 30-day sent with 60 NULL must not send a farther
	 * 60-day mail after upgrade when the 60 window is still open.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLegacyThirtySentDoesNotSendFartherSixty(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => null,
					'reminder_30_sent' => '2026-10-02',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * New tracking: a logged 30-day send blocks a farther 60-day catch-up.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLoggedThirtyDoesNotSendFartherSixty(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$log->claim(4, ReminderPlanner::renewalKey(30), '2026-11-01', '2026-10-02 12:00:00');
		$service = $this->service(
			$log,
			$mailer,
			'2026-10-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => null,
					'reminder_30_sent' => null,
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame(0, $result['sent_30']);
		$this->assertSame([], $mailer->sent);
	}

	/**
	 * A previous-cycle legacy flag does not suppress reminders for a new expiry.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPreviousCycleLegacyFlagDoesNotSuppressNewCycle(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-09-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
					'reminder_60_sent' => '2025-09-02',
					'reminder_30_sent' => '2025-10-02',
				],
			]
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(1, $result['sent_60']);
		$this->assertCount(1, $mailer->sent);
	}

	/**
	 * Running event reminders twice on the same site day yields one mail per Yes/Maybe RSVP.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventReminderRunTwiceGivesOneMailPerRsvp(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$recipients = [
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '08:00:00',
				'event_date' => '2026-10-31',
				'profile_id' => 4,
				'name' => 'Ada',
				'email' => 'ada@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
			],
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '08:00:00',
				'event_date' => '2026-10-31',
				'profile_id' => 5,
				'name' => 'Bea',
				'email' => 'bea@example.com',
				'response' => ReminderPlanner::RSVP_MAYBE,
			],
		];
		$service = $this->service($log, $mailer, '2026-10-31 12:00:00', [], $recipients);

		$first = $service->sendEventReminders();
		$second = $service->sendEventReminders();

		$this->assertSame(2, $first['sent']);
		$this->assertSame(0, $second['sent']);
		$this->assertSame(2, $second['skipped']);
		$this->assertCount(2, $mailer->sent);
		$this->assertStringContainsString('Event Reminder', $mailer->sent[0]['subject']);
		$this->assertStringContainsString('See you there!', $mailer->sent[0]['body']);
		$this->assertStringContainsString('Reminder:', $mailer->sent[0]['body']);
		$this->assertStringNotContainsString('Kickstands Up', $mailer->sent[0]['body']);
	}

	/**
	 * Unpublished and trashed events do not receive event reminder mail.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventReminderSkipsUnpublishedAndTrashed(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$recipients = [
			(object) [
				'event_id' => 9,
				'title' => 'Unpublished Meet',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 4,
				'name' => 'Ada',
				'email' => 'ada@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
				'published' => 0,
			],
			(object) [
				'event_id' => 10,
				'title' => 'Trashed Meet',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 5,
				'name' => 'Bea',
				'email' => 'bea@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
				'published' => -2,
			],
			(object) [
				'event_id' => 11,
				'title' => 'Live Meet',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 6,
				'name' => 'Cal',
				'email' => 'cal@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
				'published' => 1,
			],
		];
		$service = $this->service($log, $mailer, '2026-10-31 12:00:00', [], $recipients);
		$result = $service->sendEventReminders();

		$this->assertSame(1, $result['sent']);
		$this->assertSame(2, $result['skipped']);
		$this->assertSame('cal@example.com', $mailer->sent[0]['email']);
	}

	/**
	 * No and waitlisted RSVPs do not receive event reminder mail.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventReminderSkipsNoAndWaitlisted(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$recipients = [
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 4,
				'name' => 'No',
				'email' => 'no@example.com',
				'response' => 3,
			],
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 5,
				'name' => 'Wait',
				'email' => 'wait@example.com',
				'response' => 4,
			],
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 6,
				'name' => 'Yes',
				'email' => 'yes@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
			],
		];
		$service = $this->service($log, $mailer, '2026-10-31 12:00:00', [], $recipients);
		$result = $service->sendEventReminders();

		$this->assertSame(1, $result['sent']);
		$this->assertSame(2, $result['skipped']);
		$this->assertCount(1, $mailer->sent);
		$this->assertSame('yes@example.com', $mailer->sent[0]['email']);
	}

	/**
	 * "Today" follows the site timezone, not UTC.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTimezoneBoundaryUsesSiteToday(): void
	{
		// 2026-11-01 02:30 UTC is still 2026-10-31 in America/Chicago.
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$clock = new \DateTimeImmutable('2026-11-01 02:30:00', new \DateTimeZone('UTC'));
		$site = new SiteTime('America/Chicago', $clock);
		$windows = ComponentOptions::reminderWindows(60, 30);
		$recipients = [
			(object) [
				'event_id' => 9,
				'title' => 'Chapter Meeting',
				'ksu_time' => '',
				'event_date' => '2026-10-31',
				'profile_id' => 4,
				'name' => 'Ada',
				'email' => 'ada@example.com',
				'response' => ReminderPlanner::RSVP_GOING,
			],
		];
		$service = new ReminderService($log, $mailer, $site, $windows, [], $recipients, null, true);

		$result = $service->sendEventReminders();

		$this->assertSame('2026-10-31', $site->today());
		$this->assertSame(1, $result['sent']);
		$this->assertArrayHasKey('4|' . ReminderPlanner::eventKey(9, 0) . '|2026-10-31', $log->rows);
	}

	/**
	 * Missing reminder log skips sending and logs instead of throwing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMissingReminderLogDegradesSafely(): void
	{
		$log = new MemoryReminderLogStore;
		$mailer = new RecordingReminderMailer;
		$service = $this->service(
			$log,
			$mailer,
			'2026-09-02 12:00:00',
			[
				(object) [
					'id' => 4,
					'renewal_date' => '2026-11-01',
					'name' => 'Ada',
					'email' => 'ada@example.com',
					'user_params' => '',
				],
			],
			null,
			false
		);

		$result = $service->sendRenewalReminders();

		$this->assertSame(0, $result['sent_60']);
		$this->assertSame([], $mailer->sent);
		$this->assertNotEmpty(Log::$messages);
	}

	/**
	 * Build a ReminderService with a frozen site clock.
	 *
	 * @param   MemoryReminderLogStore     $log              Log store.
	 * @param   RecordingReminderMailer    $mailer           Mailer.
	 * @param   string                     $utcNow           Frozen UTC instant.
	 * @param   array                      $members          Renewal candidates.
	 * @param   array|null                 $eventRecipients  Event recipients.
	 * @param   boolean|null               $logReady         Schema readiness.
	 *
	 * @return  ReminderService
	 *
	 * @since   1.0.0
	 */
	private function service(
		MemoryReminderLogStore $log,
		RecordingReminderMailer $mailer,
		string $utcNow,
		array $members,
		?array $eventRecipients = null,
		?bool $logReady = true
	): ReminderService
	{
		$clock = new \DateTimeImmutable($utcNow, new \DateTimeZone('UTC'));
		$site = new SiteTime('UTC', $clock);
		$windows = ComponentOptions::reminderWindows(60, 30);

		return new ReminderService(
			$log,
			$mailer,
			$site,
			$windows,
			$members,
			$eventRecipients,
			null,
			$logReady
		);
	}
}
