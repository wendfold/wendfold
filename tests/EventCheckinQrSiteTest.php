<?php

/**
 * Wendfold - Site event page check-in QR visibility and guest token isolation.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\QrCodeHelper;
use Wendfold\Component\Wendfold\Site\Helper\EventCheckinQr;
use Wendfold\Component\Wendfold\Site\Helper\SelfCheckinStatus;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/QrCodeHelper.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/SelfCheckinStatus.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/EventCheckinQr.php';

/**
 * Locks guest token isolation, member QR display, status/past hiding, shared
 * self-check-in status predicate, and anonymous check-in rejection.
 *
 * @since  1.0.0
 */
final class EventCheckinQrSiteTest extends TestCase
{
	/**
	 * Fixed site clock for end-date comparisons.
	 *
	 * @var    SiteTime
	 * @since  1.0.0
	 */
	private SiteTime $clock;

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		$this->clock = new SiteTime(
			'UTC',
			new \DateTimeImmutable('2026-06-15 12:00:00', new \DateTimeZone('UTC'))
		);
	}

	/**
	 * Guests never see a check-in QR or receive the token for display.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGuestSeesNoTokenAndNoQr(): void
	{
		$event = $this->openEvent('tokenguest1234567890abcdef');

		self::assertFalse(EventCheckinQr::isVisible($event, 0, $this->clock));
		self::assertNull(EventCheckinQr::forViewer($event, 0, $this->clock));

		$view = $this->viewSource();
		$template = $this->eventTemplate();

		self::assertStringContainsString('EventCheckinQr::forViewer', $view);
		self::assertStringContainsString("array_key_exists('qr_token', \$itemFields)", $view);
		self::assertStringContainsString('unset($this->item->qr_token)', $view);
		self::assertStringContainsString('!empty($this->checkinQr)', $template);
		self::assertStringNotContainsString('qr_token', $template);
		self::assertStringNotContainsString("['token'", $template);
		self::assertStringNotContainsString('view=checkin', $template);
	}

	/**
	 * A signed-in member on an open future event gets a local QR image.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberSeesQrImage(): void
	{
		$event = $this->openEvent('tokenmember1234567890abcdef');
		$token = (string) get_object_vars($event)['qr_token'];
		$checkinUrl = 'https://example.test/index.php?option=com_wendfold&view=checkin&token=' . $token;

		self::assertTrue(EventCheckinQr::isVisible($event, 42, $this->clock));

		$payload = EventCheckinQr::imagePayload($checkinUrl, (int) get_object_vars($event)['id']);

		self::assertNotNull($payload);
		self::assertSame(7, $payload['eventId']);
		self::assertNotSame('', $payload['dataUri']);
		self::assertStringStartsWith('data:image/', $payload['dataUri']);
		self::assertContains($payload['extension'], ['png', 'svg']);
		self::assertSame(QrCodeHelper::fileExtension(), $payload['extension']);

		$template = $this->eventTemplate();

		self::assertStringContainsString('hc-event-layout__checkin-qr', $template);
		self::assertStringContainsString("htmlspecialchars((string) \$this->checkinQr['dataUri']", $template);
		self::assertStringContainsString("htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_CHECKIN_QR')", $template);
		self::assertStringContainsString('download="<?php echo $qrFile; ?>"', $template);
		self::assertStringContainsString(
			"RouteHelper::site(['view' => 'checkin', 'token' => \$token], true)",
			$this->helperSource()
		);
	}

	/**
	 * Every known and unknown status: QR matches SelfCheckinStatus::allows().
	 *
	 * @return  array<string, array{0: int, 1: bool}>
	 *
	 * @since   1.0.0
	 */
	public static function eventStatusProvider(): array
	{
		return [
			'scheduled'     => [SelfCheckinStatus::SCHEDULED, true],
			'completed'     => [2, false],
			'cancelled'     => [3, false],
			'confirmed'     => [SelfCheckinStatus::CONFIRMED, true],
			'weather watch' => [5, false],
			'zero'          => [0, false],
			'unknown'       => [99, false],
			'negative'      => [-1, false],
		];
	}

	/**
	 * The QR shows only for statuses that self check-in accepts.
	 *
	 * @param   integer  $status   Stored event status.
	 * @param   boolean  $visible  Whether a signed-in member may see the QR.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('eventStatusProvider')]
	public function testQrVisibilityMatchesSelfCheckinStatus(int $status, bool $visible): void
	{
		$event = $this->openEvent('tokenstatus1234567890abcdef', ['status' => $status]);

		self::assertSame($visible, SelfCheckinStatus::allows($status));
		self::assertSame($visible, EventCheckinQr::isVisible($event, 42, $this->clock));

		if ($visible)
		{
			self::assertTrue(EventCheckinQr::isVisible($event, 42, $this->clock));
		}
		else
		{
			self::assertNull(EventCheckinQr::forViewer($event, 42, $this->clock));
		}
	}

	/**
	 * CheckinModel::checkIn uses the same SelfCheckinStatus predicate as the QR.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckinPathUsesSharedSelfCheckinStatusPredicate(): void
	{
		$model = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/CheckinModel.php'
		);
		$qr = $this->helperSource();

		self::assertSame([1, 4], SelfCheckinStatus::allowed());
		self::assertStringContainsString('use Wendfold\\Component\\Wendfold\\Site\\Helper\\SelfCheckinStatus;', $model);
		self::assertSame(2, substr_count($model, 'SelfCheckinStatus::allows('));
		self::assertStringNotContainsString('in_array((int) ($event->status ?? 0), [1, 4], true)', $model);
		self::assertStringNotContainsString('in_array((int) $locked->status, [1, 4], true)', $model);
		self::assertStringContainsString('SelfCheckinStatus::allows((int) ($fields[\'status\'] ?? 0))', $qr);
		self::assertStringNotContainsString('STATUS_CANCELLED', $qr);
	}

	/**
	 * Past events (after end date and time) hide the QR for every viewer.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPastEventShowsNoQr(): void
	{
		$sameDayPast = $this->openEvent(
			'tokenpastsame1234567890abcd',
			['ksd_time' => '09:00:00']
		);

		$priorDay = $this->openEvent(
			'tokenpastday1234567890abcdef',
			[
				'event_date' => '2026-06-14',
				'end_date'   => '2026-06-14',
				'ksd_time'   => '18:00:00',
			]
		);

		$overnightPast = $this->openEvent(
			'tokenpastover1234567890abcde',
			[
				'event_date' => '2026-06-13',
				'end_date'   => '2026-06-14',
				'ksd_time'   => '20:00:00',
			]
		);

		foreach ([$sameDayPast, $priorDay, $overnightPast] as $event)
		{
			self::assertTrue(EventCheckinQr::hasEnded($event, $this->clock));
			self::assertFalse(EventCheckinQr::isVisible($event, 42, $this->clock));
			self::assertNull(EventCheckinQr::forViewer($event, 42, $this->clock));
		}

		$stillOpen = $this->openEvent(
			'tokenstillopen1234567890abcd',
			['ksd_time' => '18:00:00']
		);

		self::assertFalse(EventCheckinQr::hasEnded($stillOpen, $this->clock));
		self::assertTrue(EventCheckinQr::isVisible($stillOpen, 42, $this->clock));
	}

	/**
	 * Anonymous check-in confirm requests are rejected without weakening CSRF.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAnonymousCheckinWithTokenIsRejected(): void
	{
		$controller = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Controller/CheckinController.php'
		);
		$body = $this->functionBody($controller, 'confirm');

		self::assertStringContainsString('PostGuard::passes()', $body);
		self::assertLessThan(
			(int) strpos($body, '!$user->id'),
			(int) strpos($body, 'PostGuard::passes()')
		);
		self::assertLessThan(
			(int) strpos($body, 'getModel'),
			(int) strpos($body, '!$user->id')
		);
		self::assertStringContainsString('COM_WENDFOLD_CHECKIN_FAILED_TRY_AGAIN', $body);
		self::assertStringNotContainsString('Session::checkToken', $body);

		$gate = static function (int $userId, string $token, int $eventId): bool {
			return $userId > 0 && $token !== '' && $eventId > 0;
		};

		self::assertFalse($gate(0, 'tokenmember1234567890abcdef', 7));
		self::assertTrue($gate(42, 'tokenmember1234567890abcdef', 7));
	}

	/**
	 * The site language file defines the one new QR key used by the template.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteLanguageDefinesTheCheckinQrKey(): void
	{
		$siteIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);
		$template = $this->eventTemplate();

		self::assertStringContainsString('COM_WENDFOLD_EVENT_CHECKIN_QR=', $siteIni);
		self::assertGreaterThanOrEqual(2, substr_count($template, "Text::_('COM_WENDFOLD_EVENT_CHECKIN_QR')"));
		self::assertSame(1, substr_count($siteIni, 'COM_WENDFOLD_EVENT_CHECKIN_QR='));
	}

	/**
	 * Cache headers are set when a QR is rendered.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testQrRenderDisablesPageCaching(): void
	{
		$view = $this->viewSource();

		self::assertStringContainsString("setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true)", $view);
		self::assertStringContainsString("setHeader('Pragma', 'no-cache', true)", $view);
		self::assertStringContainsString('allowCache(false)', $view);
		self::assertStringContainsString('$this->checkinQr !== null', $view);
	}

	/**
	 * @param   string                $token      Opaque check-in token.
	 * @param   array<string, mixed>  $overrides  Field overrides.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function openEvent(string $token, array $overrides = []): object
	{
		return (object) array_merge(
			[
				'id'         => 7,
				'title'      => 'Saturday Ride',
				'status'     => 1,
				'event_date' => '2026-06-15',
				'end_date'   => '',
				'ksd_time'   => '18:00:00',
				'qr_token'   => $token,
			],
			$overrides
		);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function eventTemplate(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/event/default.php'
		);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function viewSource(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/View/Event/HtmlView.php'
		);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function helperSource(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Helper/EventCheckinQr.php'
		);
	}

	/**
	 * @param   string  $source  PHP source.
	 * @param   string  $name    Method name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function functionBody(string $source, string $name): string
	{
		$pattern = '/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{/';

		self::assertSame(1, preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE));

		$start = (int) $match[0][1] + strlen($match[0][0]);
		$depth = 1;
		$length = strlen($source);

		for ($i = $start; $i < $length; $i++)
		{
			$char = $source[$i];

			if ($char === '{')
			{
				$depth++;
			}
			elseif ($char === '}')
			{
				$depth--;

				if ($depth === 0)
				{
					return substr($source, $start, $i - $start);
				}
			}
		}

		self::fail('Unbalanced braces for ' . $name);
	}
}
