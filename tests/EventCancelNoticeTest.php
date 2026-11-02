<?php

/**
 * Wendfold - Event cancel notice audience, send-once, failure isolation.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventCancelTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\EventStatusTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Administrator\Service\EventCancelNoticeService;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderPlanner;
use Wendfold\Tests\Support\RecordingReminderMailer;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventCancelTransition.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventStatusTransition.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventRowLock.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MailerErrorRedactor.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RenewalReminderOptOut.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderPlanner.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderMailer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/EventCancelNoticeService.php';
require_once __DIR__ . '/support/RecordingReminderMailer.php';
require_once __DIR__ . '/support/RegistryStub.php';
require_once __DIR__ . '/support/ReminderTextStub.php';

/**
 * Cancel notice audience, once-per-transition, and mail isolation.
 *
 * @since  1.0.0
 */
final class EventCancelNoticeTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		Log::$messages = [];
	}

	/**
	 * Yes and Maybe RSVPs are emailed; No, missing response, and opt-out are not.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAudienceIncludesYesMaybeExcludesNoNoneAndOptOut(): void
	{
		$mailer = new RecordingReminderMailer;
		$optKey = RenewalReminderOptOut::PARAM_KEY;
		$recipients = [
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 1,
				'name'        => 'Yes',
				'email'       => 'yes@example.com',
				'response'    => ReminderPlanner::RSVP_GOING,
				'user_params' => '',
			],
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 2,
				'name'        => 'Maybe',
				'email'       => 'maybe@example.com',
				'response'    => ReminderPlanner::RSVP_MAYBE,
				'user_params' => '',
			],
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 3,
				'name'        => 'No',
				'email'       => 'no@example.com',
				'response'    => 3,
				'user_params' => '',
			],
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 4,
				'name'        => 'None',
				'email'       => 'none@example.com',
				'response'    => 0,
				'user_params' => '',
			],
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 5,
				'name'        => 'Opted',
				'email'       => 'opted@example.com',
				'response'    => ReminderPlanner::RSVP_GOING,
				'user_params' => '{"' . $optKey . '":"1"}',
			],
		];

		$service = new EventCancelNoticeService(
			$mailer,
			'Example Site',
			$recipients,
			null,
			'https://example.test/event/9'
		);
		$result  = $service->notify(9);

		$this->assertSame(2, $result['sent']);
		$this->assertSame(3, $result['skipped']);
		$this->assertSame(0, $result['failed']);
		$this->assertCount(2, $mailer->sent);
		$this->assertSame('yes@example.com', $mailer->sent[0]['email']);
		$this->assertSame('maybe@example.com', $mailer->sent[1]['email']);
		$this->assertStringContainsString('Event cancelled:', $mailer->sent[0]['subject']);
		$this->assertStringContainsString('Chapter Ride', $mailer->sent[0]['subject']);
		$this->assertStringContainsString('Chapter Ride', $mailer->sent[0]['body']);
		$this->assertStringContainsString('Example Site', $mailer->sent[0]['body']);
		$this->assertStringContainsString('November 15, 2026', $mailer->sent[0]['body']);
		$this->assertStringContainsString('https://example.test/event/9', $mailer->sent[0]['body']);
	}

	/**
	 * A failed address is logged and later recipients still receive mail.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMailFailureDoesNotBlockOtherRecipients(): void
	{
		$mailer = new RecordingReminderMailer;
		$mailer->failEmails = ['bad@example.com'];
		$mailer->failureDetail = 'SMTP Error: recipients failed: bad@example.com';
		$recipients = [
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 1,
				'name'        => 'Bad',
				'email'       => 'bad@example.com',
				'response'    => ReminderPlanner::RSVP_GOING,
				'user_params' => '',
			],
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 2,
				'name'        => 'Good',
				'email'       => 'good@example.com',
				'response'    => ReminderPlanner::RSVP_MAYBE,
				'user_params' => '',
			],
		];

		$service = new EventCancelNoticeService(
			$mailer,
			'Example Site',
			$recipients,
			null,
			'https://example.test/event/9'
		);
		$result  = $service->notify(9);

		$this->assertSame(1, $result['sent']);
		$this->assertSame(1, $result['failed']);
		$this->assertCount(1, $mailer->sent);
		$this->assertSame('good@example.com', $mailer->sent[0]['email']);
		$this->assertNotEmpty(Log::$messages);

		$joined = '';

		foreach (Log::$messages as $entry)
		{
			$message = (string) $entry[0];
			$joined .= $message . "\n";
			$this->assertStringNotContainsString(
				'bad@example.com',
				$message,
				'Failure logs must not include the recipient address'
			);
			$this->assertStringNotContainsString(
				'@example.com',
				$message,
				'Failure logs must not include any recipient address'
			);
		}

		$this->assertStringContainsString('profile 1', $joined);
		$this->assertStringContainsString('event 9', $joined);
		$this->assertStringContainsString('[redacted]', $joined);
		$this->assertStringContainsString('SMTP Error: recipients failed:', $joined);
	}

	/**
	 * becameCancelled is true only when status moves into Cancelled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBecameCancelledRequiresRealStatusMove(): void
	{
		$this->assertTrue(EventCancelNoticeService::becameCancelled(1, 3));
		$this->assertTrue(EventCancelNoticeService::becameCancelled(4, 3));
		$this->assertFalse(EventCancelNoticeService::becameCancelled(3, 3));
		$this->assertFalse(EventCancelNoticeService::becameCancelled(1, 1));
		$this->assertFalse(EventCancelNoticeService::becameCancelled(3, 1));
	}

	/**
	 * JoomlaReminderMailer stores failure detail and never logs the recipient.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testJoomlaReminderMailerSourceOmitsEmailFromFailureLog(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/JoomlaReminderMailer.php'
		);

		$this->assertStringContainsString('lastError', $source);
		$this->assertStringNotContainsString('Log::add', $source);
		$this->assertDoesNotMatchRegularExpression(
			"/lastError\s*=\s*[^;]*\$email/",
			$source
		);
	}

	/**
	 * notifyAfterTransition is a no-op when the conditional UPDATE changed nothing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNotifyAfterTransitionSkipsWhenNotTransitioned(): void
	{
		$db = $this->createMock(DatabaseInterface::class);
		$db->expects($this->never())->method('createQuery');

		EventCancelNoticeService::notifyAfterTransition($db, 9, false);
	}

	/**
	 * shouldNotify requires both OK and exactly one affected row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testShouldNotifyOnlyForRealCancelTransition(): void
	{
		$this->assertTrue(
			EventCancelNoticeService::shouldNotify(1, EventStatusTransition::OK)
		);
		$this->assertFalse(
			EventCancelNoticeService::shouldNotify(0, EventStatusTransition::OK)
		);
		$this->assertFalse(
			EventCancelNoticeService::shouldNotify(1, EventStatusTransition::DB_ERROR)
		);
		$this->assertFalse(
			EventCancelNoticeService::shouldNotify(1, EventStatusTransition::EVENT_CLOSED)
		);
		$this->assertFalse(
			EventCancelNoticeService::shouldNotify(0, EventStatusTransition::DB_ERROR)
		);
	}

	/**
	 * Conditional cancel UPDATE reports true once, then false until status leaves Cancelled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTransitionSendOnceThenAgainAfterUncancel(): void
	{
		$db = new CancelTransitionDatabaseDouble;
		$db->statusById[9] = 1;

		$this->assertTrue(EventCancelTransition::apply($db, 9));
		$this->assertSame(3, $db->statusById[9]);
		$this->assertFalse(EventCancelTransition::apply($db, 9));
		$this->assertSame(1, $db->updateCount);

		$db->statusById[9] = 1;
		$this->assertTrue(EventCancelTransition::apply($db, 9));
		$this->assertSame(2, $db->updateCount);
		$this->assertSame(3, $db->statusById[9]);
	}

	/**
	 * Saving an already-cancelled event (status already 3) does not transition.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRepeatCancelOnAlreadyCancelledSendsNothing(): void
	{
		$db = new CancelTransitionDatabaseDouble;
		$db->statusById[9] = EventCancelTransition::STATUS_CANCELLED;
		$mailer = new RecordingReminderMailer;
		$recipients = [
			(object) [
				'event_id'    => 9,
				'title'       => 'Chapter Ride',
				'event_date'  => '2026-11-15',
				'profile_id'  => 1,
				'name'        => 'Yes',
				'email'       => 'yes@example.com',
				'response'    => ReminderPlanner::RSVP_GOING,
				'user_params' => '',
			],
		];

		$transitioned = EventCancelTransition::apply($db, 9);
		$this->assertFalse($transitioned);

		if ($transitioned)
		{
			(new EventCancelNoticeService($mailer, 'Example Site', $recipients))->notify(9);
		}

		$this->assertSame([], $mailer->sent);
	}
}

/**
 * Minimal DatabaseInterface stand-in for EventCancelTransition::apply().
 *
 * @since  1.0.0
 */
