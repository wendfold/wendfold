<?php

/**
 * Wendfold - site router SEF round trips.
 *
 * parse(build()) must return the same view and id, and parse() must remove
 * every segment it read. Joomla returns 404 when any segment is left behind.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventAlias;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Site\Service\Router;
use Wendfold\Tests\Support\DatabaseStub;
use Wendfold\Tests\Support\MemberUser;
use Wendfold\Tests\Support\SiteApplication;
use Wendfold\Tests\Support\SiteMenu;

/**
 * Locks build() and parse() for stored event aliases.
 *
 * @since  1.0.0
 */
final class RouterRoundTripTest extends TestCase
{
	private const SLUG_JUNE = 'fredericksburg-wine-wheels';

	private const SLUG_JULY = 'fredericksburg-wine-wheels-2';

	private const SLUG_LUNCH = 'hill-country-lunch';

	private const SLUG_TRIP = 'overnight-hill-country';

	private const SLUG_BREAKFAST = 'foo';

	private const SLUG_BREAKFAST_DUP = 'foo-2';

	/**
	 * Menu item ids used by the fixture.
	 *
	 * @var  array<string, integer>
	 */
	private const MENUS = [
		'events' => 101,
		'dashboard' => 201,
		'profileedit' => 202,
		'myrsvps' => 203,
		'myactivities' => 206,
		'awards' => 204,
		'officers' => 205,
		'checkin' => 301,
	];

	/**
	 * Application for the router under test.
	 *
	 * @var    SiteApplication|null
	 * @since  1.0.0
	 */
	private $app;

	/**
	 * Menu for the router under test.
	 *
	 * @var    SiteMenu|null
	 * @since  1.0.0
	 */
	private $menu;

