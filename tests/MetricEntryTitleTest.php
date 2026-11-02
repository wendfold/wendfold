<?php

/**
 * Wendfold - shared metric entry title resolution across list views.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MetricEntryTitle;
use Wendfold\Component\Wendfold\Administrator\Model\ActivitiesModel;
use Wendfold\Component\Wendfold\Administrator\Service\MetricSource;
use Wendfold\Component\Wendfold\Site\Model\ActivitylogModel;
use Wendfold\Component\Wendfold\Site\Model\MyactivitiesModel;
use Wendfold\Tests\Support\ActivitiesDatabaseStub;
use Wendfold\Tests\Support\ActivitiesQueryStub;
use Wendfold\Tests\Support\MemberUser;
use Wendfold\Tests\Support\MyactivitiesAppDouble;
use Wendfold\Tests\Support\MyactivitiesDatabaseStub;
use Wendfold\Tests\Support\MyactivitiesQueryStub;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/BaseDatabaseModelStub.php';
require_once __DIR__ . '/support/MyactivitiesModelDouble.php';
require_once __DIR__ . '/support/ActivitiesModelDouble.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/StateValue.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricEntryTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricQuery.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricAggregation.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Model/MyactivitiesModel.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/ActivitiesModel.php';
require_once dirname(__DIR__) . '/com_wendfold/site/src/Model/ActivitylogModel.php';

/**
 * Exposes Myactivities list query.
 *
 * @since  1.0.0
 */
final class MetricEntryTitleMyactivitiesHarness extends MyactivitiesModel
{
	/**
	 * @var    MyactivitiesDatabaseStub
	 * @since  1.0.0
	 */
	private MyactivitiesDatabaseStub $database;

	/**
	 * @param   MyactivitiesDatabaseStub  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(MyactivitiesDatabaseStub $database)
	{
		$this->database = $database;
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
 * Exposes Activities list query.
 *
 * @since  1.0.0
 */
final class MetricEntryTitleActivitiesHarness extends ActivitiesModel
{
	/**
	 * @var    ActivitiesDatabaseStub
	 * @since  1.0.0
	 */
	private ActivitiesDatabaseStub $database;

	/**
	 * @param   ActivitiesDatabaseStub  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(ActivitiesDatabaseStub $database)
	{
		$this->database = $database;
	}

	/**
	 * @return  ActivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function exposeListQuery(): ActivitiesQueryStub
	{
		/** @var ActivitiesQueryStub $query */
		$query = $this->getListQuery();

		return $query;
	}

	/**
	 * @return  ActivitiesDatabaseStub
	 *
	 * @since   1.0.0
	 */
	public function getDatabase()
	{
		return $this->database;
	}

	/**
	 * @param   string  $key      State key.
	 * @param   mixed   $default  Default.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function getState($key = null, $default = null)
	{
		$state = new Registry([
			'filter.search'  => '',
			'list.ordering'  => 'me.entry_date',
			'list.direction' => 'DESC',
			]
		);

		if ($key === null)
		{
			return $state;
		}

		return $state->get($key, $default);
	}
}

/**
 * Exposes Activitylog title query.
 *
 * @since  1.0.0
 */
final class MetricEntryTitleActivitylogHarness extends ActivitylogModel
{
	/**
	 * @var    MyactivitiesDatabaseStub
	 * @since  1.0.0
	 */
	private MyactivitiesDatabaseStub $database;

