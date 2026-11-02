<?php

/**
 * Wendfold - site myactivities own-records and totals.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricQuery;
use Wendfold\Component\Wendfold\Site\Model\MyactivitiesModel;
use Wendfold\Tests\Support\GuestUser;
use Wendfold\Tests\Support\MemberUser;
use Wendfold\Tests\Support\MyactivitiesAppDouble;
use Wendfold\Tests\Support\MyactivitiesDatabaseStub;
use Wendfold\Tests\Support\MyactivitiesQueryStub;

require_once __DIR__ . '/support/MyactivitiesModelDouble.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricAggregation.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricEntryTitle.php';
require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricQuery.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Model/MyactivitiesModel.php';

/**
 * Exposes list query and populateState without booting Joomla's ListModel.
 *
 * @since  1.0.0
 */
class MyactivitiesModelHarness extends MyactivitiesModel
{
	/**
	 * @var    MyactivitiesDatabaseStub
	 * @since  1.0.0
	 */
	private MyactivitiesDatabaseStub $database;

	/**
	 * @param   MyactivitiesDatabaseStub|null  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?MyactivitiesDatabaseStub $database = null)
	{
		$this->database = $database ?? new MyactivitiesDatabaseStub;
		parent::__construct([]);
	}

	/**
	 * @return  MyactivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function exposeListQuery(): MyactivitiesQueryStub
	{
		/** @var MyactivitiesQueryStub $query */
		$query = $this->getListQuery();

		return $query;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function exposePopulateState(): void
	{
		$this->populateState();
	}

	/**
	 * @return  MyactivitiesDatabaseStub
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}
}

/**
 * Locks own-records scoping, guest denial, forged filters, and MetricQuery totals.
 *
 * @since  1.0.0
 */
final class MyactivitiesModelTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		parent::setUp();
		ListModel::$incomingFilters = [];
		Factory::$application = null;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function tearDown(): void
	{
		ListModel::$incomingFilters = [];
		Factory::$application = null;
		parent::tearDown();
	}

	/**
	 * Guests cannot open the page; logged-in users can.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGuestIsDeniedAndMemberIsAllowed(): void
	{
		self::assertFalse(MyactivitiesModel::allowsVisitor(null));
		self::assertFalse(MyactivitiesModel::allowsVisitor(new GuestUser));
		self::assertTrue(MyactivitiesModel::allowsVisitor(new MemberUser(7)));
	}

	/**
	 * @return  array<string, array{0: array<string, mixed>}>
	 *
	 * @since   1.0.0
	 */
	public static function forgedProfileFilterProvider(): array
	{
		return [
			'GET filter[profile_id]'  => [['profile_id' => 999]],
			'POST filter[profile_id]' => [['profile_id' => '999']],
			'member_id alias'         => [['member_id' => 999, 'profile_id' => 888]],
		];
	}

	/**
	 * Forged filter profile ids from GET or POST never become the list scope.
	 *
	 * @param   array<string, mixed>  $filters  Incoming request filters.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('forgedProfileFilterProvider')]
	public function testForgedRequestFilterProfileIdReturnsOnlyOwnRows(array $filters): void
	{
		$model = $this->modelForMemberA();
		ListModel::$incomingFilters = $filters;
		$model->exposePopulateState();

		$query = $model->exposeListQuery();

		self::assertSame(5, (int) $query->bound[':ownProfileId']);
		self::assertSame(5, (int) $model->getState('filter.profile_id'));
		self::assertNotContains(999, array_map('intval', array_filter($query->bound, 'is_numeric')));
		self::assertNotContains(888, array_map('intval', array_filter($query->bound, 'is_numeric')));
	}

	/**
	 * A poisoned session user-state profile id is overwritten; list stays own-only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSessionUserStateProfileIdStillReturnsOnlyOwnRows(): void
	{
		$model = $this->modelForMemberA();
		/** @var MyactivitiesAppDouble $app */
		$app = Factory::$application;
		$app->userState['com_wendfold.myactivities.filter.profile_id'] = 999;
		ListModel::$incomingFilters = ['profile_id' => 999];

		$model->exposePopulateState();

		self::assertSame(5, (int) $model->getState('filter.profile_id'));
		self::assertSame(
			5,
			(int) $app->getUserState('com_wendfold.myactivities.filter.profile_id')
		);
		self::assertArrayNotHasKey(
			'com_wendfold.myactivities.filter.member_id',
			$app->userState
		);

