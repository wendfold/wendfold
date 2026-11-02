<?php

/**
 * Wendfold - attendance remove must scope metric trash to the event.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';

/**
 * Remove confirms ownership before touching metric_entries.
 *
 * @since  1.0.0
 */
final class AttendanceRemoveScopeTest extends TestCase
{
	/**
	 * A mismatched event/attendance pair leaves the roster and metrics alone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMismatchedRemoveChangesNothing(): void
	{
		$roster = new AttendanceRosterDouble;
		$roster->rows[10] = ['id' => 10, 'event_id' => 4, 'profile_id' => 9];
		$store = $this->creditedStore();
		$service = new MetricService($store, 7);

		$result = $roster->remove(10, 99, $service);

		$this->assertSame('not_found', $result);
		$this->assertArrayHasKey(10, $roster->rows);
		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(1, $store->entries[1]['published']);
	}

	/**
	 * A matched remove deletes the row and trashes its metric entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMatchedRemoveDeletesRowAndTrashesEntries(): void
	{
		$roster = new AttendanceRosterDouble;
		$roster->rows[10] = ['id' => 10, 'event_id' => 4, 'profile_id' => 9];
		$store = $this->creditedStore();
		$service = new MetricService($store, 7);

		$result = $roster->remove(10, 4, $service);

		$this->assertTrue($result);
		$this->assertArrayNotHasKey(10, $roster->rows);
		$this->assertSame(-2, $store->entries[0]['published']);
		$this->assertSame(-2, $store->entries[1]['published']);
	}

	/**
	 * @return  MemoryMetricEntryStore
	 *
	 * @since   1.0.0
	 */
	private function creditedStore(): MemoryMetricEntryStore
	{
		$store = new MemoryMetricEntryStore;
		$store->eventDates[4] = '2026-06-15';
		$store->eventStates[4] = 1;
		$store->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 9,
			'event_id'   => 4,
			'event_date' => '2026-06-15',
		];
		$store->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '33.000'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];
		(new MetricService($store, 7))->creditAttendance(4, 10);

		return $store;
	}
}

/**
 * Attendance remove sequence used by meeting, RC, and admin paths.
 *
 * @since  1.0.0
 */
final class AttendanceRosterDouble
{
	/**
	 * Attendance rows keyed by id.
	 *
	 * @var    array<int, array{id: int, event_id: int, profile_id: int}>
	 * @since  1.0.0
	 */
	public array $rows = [];

	/**
	 * Load by id and event, delete only when owned, then trash metrics.
	 *
	 * @param   integer         $attendanceId  Attendance id.
	 * @param   integer         $eventId       Authorized event id.
	 * @param   MetricService   $service       Metric writer.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function remove(int $attendanceId, int $eventId, MetricService $service): bool|string
	{
		$row = $this->rows[$attendanceId] ?? null;

		if ($row === null || (int) $row['event_id'] !== $eventId)
		{
			return 'not_found';
		}

		$profileId = (int) $row['profile_id'];
		unset($this->rows[$attendanceId]);
		$service->trashAttendance($eventId, $attendanceId, $profileId);

		return true;
	}
}