	/**
	 * Round-trip cases.
	 *
	 * Each row is: menu views, query, whether the active menu is set,
	 * segments build() must return, variables parse() must return.
	 * The request input never carries Itemid. Joomla sets the active
	 * menu before the component parser runs.
	 *
	 * @return  array<string, array{0: array, 1: array, 2: boolean, 3: array, 4: array}>
	 *
	 * @since   1.0.0
	 */
	public static function roundTripProvider(): array
	{
		$cases = [];

		self::add(
			$cases,
			'events list on the events menu',
			['events'],
			['view' => 'events'],
			true,
			[],
			['view' => 'events']
		);
		self::add(
			$cases,
			'events list on the events menu before Itemid is visible',
			['events'],
			['view' => 'events'],
			false,
			[],
			[]
		);
		self::add(
			$cases,
			'events list with no menu',
			[],
			['view' => 'events'],
			false,
			['events'],
			['view' => 'events']
		);

		self::add(
			$cases,
			'event detail by alias on the events menu',
			['events'],
			['view' => 'event', 'id' => 12],
			true,
			[self::SLUG_JUNE],
			['view' => 'event', 'id' => 12]
		);
		self::add(
			$cases,
			'event detail by alias before Itemid is visible',
			['events'],
			['view' => 'event', 'id' => 12],
			false,
			[self::SLUG_JUNE],
			['view' => 'event', 'id' => 12]
		);
		self::add(
			$cases,
			'event detail by alias with no menu',
			[],
			['view' => 'event', 'id' => 12],
			false,
			['events', self::SLUG_JUNE],
			['view' => 'event', 'id' => 12]
		);

		self::add(
			$cases,
			'second event that shares a title on the events menu',
			['events'],
			['view' => 'event', 'id' => 13],
			true,
			[self::SLUG_JULY],
			['view' => 'event', 'id' => 13]
		);
		self::add(
			$cases,
			'second event that shares a title with no menu',
			[],
			['view' => 'event', 'id' => 13],
			false,
			['events', self::SLUG_JULY],
			['view' => 'event', 'id' => 13]
		);

		self::add(
			$cases,
			'child event detail on the events menu',
			['events'],
			['view' => 'event', 'id' => 20],
			true,
			[self::SLUG_LUNCH],
			['view' => 'event', 'id' => 20]
		);
		self::add(
			$cases,
			'child event detail before Itemid is visible',
			['events'],
			['view' => 'event', 'id' => 20],
			false,
			[self::SLUG_LUNCH],
			['view' => 'event', 'id' => 20]
		);
		self::add(
			$cases,
			'child event detail with no menu',
			[],
			['view' => 'event', 'id' => 20],
			false,
			['events', self::SLUG_LUNCH],
			['view' => 'event', 'id' => 20]
		);

		self::add(
			$cases,
			'trip detail on the events menu',
			['events'],
			['view' => 'trip', 'id' => 30],
			true,
			['trip', self::SLUG_TRIP],
			['view' => 'trip', 'id' => 30]
		);
		self::add(
			$cases,
			'trip detail before Itemid is visible',
			['events'],
			['view' => 'trip', 'id' => 30],
			false,
			['trip', self::SLUG_TRIP],
			['view' => 'trip', 'id' => 30]
		);
		self::add(
			$cases,
			'trip detail with no menu',
			[],
			['view' => 'trip', 'id' => 30],
			false,
			['events', 'trip', self::SLUG_TRIP],
			['view' => 'trip', 'id' => 30]
		);

		self::add(
			$cases,
			'road captain attendance on the events menu',
			['events'],
			['view' => 'rcattendance', 'event_id' => 12],
			true,
			['rcattendance', self::SLUG_JUNE],
			['view' => 'rcattendance', 'event_id' => 12]
		);
		self::add(
			$cases,
			'road captain attendance before Itemid is visible',
			['events'],
			['view' => 'rcattendance', 'event_id' => 12],
			false,
			['rcattendance', self::SLUG_JUNE],
			['view' => 'rcattendance', 'event_id' => 12]
		);
		self::add(
			$cases,
			'road captain attendance with no menu',
			[],
			['view' => 'rcattendance', 'event_id' => 12],
			false,
			['events', 'rcattendance', self::SLUG_JUNE],
			['view' => 'rcattendance', 'event_id' => 12]
		);

		self::add(
			$cases,
			'meeting attendance on the events menu',
			['events'],
			['view' => 'meetingattendance', 'event_id' => 12],
			true,
			['meetingattendance', self::SLUG_JUNE],
			['view' => 'meetingattendance', 'event_id' => 12]
		);
		self::add(
			$cases,
			'meeting attendance before Itemid is visible',
			['events'],
			['view' => 'meetingattendance', 'event_id' => 12],
			false,
			['meetingattendance', self::SLUG_JUNE],
			['view' => 'meetingattendance', 'event_id' => 12]
		);
		self::add(
			$cases,
			'meeting attendance with no menu',
			[],
			['view' => 'meetingattendance', 'event_id' => 12],
			false,
			['events', 'meetingattendance', self::SLUG_JUNE],
			['view' => 'meetingattendance', 'event_id' => 12]
		);

		self::add(
			$cases,
			'volunteer board on the events menu',
			['events'],
			['view' => 'volunteerboard', 'event_id' => 20],
			true,
			['volunteerboard', self::SLUG_LUNCH],
			['view' => 'volunteerboard', 'event_id' => 20]
		);
		self::add(
			$cases,
			'volunteer board before Itemid is visible',
			['events'],
			['view' => 'volunteerboard', 'event_id' => 20],
			false,
			['volunteerboard', self::SLUG_LUNCH],
			['view' => 'volunteerboard', 'event_id' => 20]
		);
		self::add(
			$cases,
			'volunteer board with no menu',
			[],
			['view' => 'volunteerboard', 'event_id' => 20],
			false,
			['events', 'volunteerboard', self::SLUG_LUNCH],
			['view' => 'volunteerboard', 'event_id' => 20]
		);

		self::add(
			$cases,
			'event detail by numeric id on the events menu',
			['events'],
			['view' => 'event', 'id' => 77],
			true,
			['77'],
			['view' => 'event', 'id' => 77]
		);
		self::add(
			$cases,
			'event detail by numeric id before Itemid is visible',
			['events'],
			['view' => 'event', 'id' => 77],
			false,
			['77'],
			['view' => 'event', 'id' => 77]
		);
		self::add(
			$cases,
			'event detail by numeric id with no menu',
			[],
			['view' => 'event', 'id' => 77],
			false,
			['events', '77'],
			['view' => 'event', 'id' => 77]
		);

		self::add(
			$cases,
			'same title keeps the stored alias foo',
			['events'],
			['view' => 'event', 'id' => 40],
			true,
			[self::SLUG_BREAKFAST],
			['view' => 'event', 'id' => 40]
		);
		self::add(
			$cases,
			'same title keeps the stored alias foo-2',
			['events'],
			['view' => 'event', 'id' => 41],
			true,
			[self::SLUG_BREAKFAST_DUP],
			['view' => 'event', 'id' => 41]
		);
		self::add(
			$cases,
			'same title foo-2 with no menu',
			[],
			['view' => 'event', 'id' => 41],
			false,
			['events', self::SLUG_BREAKFAST_DUP],
			['view' => 'event', 'id' => 41]
		);

		self::add(
			$cases,
			'dashboard on its menu',
			['dashboard'],
			['view' => 'dashboard'],
			true,
			[],
			['view' => 'dashboard']
		);
		self::add(
			$cases,
			'dashboard on its menu before Itemid is visible',
			['dashboard'],
			['view' => 'dashboard'],
			false,
			[],
			[]
		);
		self::add(
			$cases,
			'dashboard with no menu',
			[],
			['view' => 'dashboard'],
			false,
			['dashboard'],
			['view' => 'dashboard']
		);

		self::add(
			$cases,
			'profile edit under the dashboard menu',
			['dashboard'],
			['view' => 'profileedit'],
			true,
			['profileedit'],
			['view' => 'profileedit']
		);
		self::add(
			$cases,
			'profile edit under the dashboard menu before Itemid is visible',
			['dashboard'],
			['view' => 'profileedit'],
			false,
			['profileedit'],
			['view' => 'profileedit']
		);
		self::add(
			$cases,
			'profile edit with no menu',
			[],
			['view' => 'profileedit'],
			false,
			['dashboard', 'profileedit'],
			['view' => 'profileedit']
		);
		self::add(
			$cases,
			'profile edit on its own menu',
			['profileedit'],
			['view' => 'profileedit'],
			true,
			[],
			[]
		);
		self::add(
			$cases,
			'profile edit on its own menu before Itemid is visible',
			['profileedit'],
			['view' => 'profileedit'],
			false,
			[],
			[]
		);

		self::add(
			$cases,
			'my RSVPs under the dashboard menu',
			['dashboard'],
			['view' => 'myrsvps'],
			true,
			['myrsvps'],
			['view' => 'myrsvps']
		);
		self::add(
			$cases,
			'my RSVPs under the dashboard menu before Itemid is visible',
			['dashboard'],
			['view' => 'myrsvps'],
			false,
			['myrsvps'],
			['view' => 'myrsvps']
		);
		self::add(
			$cases,
			'my RSVPs with no menu',
			[],
			['view' => 'myrsvps'],
			false,
			['myrsvps'],
			['view' => 'myrsvps']
		);
		self::add(
			$cases,
			'my RSVPs on their own menu',
			['myrsvps'],
			['view' => 'myrsvps'],
			true,
			[],
			[]
		);

		self::add(
			$cases,
			'my activities under the dashboard menu',
			['dashboard'],
			['view' => 'myactivities'],
			true,
			['myactivities'],
			['view' => 'myactivities']
		);
		self::add(
			$cases,
			'my activities under the dashboard menu before Itemid is visible',
			['dashboard'],
			['view' => 'myactivities'],
			false,
			['myactivities'],
			['view' => 'myactivities']
		);
		self::add(
			$cases,
			'my activities with no menu',
			[],
			['view' => 'myactivities'],
			false,
			['myactivities'],
			['view' => 'myactivities']
		);
		self::add(
			$cases,
			'my activities on their own menu',
			['myactivities'],
			['view' => 'myactivities'],
			true,
			[],
			[]
		);

		self::add(
			$cases,
			'awards on their menu',
			['awards'],
			['view' => 'awards'],
			true,
			[],
			[]
		);
		self::add(
			$cases,
			'awards on their menu before Itemid is visible',
			['awards'],
			['view' => 'awards'],
			false,
			[],
			[]
		);
		self::add(
			$cases,
			'awards with no menu',
			[],
			['view' => 'awards'],
			false,
			['awards'],
			['view' => 'awards']
		);

		self::add(
			$cases,
			'officers on their menu',
			['officers'],
			['view' => 'officers'],
			true,
			[],
			[]
		);
		self::add(
			$cases,
			'officers with no menu',
			[],
			['view' => 'officers'],
			false,
			['officers'],
			['view' => 'officers']
		);

		self::add(
			$cases,
			'check-in on its menu',
			['checkin'],
			['view' => 'checkin', 'token' => 'ride-day-token'],
			true,
			['ride-day-token'],
			['view' => 'checkin', 'token' => 'ride-day-token']
		);
		self::add(
			$cases,
			'check-in with no menu',
			[],
			['view' => 'checkin', 'token' => 'ride-day-token'],
			false,
			['checkin', 'ride-day-token'],
			['view' => 'checkin', 'token' => 'ride-day-token']
		);
		self::add(
			$cases,
			'check-in token that starts with digits',
			['checkin'],
			['view' => 'checkin', 'token' => '86-ride-day'],
			true,
			['86-ride-day'],
			['view' => 'checkin', 'token' => '86-ride-day']
		);
		self::add(
			$cases,
			'numeric check-in token on its menu',
			['checkin'],
			['view' => 'checkin', 'token' => '12345'],
			true,
			['12345'],
			['view' => 'checkin', 'token' => '12345']
		);

		self::add(
			$cases,
			'alias that starts with digits on the events menu',
			['events'],
			['view' => 'event', 'id' => 70],
			true,
			['2026-toy-run'],
			['view' => 'event', 'id' => 70]
		);
		self::add(
			$cases,
			'event whose id is the leading number of another alias',
			['events'],
			['view' => 'event', 'id' => 2026],
			true,
			['canyon-cruise'],
			['view' => 'event', 'id' => 2026]
		);
		self::add(
			$cases,
			'event whose alias starts with a reserved word',
			['events'],
			['view' => 'event', 'id' => 80],
			true,
			['trip-80'],
			['view' => 'event', 'id' => 80]
		);
		self::add(
			$cases,
			'trip view for an alias that starts with the view name',
			['events'],
			['view' => 'trip', 'id' => 80],
			true,
			['trip', 'trip-80'],
			['view' => 'trip', 'id' => 80]
		);

		return $cases;
	}