final class CancelTransitionDatabaseDouble implements DatabaseInterface
{
	/**
	 * Current status by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $statusById = [];

	/**
	 * Successful status-changing updates.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $updateCount = 0;

	/**
	 * Last affected-row count.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $affected = 0;

	/**
	 * Pending update parameters from the last query.
	 *
	 * @var    array{id: int, cancelled: int, already: int}|null
	 * @since  1.0.0
	 */
	private ?array $pending = null;

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  CancelTransitionQueryDouble
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		return new CancelTransitionQueryDouble($this);
	}

	/**
	 * Create a new query object.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function createQuery()
	{
		return $this->getQuery(true);
	}

	/**
	 * @param   mixed    $query   Query object.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		if ($query instanceof CancelTransitionQueryDouble)
		{
			$this->pending = $query->params;
		}

		return $this;
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
		$this->affected = 0;

		if ($this->pending === null)
		{
			return true;
		}

		$id        = $this->pending['id'];
		$cancelled = $this->pending['cancelled'];
		$already   = $this->pending['already'];
		$current   = $this->statusById[$id] ?? null;

		if ($current !== null && $current !== $already)
		{
			$this->statusById[$id] = $cancelled;
			$this->affected        = 1;
			$this->updateCount++;
		}

		$this->pending = null;

		return true;
	}

	/**
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public function getAffectedRows()
	{
		return $this->affected;
	}

	/**
	 * @param   string  $name  Table or column name.
	 * @param   mixed   $as    Unused alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		return '`' . $name . '`';
	}

	/**
	 * @param   mixed  $key  Unused.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null)
	{
		return [];
	}
}

/**
 * Query builder double that records the cancel UPDATE binds.
 *
 * @since  1.0.0
 */
