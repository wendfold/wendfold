<?php

/**
 * Wendfold - Event type report buckets.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBuckets;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeBuckets.php';

/**
 * Report buckets follow event_types titles, including a type a chapter added.
 *
 * @since  1.0.0
 */
final class EventTypeBucketsTest extends TestCase
{
	/**
	 * A chapter-defined type stays in its own bucket.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomTypeKeepsItsOwnTitleAndCount(): void
	{
		$counts = EventTypeBuckets::counts(
			[
				['id' => 1, 'title' => 'COM_WENDFOLD_EVENT_TYPE_RIDE', 'behavior' => 1, 'count' => 2],
				['id' => 40, 'title' => 'Poker Run', 'behavior' => 4, 'count' => 3],
			]
		);

		$this->assertSame('Poker Run', $counts[40]['title']);
		$this->assertSame(3, $counts[40]['count']);
		$this->assertSame(4, $counts[40]['behavior']);
		$this->assertArrayNotHasKey(4, $counts);
		$this->assertArrayNotHasKey(5, $counts);
		$this->assertSame(5, EventTypeBuckets::total($counts));

		$monthly = EventTypeBuckets::monthly(
			[
				['month' => '2026-03', 'id' => 40, 'title' => 'Poker Run', 'behavior' => 4, 'count' => 4],
				['month' => '2026-03', 'id' => 1, 'title' => 'COM_WENDFOLD_EVENT_TYPE_RIDE', 'behavior' => 1, 'count' => 1],
			]
		);

		$this->assertSame(4, $monthly['2026-03'][40]['count']);
		$this->assertSame('Poker Run', $monthly['2026-03'][40]['title']);

		$columns = EventTypeBuckets::columns($monthly);

		$this->assertSame('Poker Run', $columns[40]['title']);
		$this->assertSame('COM_WENDFOLD_EVENT_TYPE_RIDE', $columns[1]['title']);

		$averages = EventTypeBuckets::averages(
			[
				['id' => 40, 'title' => 'Poker Run', 'behavior' => 4, 'attendance' => 8],
				['id' => 40, 'title' => 'Poker Run', 'behavior' => 4, 'attendance' => 2],
				['id' => 1, 'title' => 'COM_WENDFOLD_EVENT_TYPE_RIDE', 'behavior' => 1, 'attendance' => 5],
			]
		);

		$this->assertSame(5.0, $averages['by_type'][40]['average']);
		$this->assertSame('Poker Run', $averages['by_type'][40]['title']);
		$this->assertArrayNotHasKey(4, $averages['by_type']);
		$this->assertSame(5.0, $averages['overall']);
	}
}