	/**
	 * parse(build()) returns the same view and id and leaves no segments.
	 *
	 * @param   array    $menus              Menu views available on the site.
	 * @param   array    $query              Query passed to preprocess() and build().
	 * @param   boolean  $expose             Whether the active menu is set for parse().
	 * @param   array    $expectedSegments   Segments build() must return.
	 * @param   array    $expectedVars       Variables parse() must return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('roundTripProvider')]
	public function testParseBuildRoundTripLeavesNoSegments(
		array $menus,
		array $query,
		bool $expose,
		array $expectedSegments,
		array $expectedVars
	): void {
		$router = $this->makeRouter($menus);
		$prepared = $router->preprocess($query);
		$segments = $router->build($prepared);

		if ($menus === [])
		{
			self::assertArrayNotHasKey('Itemid', $prepared);
		}
		else
		{
			self::assertNotEmpty($prepared['Itemid'] ?? null);
		}

		foreach (['view', 'id', 'event_id', 'token'] as $key)
		{
			if (array_key_exists($key, $query))
			{
				self::assertArrayNotHasKey($key, $prepared);
			}
		}

		self::assertSame($expectedSegments, $segments);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));

		if ($expose)
		{
			$this->activateMenu((int) ($prepared['Itemid'] ?? 0));
		}

		$vars = $router->parse($segments);

		self::assertSame($expectedVars, $vars);
		self::assertSame([], $segments);
	}

	/**
	 * A hyphenated or glued segment that is not a stored alias is a missing page.
	 *
	 * Bare digits still open by id. Only an exact stored alias opens that event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNonAliasHyphenatedSegmentIsMissing(): void
	{
		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		$segments = ['12-some-title'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$router = $this->makeRouter([]);
		$segments = ['events', '12-some-title'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$matched = $this->makeRouter(
			['events'],
			[
				$this->eventRow(12, 'Some Title', 1, 'public-ride'),
			]
		);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($matched, '12-some-title', 0);
		$this->assertParsedEvent($matched, 'public-ride', 12);
		$this->assertParsedEvent($matched, '12', 12);

		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		$segments = ['12some'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertSame([], $segments);
	}

	/**
	 * A segment the router does not understand stays in the list.
	 *
	 * Joomla then 404s. Clearing the whole list would hide a bad URL.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnrecognisedTrailingSegmentStaysUnconsumed(): void
	{
		$router = $this->makeRouter([]);
		$segments = ['events', self::SLUG_LUNCH, 'not-a-page'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 20], $vars);
		self::assertSame(['not-a-page'], $segments);

		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		$segments = [self::SLUG_LUNCH, 'not-a-page'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 20], $vars);
		self::assertSame(['not-a-page'], $segments);
	}

	/**
	 * An unknown member path stays in the list so Joomla can 404.
	 *
	 * A known page under the dashboard menu is still consumed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownDashboardSegmentStaysUnconsumed(): void
	{
		$router = $this->makeRouter(['dashboard']);
		$this->activateMenu(self::MENUS['dashboard']);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));
		$segments = ['not-a-page'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'dashboard'], $vars);
		self::assertSame(['not-a-page'], $segments);

		$segments = ['86-not-a-page'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'dashboard'], $vars);
		self::assertSame(['86-not-a-page'], $segments);

		$router = $this->makeRouter([]);
		$segments = ['dashboard', 'not-a-page'];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'dashboard'], $vars);
		self::assertSame(['not-a-page'], $segments);
	}

	/**
	 * Blank segments are dropped, and the alias after them is still consumed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBlankSegmentsAreRemovedAndTheAliasIsConsumed(): void
	{
		$router = $this->makeRouter([]);
		$segments = ['', 'events', self::SLUG_LUNCH];

		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 20], $vars);
		self::assertSame([], $segments);
	}

	/**
	 * A GPX download is a task URL, so build() adds no path segments.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGpxDownloadStaysAQueryTaskAndAddsNoSegments(): void
	{
		$router = $this->makeRouter(['events']);
		$query = ['task' => 'event.gpx', 'id' => 12];
		$prepared = $router->preprocess($query);
		$segments = $router->build($prepared);

		self::assertArrayNotHasKey('Itemid', $prepared);
		self::assertSame('event.gpx', $prepared['task']);
		self::assertSame(12, $prepared['id']);
		self::assertSame([], $segments);

		$vars = $router->parse($segments);

		self::assertSame([], $vars);
		self::assertSame([], $segments);
	}

	/**
	 * Stored aliases for reserved words, digit-led names, and id-only links.
	 *
	 * The address is the value on the row. Same-title twins, a reserved
	 * word plus its id, and a digit-led alias each round-trip as stored.
	 *
	 * @return  array<string, array{0: array, 1: boolean, 2: array, 3: array, 4: array}>
	 *
	 * @since   1.0.0
	 */
	public static function reservedAliasRoundTripProvider(): array
	{
		$words = [
			'trip' => [80, 'trip-80', 'id'],
			'rcattendance' => [81, 'rcattendance-81', 'event_id'],
			'meetingattendance' => [82, 'meetingattendance-82', 'event_id'],
			'volunteerboard' => [83, 'volunteerboard-83', 'event_id'],
		];

		$contexts = [
			'on the events menu' => [['events'], true],
			'before the active menu item is set' => [['events'], false],
			'with no menu' => [[], false],
		];

		$cases = [];

		foreach ($words as $word => [$id, $slug, $key])
		{
			foreach ($contexts as $label => [$menus, $expose])
			{
				$prefix = $menus === [] ? ['events'] : [];

				$cases['stored alias ' . $slug . ' ' . $label] = [
					$menus,
					$expose,
					['view' => 'event', 'id' => $id],
					array_merge($prefix, [$slug]),
					['view' => 'event', 'id' => $id],
				];

				$cases[$word . ' view for stored alias ' . $slug . ' ' . $label] = [
					$menus,
					$expose,
					['view' => $word, $key => $id],
					array_merge($prefix, [$word, $slug]),
					['view' => $word, $key => $id],
				];
			}
		}

		return $cases;
	}

