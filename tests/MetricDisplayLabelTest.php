<?php

/**
 * Wendfold - metric labels omit redundant units and pluralise counts.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricDisplayLabel;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricDisplayLabel.php';

/**
 * Metric card titles and amounts keep informative units and drop duplicated ones.
 *
 * @since  1.0.0
 */
final class MetricDisplayLabelTest extends TestCase
{
	/**
	 * Informative units stay; redundant units (equal or contained, singular/plural) drop.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testWithOptionalUnitKeepsInformativeAndDropsRedundant(): void
	{
		self::assertSame(
			'Distance (Miles)',
			MetricDisplayLabel::withOptionalUnit('Distance', 'Miles')
		);
		self::assertSame(
			'Distance (mi)',
			MetricDisplayLabel::withOptionalUnit('Distance', 'mi')
		);
		self::assertSame(
			'Distance (miles)',
			MetricDisplayLabel::withOptionalUnit('Distance', 'COM_WENDFOLD_UNIT_MILES')
		);
		self::assertSame(
			'Events attended',
			MetricDisplayLabel::withOptionalUnit('Events attended', 'Events')
		);
		self::assertSame(
			'Events attended',
			MetricDisplayLabel::withOptionalUnit('Events attended', 'COM_WENDFOLD_UNIT_EVENTS')
		);
		self::assertSame(
			'Points',
			MetricDisplayLabel::withOptionalUnit('Points', 'Points')
		);
		self::assertSame(
			'Points',
			MetricDisplayLabel::withOptionalUnit('Points', 'COM_WENDFOLD_UNIT_POINTS')
		);
		self::assertSame(
			'Volunteer hours',
			MetricDisplayLabel::withOptionalUnit('Volunteer hours', 'Hours')
		);
		self::assertSame(
			'Volunteer hours',
			MetricDisplayLabel::withOptionalUnit('Volunteer hours', 'COM_WENDFOLD_UNIT_HOURS')
		);
		self::assertSame(
			'Distance',
			MetricDisplayLabel::withOptionalUnit('Distance', '')
		);
	}

	/**
	 * Counted amounts use Text::plural singular and plural unit forms.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFormatAmountWithUnitSingularPluralAndRedundant(): void
	{
		self::assertSame(
			'1 event',
			MetricDisplayLabel::formatAmountWithUnit('1', 1.0, 'COM_WENDFOLD_UNIT_EVENTS', 'Distance')
		);
		self::assertSame(
			'2 events',
			MetricDisplayLabel::formatAmountWithUnit('2', 2.0, 'COM_WENDFOLD_UNIT_EVENTS', 'Distance')
		);
		self::assertSame(
			'1.0 mile',
			MetricDisplayLabel::formatAmountWithUnit('1.0', 1.0, 'COM_WENDFOLD_UNIT_MILES', 'Distance')
		);
		self::assertSame(
			'85.5 miles',
			MetricDisplayLabel::formatAmountWithUnit('85.5', 85.5, 'COM_WENDFOLD_UNIT_MILES', 'Distance')
		);
		self::assertSame(
			'1',
			MetricDisplayLabel::formatAmountWithUnit('1', 1.0, 'COM_WENDFOLD_UNIT_EVENTS', 'Events attended')
		);
		self::assertSame(
			'20',
			MetricDisplayLabel::formatAmountWithUnit('20', 20.0, 'COM_WENDFOLD_UNIT_POINTS', 'Points')
		);
		self::assertSame(
			'3',
			MetricDisplayLabel::formatAmountWithUnit('3', 3.0, 'COM_WENDFOLD_UNIT_HOURS', 'Volunteer hours')
		);
		self::assertSame(
			'point',
			MetricDisplayLabel::unitForCount('COM_WENDFOLD_UNIT_POINTS', 1.0)
		);
		self::assertSame(
			'points',
			MetricDisplayLabel::unitForCount('COM_WENDFOLD_UNIT_POINTS', 20.0)
		);
	}

	/**
	 * Redundancy detection is case-insensitive and understands simple plurals.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testIsRedundantUnitCaseAndPlural(): void
	{
		self::assertTrue(MetricDisplayLabel::isRedundantUnit('Events attended', 'EVENTS'));
		self::assertTrue(MetricDisplayLabel::isRedundantUnit('Volunteer hours', 'HOURS'));
		self::assertTrue(MetricDisplayLabel::isRedundantUnit('Points', 'points'));
		self::assertFalse(MetricDisplayLabel::isRedundantUnit('Distance', 'Miles'));
		self::assertFalse(MetricDisplayLabel::isRedundantUnit('Elevation', 'ft'));
	}

	/**
	 * Member-facing templates wire the helper and language keys exist.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberViewsUseHelperAndLanguageKeys(): void
	{
		$dashboard = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/dashboard/default.php'
		);
		$myactivities = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/myactivities/default.php'
		);
		$activitylog = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/activitylog/default.php'
		);
		$adminActivities = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/activities/default.php'
		);
		$siteIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$helper = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricDisplayLabel.php'
		);

		self::assertStringContainsString('MetricDisplayLabel::withOptionalUnit', $dashboard);
		self::assertStringContainsString('MetricDisplayLabel::formatAmountWithUnit', $myactivities);
		self::assertStringContainsString('MetricDisplayLabel::withOptionalUnit', $activitylog);
		self::assertStringContainsString('MetricDisplayLabel::formatAmountWithUnit', $adminActivities);
		self::assertStringContainsString('activity_title', $myactivities);
		self::assertStringContainsString('COM_WENDFOLD_MYACTIVITIES_TITLE', $myactivities);
		self::assertStringContainsString('COM_WENDFOLD_METRIC_LABEL_WITH_UNIT="%s (%s)"', $siteIni);
		self::assertStringContainsString('COM_WENDFOLD_UNIT_EVENTS_1="event"', $siteIni);
		self::assertStringContainsString('COM_WENDFOLD_UNIT_EVENTS_MORE="events"', $siteIni);
		self::assertStringContainsString('COM_WENDFOLD_UNIT_EVENTS_1="event"', $adminIni);
		self::assertStringContainsString('COM_WENDFOLD_UNIT_EVENTS_MORE="events"', $adminIni);
		self::assertStringContainsString("Text::plural(", $helper);
		self::assertStringContainsString("Text::sprintf('COM_WENDFOLD_METRIC_LABEL_WITH_UNIT'", $helper);
	}
}