	/**
	 * @param   MyactivitiesDatabaseStub  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(MyactivitiesDatabaseStub $database)
	{
		$this->database = $database;
	}

	/**
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  MyactivitiesQueryStub
	 *
	 * @since   1.0.0
	 */
	public function exposeTitleQuery(int $profileId): MyactivitiesQueryStub
	{
		/** @var MyactivitiesQueryStub $query */
		$query = $this->buildEntriesTitleQuery($profileId);

		return $query;
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
 * Shared MetricEntryTitle SQL and display behaviour.
 *
 * @since  1.0.0
 */
final class MetricEntryTitleTest extends TestCase
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
	 * @return  array<string, array{0: string}>
	 *
	 * @since   1.0.0
	 */
	public static function sourceTypeFragmentProvider(): array
	{
		return [
			'activity'   => ["me.source_type = '2'"],
			'attendance' => ["me.source_type = '1'"],
			'volunteer'  => ["me.source_type = '5'"],
			'manual'     => ["me.source_type = '3'"],
			'import'     => ["me.source_type = '4'"],
		];
	}

	/**
	 * The shared CASE covers every MetricSource value.
	 *
	 * @param   string  $fragment  Expected CASE fragment.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('sourceTypeFragmentProvider')]
	public function testTitleExpressionCoversEachSourceType(string $fragment): void
	{
		$db = new MyactivitiesDatabaseStub;
		$sql = MetricEntryTitle::titleExpression($db);

		self::assertStringContainsString($fragment, $sql);
		self::assertStringContainsString('Volunteer: ', $sql);
		self::assertStringContainsString('Manual entry', $sql);
		self::assertStringContainsString('note', $sql);
		self::assertStringContainsString('IS NULL', $sql);
		self::assertStringContainsString('Imported entry', $sql);
		self::assertStringContainsString('Metric entry', $sql);
		self::assertStringContainsString('CONCAT(', $sql);
		self::assertStringContainsString('e_src.title', $sql);
		self::assertStringContainsString('a.title', $sql);
		self::assertStringContainsString('vr.title', $sql);
	}

	/**
	 * Empty resolved titles stay empty so templates can render "—".
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDisplayReturnsEmptyForDashFallback(): void
	{
		self::assertSame('', MetricEntryTitle::display(null));
		self::assertSame('', MetricEntryTitle::display(''));
		self::assertSame('', MetricEntryTitle::display('   '));
		self::assertSame('Group Ride', MetricEntryTitle::display(' Group Ride '));
	}

	/**
	 * My activities, admin Activities, and activity log share one title expression.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testThreeViewsResolveIdenticalTitleExpression(): void
	{
		$expected = MetricEntryTitle::titleExpression(new MyactivitiesDatabaseStub);

		$myDb = new MyactivitiesDatabaseStub;
		$myDb->profilesByUserId[7] = (object) [
			'id'            => 5,
			'user_id'       => 7,
			'member_number' => 'A1',
		];
		Factory::$application = new MyactivitiesAppDouble(new MemberUser(7));
		$myQuery = (new MetricEntryTitleMyactivitiesHarness($myDb))->exposeListQuery();

		$adminQuery = (new MetricEntryTitleActivitiesHarness(new ActivitiesDatabaseStub))->exposeListQuery();
		$logQuery = (new MetricEntryTitleActivitylogHarness(new MyactivitiesDatabaseStub))->exposeTitleQuery(5);

		foreach ([$myQuery, $adminQuery, $logQuery] as $query)
		{
			$sql = (string) $query;
			self::assertStringContainsString($expected, $sql);
			self::assertStringContainsString('activity_title', $sql);
			self::assertStringContainsString('#__wendfold_activities', $sql);
			self::assertStringContainsString('#__wendfold_events', $sql);
			self::assertStringContainsString('#__wendfold_event_volunteers', $sql);
			self::assertStringContainsString('#__wendfold_event_volunteer_roles', $sql);
			self::assertArrayHasKey(':activitySourceTitle', $query->bound);
			self::assertArrayHasKey(':attendanceSourceTitle', $query->bound);
			self::assertArrayHasKey(':volunteerSourceTitle', $query->bound);
			self::assertSame(MetricSource::Activity->value, (int) $query->bound[':activitySourceTitle']);
			self::assertSame(MetricSource::Attendance->value, (int) $query->bound[':attendanceSourceTitle']);
			self::assertSame(MetricSource::Volunteer->value, (int) $query->bound[':volunteerSourceTitle']);
		}
	}

	/**
	 * Manual / unset source types resolve to note or the Manual entry label, never an empty title.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testManualAndUnsetSourceTypesUseManualEntryLabel(): void
	{
		$db = new MyactivitiesDatabaseStub;
		$sql = MetricEntryTitle::titleExpression($db);

		self::assertStringContainsString("OR me.source_type = '0'", $sql);
		self::assertStringContainsString('me.source_type IS NULL', $sql);
		self::assertStringContainsString("OR me.source_type = '3'", $sql);
		self::assertStringContainsString('COALESCE(NULLIF(TRIM(me.note)', $sql);
		self::assertStringContainsString('Manual entry', $sql);
		self::assertStringNotContainsString("WHEN '3' THEN NULLIF", $sql);
	}

	/**
	 * Templates escape the shared title and keep the em-dash only for empty values.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTemplatesEscapeSharedTitleAndDashFallback(): void
	{
		$files = [
			dirname(__DIR__) . '/com_wendfold/site/tmpl/myactivities/default.php',
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/activities/default.php',
			dirname(__DIR__) . '/com_wendfold/site/tmpl/activitylog/default.php',
		];

		foreach ($files as $file)
		{
			$template = (string) file_get_contents($file);
			self::assertStringContainsString('MetricEntryTitle::display', $template);
			self::assertStringContainsString('COM_WENDFOLD_MYACTIVITIES_NOTE_NONE', $template);
			self::assertStringContainsString('$this->escape', $template);
		}
	}
}
