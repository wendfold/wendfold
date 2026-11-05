<?php

/**
 * Wendfold - Multi-day events show a saved Return time.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\EventReturnDisplay;

require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/EventReturnDisplay.php';

/**
 * Guards ride-or-multi-day Return display on the event page and dashboard.
 *
 * Multi-day saves force is_riding_event=0. Meetings and same-day non-ride
 * events must not show a stale Return time.
 *
 * @since  1.0.0
 */
final class MultiDayReturnDisplayTest extends TestCase
{
	/**
	 * A meeting with a stale return time must not show Return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingWithStaleReturnShowsNoReturn(): void
	{
		self::assertFalse(
			EventReturnDisplay::shouldShow(false, '', '', '2026-11-08')
		);
		self::assertFalse(
			EventReturnDisplay::shouldShow(false, '2026-11-08', '2026-11-08', '2026-11-08')
		);

		$event = $this->eventTemplate();
		$dashboard = $this->dashboardTemplate();

		self::assertStringContainsString('EventReturnDisplay::shouldShow', $event);
		self::assertStringContainsString(
			'$showReturn && ($ksdName || $ksdCity || $ksdState || !empty($item->ksd_time))',
			$event
		);
		self::assertStringNotContainsString(
			'if ($ksdName || $ksdCity || $ksdState || !empty($item->ksd_time))',
			$event
		);
		self::assertStringContainsString('EventReturnDisplay::shouldShow', $dashboard);
		self::assertStringContainsString('$showReturn && !empty($rsvp->ksd_time)', $dashboard);
	}

	/**
	 * A multi-day event with is_riding_event=0 still shows Return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMultiDayEventWithRidingClearedShowsReturn(): void
	{
		self::assertTrue(
			EventReturnDisplay::shouldShow(false, '2026-11-07', '2026-11-09', '2026-11-07')
		);
		self::assertTrue(
			EventReturnDisplay::shouldShow(false, '', '2026-11-09', '2026-11-07')
		);

		$event = $this->eventTemplate();

		self::assertStringContainsString(
			'$showReturn && ($ksdName || $ksdCity || $ksdState || !empty($item->ksd_time))',
			$event
		);
		self::assertMatchesRegularExpression(
			'/\$ksdTimeLabel\s*=\s*!empty\(\$item->ksd_time\)\s*\?\s*date\(\'g:i A\',\s*strtotime\(\(string\)\s*\$item->ksd_time\)\)\s*:\s*\'\'/',
			$event
		);
		self::assertStringContainsString(
			"htmlspecialchars(\$ksdTimeLabel, ENT_QUOTES, 'UTF-8')",
			$event
		);
	}

	/**
	 * A same-day ride shows Return when return data is present.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSameDayRideShowsReturn(): void
	{
		self::assertTrue(
			EventReturnDisplay::shouldShow(true, '', '', '2026-11-08')
		);
		self::assertTrue(
			EventReturnDisplay::shouldShow(true, '2026-11-08', '2026-11-08', '2026-11-08')
		);

		$event = $this->eventTemplate();

		self::assertStringContainsString(
			"\$showRide = !empty(\$item->is_riding_event);",
			$event
		);
		self::assertStringContainsString(
			'$showRide && isset($item->distance_credit)',
			$event
		);
		self::assertStringContainsString(
			'$showReturn && ($ksdName || $ksdCity || $ksdState || !empty($item->ksd_time))',
			$event
		);
	}

	/**
	 * Dashboard selects the fields the ride-or-multi-day gate needs and labels times.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDashboardSelectsReturnFieldsAndLabelsDepartureAndReturn(): void
	{
		$model = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/DashboardModel.php'
		);
		$template = $this->dashboardTemplate();

		self::assertStringContainsString("quoteName('e.ksu_time')", $model);
		self::assertStringContainsString("quoteName('e.ksd_time')", $model);
		self::assertStringContainsString("quoteName('e.start_date')", $model);
		self::assertStringContainsString("quoteName('e.end_date')", $model);
		self::assertStringContainsString("quoteName('e.is_riding_event')", $model);

		self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSU')", $template);
		self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSD')", $template);
		self::assertStringContainsString('EventReturnDisplay::shouldShow', $template);
		self::assertStringContainsString('$departureLine', $template);
		self::assertStringContainsString('$returnLine', $template);
		self::assertStringContainsString('$showReturn && !empty($rsvp->ksd_time)', $template);
		self::assertStringNotContainsString(
			"\$meta .= ' - ' . date('g:i A', strtotime((string) \$rsvp->ksu_time))",
			$template
		);
		self::assertStringContainsString(
			"htmlspecialchars(\$departureLine, ENT_QUOTES, 'UTF-8')",
			$template
		);
		self::assertStringContainsString(
			"htmlspecialchars(\$returnLine, ENT_QUOTES, 'UTF-8')",
			$template
		);
	}

	/**
	 * @return  string
	 */
	private function eventTemplate(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/event/default.php'
		);
	}

	/**
	 * @return  string
	 */
	private function dashboardTemplate(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/dashboard/default.php'
		);
	}
}
