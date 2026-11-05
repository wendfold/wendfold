<?php

/**
 * Wendfold - Activities list query must not put binds only in SELECT.
 *
 * ListModel::_getListCount clears SELECT but keeps binds. A pending-count
 * subselect in SELECT caused ArgumentCountError on the activities list.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Model\ActivitiesModel;
use Wendfold\Tests\Support\ActivitiesDatabaseStub;
use Wendfold\Tests\Support\ActivitiesQueryStub;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once __DIR__ . '/support/ActivitiesModelDouble.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MetricEntryTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/StateValue.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/ActivitiesModel.php';

/**
 * Exposes getListQuery without booting Joomla's ListModel constructor.
 *
 * @since  1.0.0
 */
final class ActivitiesModelListHarness extends ActivitiesModel
{
	/**
	 * @var    ActivitiesDatabaseStub
	 * @since  1.0.0
	 */
	private ActivitiesDatabaseStub $database;

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $search = '';

	/**
	 * @param   ActivitiesDatabaseStub|null  $database  Query factory.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?ActivitiesDatabaseStub $database = null)
	{
		$this->database = $database ?? new ActivitiesDatabaseStub;
	}

	/**
	 * @param   string  $search  Filter text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setSearch(string $search): void
	{
		$this->search = $search;
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
			'filter.search' => $this->search,
			'list.ordering' => 'me.entry_date',
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
 * Activities list and count queries stay executable with and without rows.
 *
 * @since  1.0.0
 */
final class ActivitiesListQueryTest extends TestCase
{
	/**
	 * The list SELECT has no named placeholders of its own.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListQuerySelectHasNoBinds(): void
	{
		$model = new ActivitiesModelListHarness;
		$query = $model->exposeListQuery();
		$sql = (string) $query;

		$this->assertStringContainsString('#__wendfold_metric_entries', $sql);
		$this->assertStringContainsString('#__wendfold_activities', $sql);
		$this->assertStringContainsString('activity_title', $sql);
		$this->assertStringNotContainsString('pending_entries', $sql);
		$this->assertStringNotContainsString(':pendingPublished', $sql);

		// Title source-type binds live on JOINs so COUNT (which clears SELECT) stays valid.
		$this->assertArrayHasKey(':activitySourceTitle', $query->bound);
		$this->assertArrayHasKey(':attendanceSourceTitle', $query->bound);
		$this->assertStringNotContainsString(':searchTitle', $sql);
	}

	/**
	 * A search filter keeps its binds in WHERE, which COUNT still needs.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSearchBindsStayOnWhereClause(): void
	{
		$model = new ActivitiesModelListHarness;
		$model->setSearch('morning');
		$query = $model->exposeListQuery();
		$sql = (string) $query;

		$this->assertStringContainsString('LIKE :searchTitle', $sql);
		$this->assertArrayHasKey(':searchTitle', $query->bound);
		$this->assertArrayHasKey(':searchName', $query->bound);

		$countSql = $this->countSql($query);
		$this->assertStringContainsString('COUNT(*)', $countSql);
		$this->assertStringContainsString('LIKE :searchTitle', $countSql);
		$this->assertStringNotContainsString('pending_entries', $countSql);
	}

	/**
	 * Mimic ListModel::_getListCount clearing select and order.
	 *
	 * @param   ActivitiesQueryStub  $query  List query.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function countSql(ActivitiesQueryStub $query): string
	{
		$clone = clone $query;
		$clone->clear('select')->clear('order')->clear('limit')->clear('offset')->select('COUNT(*)');

		return (string) $clone;
	}
}
