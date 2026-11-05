<?php

/**
 * Wendfold - check-in on a trashed event must not create rows.
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
 * Admin, meeting, RC, and QR add paths refuse trash before insert.
 *
 * @since  1.0.0
 */
final class AttendanceTrashedCheckinTest extends TestCase
{
	/**
	 * A trashed event refuses check-in and creates no attendance or entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashedEventCheckInCreatesNoRowOrEntries(): void
	{
		$roster = new AttendanceAddDouble;
		$roster->eventPublished[4] = -2;
		$store = new MemoryMetricEntryStore;
		$store->eventDates[4] = '2026-06-15';
		$store->eventStates[4] = -2;
		$store->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '33.000'],
		];
		$service = new MetricService($store, 7);

		$result = $roster->add(4, 9, $store, $service);

		$this->assertSame('trashed', $result);
		$this->assertSame([], $roster->rows);
		$this->assertSame([], $store->entries);
		$this->assertSame([], $store->attendances);
	}

	/**
	 * A published event still credits attendance at published = 1.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishedEventCheckInCreditsAtOne(): void
	{
		$roster = new AttendanceAddDouble;
		$roster->eventPublished[4] = 1;
		$store = new MemoryMetricEntryStore;
		$store->eventDates[4] = '2026-06-15';
		$store->eventStates[4] = 1;
		$store->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '33.000'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];
		$service = new MetricService($store, 7);

		$result = $roster->add(4, 9, $store, $service);

		$this->assertTrue($result);
		$this->assertCount(1, $roster->rows);
		$this->assertCount(2, $store->entries);
		$this->assertSame(1, $store->entries[0]['published']);
		$this->assertSame(1, $store->entries[1]['published']);
	}

	/**
	 * An officer add on an unpublished event writes pending entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnpublishedEventOfficerAddCreditsAtZero(): void
	{
		$roster = new AttendanceAddDouble;
		$roster->eventPublished[4] = 0;
		$store = new MemoryMetricEntryStore;
		$store->eventDates[4] = '2026-06-15';
		$store->eventStates[4] = 0;
		$store->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '33.000'],
			(object) ['metric_id' => 2, 'value' => '1.000'],
		];
		$service = new MetricService($store, 7);

		$result = $roster->add(4, 9, $store, $service);

		$this->assertTrue($result);
		$this->assertCount(1, $roster->rows);
		$this->assertSame([0, 0], array_column($store->entries, 'published'));
	}
}

/**
 * Attendance add sequence used by admin, meeting, RC, and QR paths.
 *
 * @since  1.0.0
 */
final class AttendanceAddDouble
{
	/**
	 * Event published state keyed by event id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $eventPublished = [];

	/**
	 * Attendance rows keyed by id.
	 *
	 * @var    array<int, array{id: int, event_id: int, profile_id: int}>
	 * @since  1.0.0
	 */
	public array $rows = [];

	/**
	 * Next attendance id to assign.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $nextId = 1;

	/**
	 * Refuse trash, otherwise insert and credit.
	 *
	 * @param   integer                  $eventId    Event id.
	 * @param   integer                  $profileId  Profile id.
	 * @param   MemoryMetricEntryStore   $store      Entry store.
	 * @param   MetricService            $service    Metric writer.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function add(
		int $eventId,
		int $profileId,
		MemoryMetricEntryStore $store,
		MetricService $service
	): bool|string {
		if (($this->eventPublished[$eventId] ?? null) === -2)
		{
			return 'trashed';
		}

		$attendanceId = $this->nextId++;
		$this->rows[$attendanceId] = [
			'id'         => $attendanceId,
			'event_id'   => $eventId,
			'profile_id' => $profileId,
		];
		$store->attendances[$attendanceId] = (object) [
			'id'         => $attendanceId,
			'profile_id' => $profileId,
			'event_id'   => $eventId,
			'event_date' => $store->eventDates[$eventId] ?? '2026-06-15',
		];
		$service->creditAttendance($eventId, $attendanceId);

		return true;
	}
}