	/**
	 * Digit-led aliases, an empty alias, an ordinary alias, and same-title twins.
	 *
	 * @return  array<string, array{0: array, 1: boolean, 2: array, 3: array, 4: array}>
	 *
	 * @since   1.0.0
	 */
	public static function storedAliasEdgeRoundTripProvider(): array
	{
		$contexts = [
			'on the events menu' => [['events'], true],
			'before the active menu item is set' => [['events'], false],
			'with no menu' => [[], false],
		];

		$rows = [
			'digit-led alias' => [70, '2026-toy-run'],
			'event whose id is the leading number of another alias' => [2026, 'canyon-cruise'],
			'ordinary alias' => [20, 'hill-country-lunch'],
			'empty alias uses the id' => [77, '77'],
			'same-title twin foo' => [40, 'foo'],
			'same-title twin foo-2' => [41, 'foo-2'],
		];

		$cases = [];

		foreach ($rows as $name => [$id, $slug])
		{
			foreach ($contexts as $label => [$menus, $expose])
			{
				$prefix = $menus === [] ? ['events'] : [];
				$cases[$name . ' ' . $label] = [
					$menus,
					$expose,
					['view' => 'event', 'id' => $id],
					array_merge($prefix, [$slug]),
					['view' => 'event', 'id' => $id],
				];
			}
		}

		return $cases;
	}