		$query = $model->exposeListQuery();
		self::assertSame(5, (int) $query->bound[':ownProfileId']);
	}

	/**
	 * Even if state still holds B, getListQuery resolves A from the current user.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListQueryIgnoresPoisonedStateAndUsesCurrentUser(): void
	{
		$model = $this->modelForMemberA();
		$model->setState('filter.profile_id', 999);

		$query = $model->exposeListQuery();

		self::assertSame(5, (int) $query->bound[':ownProfileId']);
		self::assertSame(999, (int) $model->getState('filter.profile_id'));
	}

	/**
	 * List query left-joins activities for activity-sourced metric entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListQueryLeftJoinsActivitiesForActivitySource(): void
	{
		$model = $this->modelForMemberA();
		$query = $model->exposeListQuery();
		$sql = (string) $query;

		self::assertStringContainsString('#__wendfold_activities', $sql);
		self::assertStringContainsString('#__wendfold_events', $sql);
		self::assertStringContainsString('#__wendfold_event_volunteers', $sql);
		self::assertStringContainsString('LEFT ', $sql);
		self::assertStringContainsString('activity_title', $sql);
		self::assertStringContainsString('me.source_type', $sql);
		self::assertSame(
			\Wendfold\Component\Wendfold\Administrator\Service\MetricSource::Activity->value,
			(int) $query->bound[':activitySourceTitle']
		);
		self::assertSame(5, (int) $query->bound[':ownProfileId']);
	}

	/**
	 * My activities template shows the joined activity title and keeps Notes separate.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMyactivitiesTemplateShowsActivityTitleColumn(): void
	{
		$template = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/myactivities/default.php'
		);

		self::assertStringContainsString('COM_WENDFOLD_MYACTIVITIES_TITLE', $template);
		self::assertStringContainsString('activity_title', $template);
		self::assertStringContainsString('MetricEntryTitle::display', $template);
		self::assertStringContainsString('COM_WENDFOLD_ACTIVITY_NOTES', $template);
		self::assertStringContainsString('MetricDisplayLabel::formatAmountWithUnit', $template);
		self::assertStringNotContainsString("\$item->note =", $template);
		self::assertDoesNotMatchRegularExpression(
			'/activity_title[\s\S]{0,80}\$note\s*=/',
			$template
		);
	}

	/**
	 * Totals equal SUM(value) for published = 1 and ignore a forged profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTotalsEqualSqlSumOfPublishedEntriesAndIgnoreForgedId(): void
	{
		$db = $this->entriesDatabase();
		$model = $this->modelForMemberA($db);
		$model->setState('filter.profile_id', 9);
		ListModel::$incomingFilters = ['profile_id' => 9];
		$model->exposePopulateState();

		$range = $model->totalsDateRange();
		$aggregate = MetricQuery::profileAggregate($db, 5, $range['start'], $range['end']);

		$sqlSumMetric1 = 0.0;
		$sqlSumMetric2 = 0.0;

		foreach ($db->entries as $entry)
		{
			if ((int) $entry['profile_id'] !== 5 || (int) $entry['published'] !== 1)
			{
				continue;
			}

			if ((int) $entry['metric_id'] === 1)
			{
				$sqlSumMetric1 += (float) $entry['value'];
			}

			if ((int) $entry['metric_id'] === 2)
			{
				$sqlSumMetric2 += (float) $entry['value'];
			}
		}

		self::assertSame('14.5', MetricQuery::pickedTotal($aggregate[1], 1));
		self::assertSame('3', MetricQuery::pickedTotal($aggregate[2], 1));
		self::assertSame(14.5, $sqlSumMetric1);
		self::assertSame(3.0, $sqlSumMetric2);

		$totals = $model->getMetricTotals();
		$byTitle = [];

		foreach ($totals as $row)
		{
			$byTitle[$row->title] = $row->total;
		}

		self::assertSame('14.5', $byTitle['Distance']);
		self::assertSame('3', $byTitle['Events']);
		self::assertSame(5, (int) $model->getState('filter.profile_id'));
	}

	/**
	 * Guest identity yields an empty list scope.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGuestIdentityBindsZeroProfileId(): void
	{
		$db = new MyactivitiesDatabaseStub;
		Factory::$application = new MyactivitiesAppDouble(new GuestUser);
		$model = new MyactivitiesModelHarness($db);
		ListModel::$incomingFilters = ['profile_id' => 5];
		$model->exposePopulateState();

		$query = $model->exposeListQuery();
		self::assertSame(0, (int) $query->bound[':ownProfileId']);
		self::assertSame([], $model->getMetricTotals());
	}

	/**
	 * @param   MyactivitiesDatabaseStub|null  $db  Optional fixture db.
	 *
	 * @return  MyactivitiesModelHarness
	 *
	 * @since   1.0.0
	 */
	private function modelForMemberA(?MyactivitiesDatabaseStub $db = null): MyactivitiesModelHarness
	{
		$db ??= new MyactivitiesDatabaseStub;
		$db->profilesByUserId[7] = (object) [
			'id'            => 5,
			'user_id'       => 7,
			'member_number' => 'A1',
		];
		$db->profilesByUserId[42] = (object) [
			'id'            => 9,
			'user_id'       => 42,
			'member_number' => 'B2',
		];

		Factory::$application = new MyactivitiesAppDouble(new MemberUser(7));

		return new MyactivitiesModelHarness($db);
	}

	/**
	 * @return  MyactivitiesDatabaseStub
	 *
	 * @since   1.0.0
	 */
	private function entriesDatabase(): MyactivitiesDatabaseStub
	{
		$db = new MyactivitiesDatabaseStub;
		$db->entries = [
			[
				'profile_id' => 5,
				'metric_id'  => 1,
				'value'      => 10.5,
				'published'  => 1,
				'entry_date' => '2026-03-01',
			],
			[
				'profile_id' => 5,
				'metric_id'  => 1,
				'value'      => 4.0,
				'published'  => 1,
				'entry_date' => '2026-04-01',
			],
			[
				'profile_id' => 5,
				'metric_id'  => 1,
				'value'      => 99.0,
				'published'  => 0,
				'entry_date' => '2026-05-01',
			],
			[
				'profile_id' => 5,
				'metric_id'  => 1,
				'value'      => 50.0,
				'published'  => -2,
				'entry_date' => '2026-06-01',
			],
			[
				'profile_id' => 5,
				'metric_id'  => 2,
				'value'      => 3.0,
				'published'  => 1,
				'entry_date' => '2026-03-15',
			],
			[
				'profile_id' => 9,
				'metric_id'  => 1,
				'value'      => 1000.0,
				'published'  => 1,
				'entry_date' => '2026-03-01',
			],
		];
		$db->definitions = [
			(object) [
				'id'          => 1,
				'title'       => 'Distance',
				'unit_label'  => 'mi',
				'decimals'    => 1,
				'aggregation' => 1,
			],
			(object) [
				'id'          => 2,
				'title'       => 'Events',
				'unit_label'  => '',
				'decimals'    => 0,
				'aggregation' => 1,
			],
		];

		return $db;
	}
}