final class CancelTransitionQueryDouble
{
	/**
	 * Bound parameters for the cancel UPDATE.
	 *
	 * @var    array{id: int, cancelled: int, already: int}
	 * @since  1.0.0
	 */
	public array $params = [
		'id'        => 0,
		'cancelled' => 0,
		'already'   => 0,
	];

	/**
	 * @param   CancelTransitionDatabaseDouble  $db  Parent double.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private CancelTransitionDatabaseDouble $db)
	{
	}

	/**
	 * @param   mixed  $table  Table name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function update($table)
	{
		return $this;
	}

	/**
	 * @param   mixed   $conditions  SET fragment.
	 * @param   string  $glue        Join glue.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function set($conditions, $glue = ',')
	{
		return $this;
	}

	/**
	 * @param   mixed   $conditions  WHERE fragment.
	 * @param   string  $glue        Join glue.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($conditions, $glue = 'AND')
	{
		return $this;
	}

	/**
	 * @param   string   $key            Bind key.
	 * @param   mixed    $value          Value.
	 * @param   integer  $dataType       Parameter type.
	 * @param   integer  $length         Unused length.
	 * @param   array    $driverOptions  Unused driver options.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function bind($key, &$value, $dataType = 2, $length = 0, $driverOptions = [])
	{
		$name = ltrim((string) $key, ':');

		if ($name === 'id')
		{
			$this->params['id'] = (int) $value;
		}
		elseif ($name === 'cancelled')
		{
			$this->params['cancelled'] = (int) $value;
		}
		elseif ($name === 'already')
		{
			$this->params['already'] = (int) $value;
		}

		return $this;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function __toString()
	{
		return 'UPDATE';
	}
}