	/**
	 * parse(build()) keeps the view, the id, and an empty segment list.
	 *
	 * These rows store the alias. The router does not rebuild it from the title.
	 *
	 * @param   array    $menus              Menu views available on the site.
	 * @param   boolean  $expose             Whether the active menu is set for parse().
	 * @param   array    $query              Query passed to preprocess() and build().
	 * @param   array    $expectedSegments   Segments build() must return.
	 * @param   array    $expectedVars       Variables parse() must return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('reservedAliasRoundTripProvider')]
	#[DataProvider('storedAliasEdgeRoundTripProvider')]
	public function testEdgeCaseRoundTripLeavesNoSegments(
		array $menus,
		bool $expose,
		array $query,
		array $expectedSegments,
		array $expectedVars
	): void {
		$this->assertRoundTrip($menus, $expose, $query, $expectedSegments, $expectedVars);
	}

	/**
	 * A bare reserved word is that view, even when an event alias is the same word.
	 *
	 * View parsing runs before alias lookup. The following segment is still
	 * the event alias.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBareReservedWordStaysTheView(): void
	{
		$words = [
			'trip' => 'id',
			'rcattendance' => 'event_id',
			'meetingattendance' => 'event_id',
			'volunteerboard' => 'event_id',
		];

		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));

		foreach ($words as $word => $key)
		{
			$segments = [$word];
			$vars = $router->parse($segments);

			self::assertSame(['view' => $word, $key => 0], $vars);
			self::assertSame([], $segments);
		}

		$router = $this->makeRouter([]);

		foreach ($words as $word => $key)
		{
			$segments = [$word];
			$vars = $router->parse($segments);

			self::assertSame(['view' => $word, $key => 0], $vars);
			self::assertSame([], $segments);

			$segments = ['events', $word];
			$vars = $router->parse($segments);

			self::assertSame(['view' => $word, $key => 0], $vars);
			self::assertSame([], $segments);
		}

		$router = $this->makeRouter(
			['events'],
			[
				$this->eventRow(15, 'Trip', 1, 'trip'),
				$this->eventRow(16, 'Hill Country Lunch', 1, 'hill-country-lunch'),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		$segments = ['TRIP'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 0], $vars);
		self::assertNotSame(15, $vars['id']);
		self::assertSame([], $segments);

		$segments = ['trip', 'hill-country-lunch'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 16], $vars);
		self::assertSame([], $segments);
	}









	/**
	 * Two events with one title keep the alias each row stores.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSameTitleAliasesResolveToThemselves(): void
	{
		$rows = [
			$this->eventRow(40, 'Breakfast Ride', 1, 'foo', AudienceHelper::SCOPE_ALL_VISITORS),
			$this->eventRow(41, 'Breakfast Ride', 1, 'foo-2', AudienceHelper::SCOPE_ALL_VISITORS),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 40, ['foo'], 40);
		$this->assertBuiltEvent($router, 41, ['foo-2'], 41);

		$segments = ['foo'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 40], $vars);
		self::assertSame([], $segments);

		$segments = ['foo-2'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 41], $vars);
		self::assertSame([], $segments);

		$segments = ['Foo'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertNotSame(40, $vars['id']);
		self::assertNotSame(41, $vars['id']);
		self::assertSame([], $segments);
	}

	/**
	 * A generated alias that was shortened on a hyphen is that address.
	 *
	 * The full title string is not an address. A second event that received
	 * the next suffix still opens itself through its stored alias.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTruncatedAliasRoundTripsWithoutALongerSlug(): void
	{
		require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventAlias.php';

		$token = str_repeat('a', 50);
		$title = implode('-', array_fill(0, 12, $token));
		$first = $this->generatedAlias($title, []);
		$second = $this->generatedAlias($title, [$first]);

		self::assertNotSame($title, $first);
		self::assertLessThanOrEqual(400, mb_strlen($first, 'UTF-8'));
		self::assertSame($first . '-2', $second);

		$rows = [
			$this->eventRow(8, $title, 1, $first),
			$this->eventRow(9, $title, 1, $second),
			$this->eventRow(1, $title, 0, $first . '-held'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 8, [$first], 8);
		$this->assertBuiltEvent($router, 9, [$second], 9);
		$this->assertParsedEvent($router, $title, 0);
		$this->assertParsedEvent($router, $first . '-' . $token, 0);

		$yearTitle = '2026-' . $title;
		$yearAlias = $this->generatedAlias($yearTitle, []);
		$yearRouter = $this->makeRouter(
			['events'],
			[
				$this->eventRow(2026, 'Canyon Cruise', 1, 'canyon-cruise'),
				$this->eventRow(70, $yearTitle, 1, $yearAlias),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		self::assertStringStartsWith('2026-', $yearAlias);
		self::assertNotSame($yearTitle, $yearAlias);
		$this->assertBuiltEvent($yearRouter, 70, [$yearAlias], 70);
		$this->assertParsedEvent($yearRouter, '2026', 2026);
		$this->assertParsedEvent($yearRouter, $yearTitle, 0);
		$this->assertParsedEvent($yearRouter, $yearAlias, 70);
	}

	/**
	 * Attendance views resolve archived and unpublished aliases; other views do not.
	 *
	 * build() and parse() round-trip the same id for published, archived, and
	 * unpublished attendance links. Trashed stays 0. Event, trip, and
	 * volunteerboard stay published-only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAttendanceViewsResolveArchivedAndUnpublished(): void
	{
		$rows = [
			$this->eventRow(10, 'Archived Meeting', 2, 'archived-meeting'),
			$this->eventRow(11, 'Unpublished Meeting', 0, 'unpublished-meeting'),
			$this->eventRow(12, 'Trashed Meeting', -2, 'trashed-meeting'),
			$this->eventRow(13, 'Published Meeting', 1, 'published-meeting'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		foreach (['meetingattendance', 'rcattendance'] as $view)
		{
			$this->assertBuiltEvent($router, 13, [$view, 'published-meeting'], 13, $view, 'event_id');
			$this->assertBuiltEvent($router, 10, [$view, 'archived-meeting'], 10, $view, 'event_id');
			$this->assertBuiltEvent($router, 11, [$view, 'unpublished-meeting'], 11, $view, 'event_id');

			$attendanceSegments = [
				['trashed-meeting', 0],
				['12', 0],
				['10', 10],
				['11', 11],
			];

			foreach ($attendanceSegments as [$segment, $id])
			{
				$segments = [$view, $segment];
				$vars = $router->parse($segments);

				self::assertSame(['view' => $view, 'event_id' => $id], $vars, $view . '/' . $segment);
				self::assertSame([], $segments, $view . '/' . $segment);
			}
		}

		$this->assertParsedEvent($router, 'published-meeting', 13);
		$this->assertParsedEvent($router, 'archived-meeting', 0);
		$this->assertParsedEvent($router, 'unpublished-meeting', 0);
		$this->assertParsedEvent($router, 'trashed-meeting', 0);
		$this->assertParsedEvent($router, '10', 0);
		$this->assertParsedEvent($router, '11', 0);
		$this->assertParsedEvent($router, '12', 0);
		$this->assertParsedEvent($router, '13', 13);

		$otherViews = [
			['trip', 'id'],
			['volunteerboard', 'event_id'],
		];

		foreach ($otherViews as [$view, $key])
		{
			foreach ([10, 11, 12] as $eventId)
			{
				$query = ['view' => $view, $key => $eventId];
				$prepared = $router->preprocess($query);
				$built = $router->build($prepared);
				$vars = $router->parse($built);

				self::assertSame(0, $vars[$key], $view . ' event ' . $eventId);
				self::assertSame($view, $vars['view'], $view . ' event ' . $eventId);
				self::assertSame([], $built, $view . ' event ' . $eventId);
			}

			$this->assertBuiltEvent($router, 13, [$view, 'published-meeting'], 13, $view, $key);
		}
	}

	/**
	 * An unpublished or trashed alias is a missing page and is not given to a twin.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnpublishedOrTrashedAliasNeverOpensATwin(): void
	{
		$rows = [
			$this->eventRow(1, 'Chapter Ride', 0, 'foo'),
			$this->eventRow(2, 'Chapter Ride', 1, 'foo-2'),
			$this->eventRow(3, 'Chapter Ride', -2, 'bar'),
			$this->eventRow(4, 'Chapter Ride', 1, 'bar-2'),
			$this->eventRow(12, 'Public Ride', 1, 'public-ride'),
			$this->eventRow(99, 'Hidden Legacy', 0, '12-some-title'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		foreach (['foo' => 2, 'bar' => 4] as $hidden => $twinId)
		{
			$segments = [$hidden];
			$vars = $router->parse($segments);

			self::assertSame(['view' => 'event', 'id' => 0], $vars, $hidden);
			self::assertNotSame($twinId, $vars['id']);
			self::assertSame([], $segments);
		}

		$this->assertBuiltEvent($router, 2, ['foo-2'], 2);
		$this->assertBuiltEvent($router, 4, ['bar-2'], 4);

		$query = ['view' => 'event', 'id' => 1];
		$prepared = $router->preprocess($query);
		$built = $router->build($prepared);

		self::assertSame(['foo'], $built);

		$vars = $router->parse($built);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertNotSame(2, $vars['id']);

		$segments = ['12-some-title'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertNotSame(12, $vars['id']);
		self::assertSame([], $segments);

		$segments = ['12'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 12], $vars);
		self::assertSame([], $segments);
	}

	/**
	 * An id fallback opens only a published event the visitor may see.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testIdFallbackRespectsVisibility(): void
	{
		$managed = $this->eventRow(51, 'Manager Only', 1, 'manager-only', AudienceHelper::SCOPE_OFFICERS);

		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Stub mirrors the volunteer_manager_id column.
		$managed->volunteer_manager_id = 9;
		$rows = [
			$this->eventRow(12, 'Public Ride', 1, 'public-ride'),
			$this->eventRow(50, 'Officers Briefing', 1, 'officers-briefing', AudienceHelper::SCOPE_OFFICERS),
			$this->eventRow(70, '2026 Toy Run', 1, '2026-toy-run'),
			$this->eventRow(2026, 'Canyon Cruise', 1, 'canyon-cruise'),
			$this->eventRow(91, 'Closed Workshop', 0, 'closed-workshop'),
			$this->eventRow(96, 'Trashed Ride', -2, 'trashed-ride'),
			$managed,
		];

		$guest = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($guest, '12', 12);
		$this->assertParsedEvent($guest, '12-some-title', 0);
		$this->assertParsedEvent($guest, 'public-ride', 12);
		$this->assertParsedEvent($guest, '2026-toy-run', 70);
		$this->assertParsedEvent($guest, '2026', 2026);
		$this->assertParsedEvent($guest, '2026-some-title', 0);
		$this->assertParsedEvent($guest, '2026-toy-run-2026-10-03', 0);
		$this->assertParsedEvent($guest, 'fredericksburg-wine-wheels-2026-06-14', 0);
		$this->assertParsedEvent($guest, 'officers-briefing', 0);
		$this->assertParsedEvent($guest, '50', 0);
		$this->assertParsedEvent($guest, '50-old-name', 0);
		$this->assertParsedEvent($guest, 'closed-workshop', 0);
		$this->assertParsedEvent($guest, '91', 0);
		$this->assertParsedEvent($guest, '91-closed-workshop', 0);
		$this->assertParsedEvent($guest, 'trashed-ride', 0);
		$this->assertParsedEvent($guest, '96', 0);
		$this->assertParsedEvent($guest, 'manager-only', 0);
		$this->assertParsedEvent($guest, '4040-not-a-real-event', 0);
		$this->assertParsedEvent($guest, 'no-such-event', 0);

		$officer = $this->makeRouter(
			['events'],
			$rows,
			new MemberUser(6),
			[$this->profile(6, 8, true)]
		);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($officer, 'officers-briefing', 50);
		$this->assertParsedEvent($officer, '50', 50);
		$this->assertParsedEvent($officer, '50-old-name', 0);
		$this->assertParsedEvent($officer, 'manager-only', 51);
		$this->assertParsedEvent($officer, 'closed-workshop', 0);
		$this->assertParsedEvent($officer, '91', 0);

		$manager = $this->makeRouter(
			['events'],
			$rows,
			new MemberUser(5),
			[$this->profile(5, 9)]
		);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($manager, 'manager-only', 51);
		$this->assertParsedEvent($manager, '51', 51);
		$this->assertParsedEvent($manager, 'officers-briefing', 0);
		$this->assertParsedEvent($manager, '50', 0);

		$member = $this->makeRouter(
			['events'],
			$rows,
			new MemberUser(7),
			[$this->profile(7, 4)]
		);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($member, 'public-ride', 12);
		$this->assertParsedEvent($member, 'officers-briefing', 0);
		$this->assertParsedEvent($member, 'manager-only', 0);

		$admin = $this->makeRouter(['events'], $rows, new MemberUser(8, true));
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($admin, 'officers-briefing', 50);
		$this->assertParsedEvent($admin, 'closed-workshop', 0);
		$this->assertParsedEvent($admin, 'trashed-ride', 0);
	}

	/**
	 * A segment that is itself an alias wins over a bare id with the same digits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAliasMatchBeatsBareIdWithSharedDigits(): void
	{
		$rows = [
			$this->eventRow(12, 'Public Ride', 1, 'public-ride'),
			$this->eventRow(44, 'Digit Led', 1, '12-some-title'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 44, ['12-some-title'], 44);
		$this->assertParsedEvent($router, '12-some-title', 44);
		$this->assertParsedEvent($router, '12', 12);
	}

	/**
	 * An all-digit stored alias wins over the event whose id is that number.
	 *
	 * A bare id still opens by id when no row stores that alias. A hyphenated
	 * segment that is not a stored alias is a missing page.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllDigitAliasWinsOverTheSameId(): void
	{
		$rows = [
			$this->eventRow(1, 'First Ride', 1, 'first-ride'),
			$this->eventRow(25, 'Imported Stop', 1, '1'),
			$this->eventRow(12, 'Public Ride', 1, 'public-ride'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 25, ['1'], 25);
		$this->assertParsedEvent($router, '1', 25);
		$this->assertParsedEvent($router, '25', 25);
		$this->assertParsedEvent($router, '12-some-title', 0);

		$plain = $this->makeRouter(
			['events'],
			[
				$this->eventRow(1, 'First Ride', 1, 'first-ride'),
				$this->eventRow(25, 'Other Stop', 1, 'other-stop'),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		$this->assertParsedEvent($plain, '1', 1);
		$this->assertParsedEvent($plain, '25', 25);
		$this->assertParsedEvent($plain, '12-some-title', 0);

		$hidden = $this->makeRouter(
			['events'],
			[
				$this->eventRow(1, 'First Ride', 1, 'first-ride'),
				$this->eventRow(25, 'Imported Stop', 0, '1'),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		$this->assertParsedEvent($hidden, '1', 0);
		$this->assertBuiltEvent($hidden, 1, ['first-ride'], 1);
	}

	/**
	 * Title-derived and trailing-id addresses are missing pages.
	 *
	 * Build still emits the stored alias. An exact stored alias opens the event,
	 * including after a reserved view name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTitleDerivedAddressesAreMissing(): void
	{
		$rows = [
			$this->eventRow(4, 'Canyon Ride', 1, 'canyon-ride'),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 4, ['canyon-ride'], 4);
		$this->assertParsedEvent($router, 'canyon-ride', 4);
		$this->assertParsedEvent($router, 'canyon-ride-4', 0);
		$this->assertParsedEvent($router, 'trip-4', 0);
		$this->assertParsedEvent($router, 'some-ride-2026-11-17-4', 0);
		$this->assertParsedEvent($router, 'some-ride-2026-11-17', 0);

		$owned = $this->makeRouter(
			['events'],
			[
				$this->eventRow(4, 'Canyon Ride', 1, 'canyon-ride'),
				$this->eventRow(9, 'Owned Suffix', 1, 'trip-4'),
			]
		);
		$this->activateMenu(self::MENUS['events']);
		$this->assertParsedEvent($owned, 'trip-4', 9);

		$segments = ['trip', 'trip-4'];
		$vars = $owned->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 9], $vars);
		self::assertSame([], $segments);

		$segments = ['trip', 'canyon-ride'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 4], $vars);
		self::assertSame([], $segments);

		$segments = ['trip', 'canyon-ride-4'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$segments = ['trip', 'some-ride-2026-11-17'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$hidden = $this->makeRouter(
			['events'],
			[
				$this->eventRow(4, 'Canyon Ride', 0, 'canyon-ride'),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		$this->assertParsedEvent($hidden, 'trip-4', 0);
		$this->assertParsedEvent($hidden, 'canyon-ride', 0);
		$this->assertParsedEvent($hidden, 'canyon-ride-4', 0);
		$this->assertParsedEvent($hidden, 'some-ride-2026-11-17', 0);
	}

	/**
	 * Long, non-ASCII, and reserved-word aliases round-trip as stored.
	 *
	 * /trip is still the view. The following segment uses the stored alias.
	 * A query parameter stays on the query.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testStoredAliasEdgeShapesRoundTrip(): void
	{
		$long = str_repeat('n', 400);
		$near = str_repeat('m', 390) . '-near';
		$rows = [
			$this->eventRow(15, 'Trip', 1, 'trip'),
			$this->eventRow(16, 'Hill Country Lunch', 1, 'hill-country-lunch'),
			$this->eventRow(60, 'Cafe Ride', 1, 'café-ride'),
			$this->eventRow(61, 'Night Ride', 1, 'night-ride-🏁'),
			$this->eventRow(62, 'Long Ride', 1, $long),
			$this->eventRow(63, 'Near Long Ride', 1, $near),
		];
		$router = $this->makeRouter(['events'], $rows);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 60, ['café-ride'], 60);
		$this->assertBuiltEvent($router, 61, ['night-ride-🏁'], 61);
		$this->assertBuiltEvent($router, 62, [$long], 62);
		$this->assertBuiltEvent($router, 63, [$near], 63);
		$this->assertParsedEvent($router, 'Café-ride', 0);
		self::assertSame(400, mb_strlen($long, 'UTF-8'));
		self::assertLessThanOrEqual(400, mb_strlen($near, 'UTF-8'));

		$segments = ['trip'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 0], $vars);
		self::assertNotSame(15, $vars['id']);
		self::assertSame([], $segments);

		$segments = ['trip', 'trip'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 15], $vars);
		self::assertSame([], $segments);

		$segments = ['trip', 'hill-country-lunch'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 16], $vars);
		self::assertSame([], $segments);

		$catalogue = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		$segments = ['trip', 'trip'];
		$vars = $catalogue->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$segments = ['trip', 'trip-80'];
		$vars = $catalogue->parse($segments);

		self::assertSame(['view' => 'trip', 'id' => 80], $vars);
		self::assertSame([], $segments);

		$query = ['view' => 'event', 'id' => 60, 'format' => 'raw', 'tmpl' => 'component'];
		$prepared = $router->preprocess($query);
		$built = $router->build($prepared);

		self::assertSame(['café-ride'], $built);
		self::assertSame('raw', $prepared['format']);
		self::assertSame('component', $prepared['tmpl']);
		self::assertArrayNotHasKey('view', $prepared);
		self::assertArrayNotHasKey('id', $prepared);
	}

	/**
	 * Current event URLs build from the stored alias and parse back to that id.
	 *
	 * A bare numeric segment opens by id when no row stores that alias. Older
	 * title-slug and trailing-id forms are missing pages.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCurrentAliasAndBareIdUrlsBuildAndParse(): void
	{
		$twelve = $this->eventRow(12, 'Twin Ride', 1, 'r84-twin-ride', 1, '2026-11-12');
		$one = $this->eventRow(1, 'Some Ride', 1, 'some-ride', 1, '2026-11-01');
		$empty = $this->eventRow(77, 'Numbered Link', 1, '');
		$router = $this->makeRouter(['events'], [$one, $twelve, $empty]);
		$this->activateMenu(self::MENUS['events']);

		$this->assertBuiltEvent($router, 12, ['r84-twin-ride'], 12);
		$this->assertBuiltEvent($router, 1, ['some-ride'], 1);
		$this->assertBuiltEvent($router, 77, ['77'], 77);
		$this->assertParsedEvent($router, 'r84-twin-ride', 12);
		$this->assertParsedEvent($router, 'some-ride', 1);
		$this->assertParsedEvent($router, '77', 77);
		$this->assertParsedEvent($router, '12', 12);
		$this->assertParsedEvent($router, '1', 1);

		$this->assertParsedEvent($router, 'some-ride-2026-11-12', 0);
		$this->assertParsedEvent($router, 'some-ride-2026-11-1', 0);
		$this->assertParsedEvent($router, 'r84-twin-ride-2026-11-12', 0);
		$this->assertParsedEvent($router, 'no-such-alias-12', 0);
		$this->assertParsedEvent($router, 'twin-ride-12', 0);
		$this->assertParsedEvent($router, 'twin-ride-2026-11-12-12', 0);
		$this->assertParsedEvent($router, 'some-ride-2026-11-01-1', 0);
		$this->assertParsedEvent($router, 'renamed-ride-12', 0);
	}

	/**
	 * Hyphenated segments that are not stored aliases are missing pages.
	 *
	 * Stored aliases and bare ids still open the matching event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHyphenatedNonAliasSegmentsAreMissing(): void
	{
		$router = $this->makeRouter(
			['events'],
			[
				$this->eventRow(9, '2026', 1, 'year-2026'),
				$this->eventRow(7, '2031-2032', 1, 'season-2031'),
				$this->eventRow(8, '12 Days of Toys', 1, 'twelve-days', 1, '2026-12-08'),
				$this->eventRow(12, 'Toy Run 2026', 1, 'public-ride'),
				$this->eventRow(15, 'R84i Twin Ride 2', 1, 'chapter-ride'),
				$this->eventRow(2, 'Other Stop', 1, 'other-stop'),
				$this->eventRow(2026, 'Canyon Cruise', 1, 'canyon-cruise'),
			]
		);
		$this->activateMenu(self::MENUS['events']);

		$this->assertParsedEvent($router, 'year-2026', 9);
		$this->assertParsedEvent($router, 'season-2031', 7);
		$this->assertParsedEvent($router, 'twelve-days', 8);
		$this->assertParsedEvent($router, 'public-ride', 12);
		$this->assertParsedEvent($router, 'chapter-ride', 15);
		$this->assertParsedEvent($router, '9', 9);
		$this->assertParsedEvent($router, '12', 12);
		$this->assertParsedEvent($router, '2026', 2026);

		$this->assertParsedEvent($router, '2026-9', 0);
		$this->assertParsedEvent($router, '2031-2032-7', 0);
		$this->assertParsedEvent($router, '2031-2032', 0);
		$this->assertParsedEvent($router, '2026-7', 0);
		$this->assertParsedEvent($router, '9-anything', 0);
		$this->assertParsedEvent($router, '12-days-of-toys-8', 0);
		$this->assertParsedEvent($router, '12-days-of-toys-2026-12-08-8', 0);
		$this->assertParsedEvent($router, '12-days-of-toys-1999-01-01-8', 0);
		$this->assertParsedEvent($router, '12-anything', 0);
		$this->assertParsedEvent($router, '12-days-of-toys-2', 0);
		$this->assertParsedEvent($router, '15-r84i-twin-ride-2', 0);
		$this->assertParsedEvent($router, '12-toy-run-2026', 0);
		$this->assertParsedEvent($router, '99-toy-run-2026', 0);
		$this->assertParsedEvent($router, '2026-toy-run', 0);
		$this->assertParsedEvent($router, '2026-toy-run-2026-06-14', 0);
		$this->assertParsedEvent($router, '2026-something', 0);

		$prefixed = $this->makeRouter(
			[],
			[
				$this->eventRow(8, '12 Days of Toys', 1, 'twelve-days', 1, '2026-12-08'),
				$this->eventRow(12, 'Public Ride', 1, 'public-ride'),
			]
		);
		$segments = ['events', 'twelve-days'];
		$vars = $prefixed->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 8], $vars);
		self::assertSame([], $segments);

		$segments = ['events', '12-days-of-toys-8'];
		$vars = $prefixed->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertNotSame(12, $vars['id']);
		self::assertSame([], $segments);
	}

	/**
	 * An address that matches no event is a missing page.
	 *
	 * A segment after a matched alias stays in the list so Joomla can 404.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownSegmentGivesNotFound(): void
	{
		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);

		$this->assertParsedEvent($router, 'no-such-event', 0);
		$this->assertParsedEvent($router, 'fredericksburg-wine-wheels-2026-06-14', 0);

		$router = $this->makeRouter([]);
		$segments = ['events', 'no-such-event'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 0], $vars);
		self::assertSame([], $segments);

		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		$segments = [self::SLUG_LUNCH, 'not-a-page'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 20], $vars);
		self::assertSame(['not-a-page'], $segments);
	}

	/**
	 * Build one event link and parse it back.
	 *
	 * @param   Router   $router     Router under test. Its menu is already active.
	 * @param   integer  $eventId    Event id passed to build().
	 * @param   array    $segments   Segments build() must return.
	 * @param   integer  $parsedId   Id parse() must return.
	 * @param   string   $view       View to build and parse.
	 * @param   string   $key        Query key that carries the event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertBuiltEvent(
		Router $router,
		int $eventId,
		array $segments,
		int $parsedId,
		string $view = 'event',
		string $key = 'id'
	): void {
		$query = ['view' => $view, $key => $eventId];
		$prepared = $router->preprocess($query);
		$built = $router->build($prepared);

		self::assertSame($segments, $built);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));

		$vars = $router->parse($built);

		self::assertSame(['view' => $view, $key => $parsedId], $vars);
		self::assertSame([], $built);
	}

	/**
	 * Parse one event segment on the active router.
	 *
	 * @param   Router   $router   Router under test.
	 * @param   string   $segment  Path segment.
	 * @param   integer  $id       Event id parse() must return. 0 is a missing page.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertParsedEvent(Router $router, string $segment, int $id): void
	{
		$segments = [$segment];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => $id], $vars, $segment);
		self::assertSame([], $segments, $segment);
	}

	/**
	 * One event row for a router fixture.
	 *
	 * @param   integer  $id              Event primary key.
	 * @param   string   $title           Event title.
	 * @param   integer  $published       1 published, 0 unpublished, -2 trashed.
	 * @param   string   $alias           Stored alias. Empty builds the numeric id.
	 * @param   integer  $audienceScope   Audience scope. 1 is all visitors.

	 * @param   string   $startDate       Start date. Empty leaves the column out.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function eventRow(
		int $id,
		string $title,
		int $published,
		string $alias,
		int $audienceScope = 1,
		string $startDate = ''
	): object {
		$row = [
			'id' => $id,
			'title' => $title,
			'alias' => $alias,
			'published' => $published,
			'audience_scope' => $audienceScope,
		];

		if ($startDate !== '')
		{
			$row['start_date'] = $startDate;
		}

		return (object) $row;
	}

	/**
	 * Generate one alias the way a new event save would.
	 *
	 * @param   string  $title  Event title.
	 * @param   array   $taken  Aliases already stored on other rows.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function generatedAlias(string $title, array $taken): string
	{
		require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventAlias.php';

		$urlSafe = static function (string $value): string {
			$value = str_replace('-', ' ', $value);
			$value = strtolower($value);
			$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

			return trim($value, '-');
		};

		$built = EventAlias::build('', $title, 0, $taken, '2026-10-25-04-19-00', $urlSafe);
		self::assertIsString($built);

		return $built;
	}

	/**
	 * One published profile row for the visitor lookup.
	 *
	 * @param   integer  $userId        Joomla user id.
	 * @param   integer  $profileId     Profile id.
	 * @param   boolean  $officer       Whether the profile is a current officer.
	 * @param   boolean  $roadCaptain   Whether the profile is a road captain.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function profile(int $userId, int $profileId, bool $officer = false, bool $roadCaptain = false): object
	{
		return (object) [
			'profile_id' => $profileId,
			'user_id' => $userId,
			'is_officer' => $officer ? 1 : 0,
			'is_road_captain' => $roadCaptain ? 1 : 0,
		];
	}

	/**
	 * A segment after a matched alias stays in the list so Joomla can 404.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEdgeCaseAliasesLeaveAnUnknownTrailingSegment(): void
	{
		$router = $this->makeRouter(['events']);
		$this->activateMenu(self::MENUS['events']);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));

		$segments = ['2026-toy-run', 'not-a-page'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 70], $vars);
		self::assertSame(['not-a-page'], $segments);

		$segments = ['trip-80', 'not-a-page'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 80], $vars);
		self::assertSame(['not-a-page'], $segments);

		$router = $this->makeRouter([]);
		$segments = ['events', 'meetingattendance-82', 'not-a-page'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'event', 'id' => 82], $vars);
		self::assertSame(['not-a-page'], $segments);

		$segments = ['volunteerboard', 'volunteerboard-83', 'not-a-page'];
		$vars = $router->parse($segments);

		self::assertSame(['view' => 'volunteerboard', 'event_id' => 83], $vars);
		self::assertSame(['not-a-page'], $segments);
	}

	/**
	 * Run one build-then-parse round trip.
	 *
	 * @param   array    $menus              Menu views available on the site.
	 * @param   boolean  $expose             Whether the active menu is set for parse().
	 * @param   array    $query              Query passed to preprocess() and build().
	 * @param   array    $expectedSegments   Segments build() must return.
	 * @param   array    $expectedVars       Variables parse() must return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertRoundTrip(
		array $menus,
		bool $expose,
		array $query,
		array $expectedSegments,
		array $expectedVars
	): void {
		$router = $this->makeRouter($menus);
		$prepared = $router->preprocess($query);
		$segments = $router->build($prepared);

		if ($menus === [])
		{
			self::assertArrayNotHasKey('Itemid', $prepared);
		}
		else
		{
			self::assertNotEmpty($prepared['Itemid'] ?? null);
		}

		self::assertSame($expectedSegments, $segments);
		self::assertSame(0, $this->app->input->getInt('Itemid', 0));

		if ($expose)
		{
			$this->activateMenu((int) ($prepared['Itemid'] ?? 0));
		}

		$vars = $router->parse($segments);

		self::assertSame($expectedVars, $vars);
		self::assertSame([], $segments);
	}

	/**
	 * Store one round-trip case.
	 *
	 * @param   array    $cases     Case list, keyed by name.
	 * @param   string   $name      Case name.
	 * @param   array    $menus     Menu views available on the site.
	 * @param   array    $query     Query passed to preprocess() and build().
	 * @param   boolean  $expose    Whether the active menu is set for parse().
	 * @param   array    $segments  Segments build() must return.
	 * @param   array    $expected  Variables parse() must return.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private static function add(
		array &$cases,
		string $name,
		array $menus,
		array $query,
		bool $expose,
		array $segments,
		array $expected
	): void {
		$cases[$name] = [$menus, $query, $expose, $segments, $expected];
	}

	/**
	 * Build a router around the given menu views and the shared event rows.
	 *
	 * @param   array        $views      Menu views to publish.
	 * @param   array|null   $rows       Event rows, or null for the shared catalogue.
	 * @param   object|null  $user       Visitor, or null for a guest.
	 * @param   array        $profiles   Profile rows for that visitor.
	 *
	 * @return  Router
	 *
	 * @since   1.0.0
	 */
	private function makeRouter(array $views, ?array $rows = null, ?object $user = null, array $profiles = []): Router
	{
		$items = [];

		foreach ($views as $view)
		{
			$id = self::MENUS[$view];
			$items[$id] = (object) [
				'id' => $id,
				'component' => 'com_wendfold',
				'query' => [
					'option' => 'com_wendfold',
					'view' => $view,
				],
			];
		}

		$this->app = new SiteApplication($user);
		$this->menu = new SiteMenu($items);

		return new Router(
			$this->app,
			$this->menu,
			null,
			new DatabaseStub($rows ?? self::events(), $profiles)
		);
	}

	/**
	 * Mark the menu item SiteRouter would activate before parse().
	 *
	 * @param   integer  $itemId  Menu item id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function activateMenu(int $itemId): void
	{
		$this->menu->setActiveItem($itemId > 0 ? $this->menu->getItem($itemId) : null);
	}

	/**
	 * Published event rows the router reads.
	 *
	 * Ids 12 and 13 share a title and keep different aliases. Ids 40 and 41
	 * share a title and use foo and foo-2. Id 77 has an empty alias, so its
	 * link is the numeric id. Id 70 stores an alias that starts with digits,
	 * and id 2026 is that number.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private static function events(): array
	{
		$published = static function (int $id, string $title, string $alias, int $parent = 0): object {
			$row = (object) [
				'id' => $id,
				'title' => $title,
				'alias' => $alias,
				'published' => 1,
				'audience_scope' => 1,
			];

			if ($parent > 0)
			{
				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Stub mirrors the parent_event_id column.
				$row->parent_event_id = $parent;
			}

			return $row;
		};

		return [
			$published(12, 'Fredericksburg Wine Wheels', 'fredericksburg-wine-wheels'),
			$published(13, 'Fredericksburg Wine Wheels', 'fredericksburg-wine-wheels-2'),
			$published(20, 'Hill Country Lunch', 'hill-country-lunch', 30),
			$published(30, 'Overnight Hill Country', 'overnight-hill-country'),
			$published(40, 'Breakfast Ride', 'foo'),
			$published(41, 'Breakfast Ride', 'foo-2'),
			$published(70, '2026 Toy Run', '2026-toy-run'),
			$published(77, 'Numbered Link', ''),
			$published(80, 'Trip', 'trip-80'),
			$published(81, 'Rcattendance', 'rcattendance-81'),
			$published(82, 'Meetingattendance', 'meetingattendance-82'),
			$published(83, 'Volunteerboard', 'volunteerboard-83'),
			$published(2026, 'Canyon Cruise', 'canyon-cruise'),
		];
	}
}
