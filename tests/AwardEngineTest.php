<?php

/**
 * Wendfold - AwardEngine recompute rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\AwardPrerequisiteGraph;
use Wendfold\Component\Wendfold\Administrator\Service\AwardChangeSink;
use Wendfold\Component\Wendfold\Administrator\Service\AwardCriterionType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardEngine;
use Wendfold\Component\Wendfold\Administrator\Service\AwardGrantSource;
use Wendfold\Component\Wendfold\Administrator\Service\AwardMatchMode;
use Wendfold\Component\Wendfold\Administrator\Service\AwardPeriodType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRevokePolicy;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;
use Wendfold\Tests\Support\MemoryAwardStore;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardPeriodType.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardCriterionType.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRepeatMode.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardMatchMode.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRevokePolicy.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardState.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardGrantSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardChangeSink.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/NullAwardChangeSink.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricPeriod.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardRecomputer.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardLockTimeoutException.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardEngine.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AwardPrerequisiteGraph.php';
require_once __DIR__ . '/support/MemoryAwardStore.php';

/**
 * Idempotent recompute, revoke, lock, tiers, periods, and prerequisites.
 *
 * @since  1.0.0
 */
final class AwardEngineTest extends TestCase
{
	/**
	 * Re-running recompute leaves the same ids and states.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRecomputeIsIdempotent(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);
		$sink = new RecordingAwardChangeSink;

		$first = (new AwardEngine($store, $sink, 'UTC', '2026-10-31 12:00:00', '2026-10-31'))->recompute();
		$idsAfterFirst = array_keys($store->memberAwards);
		$checksum = $this->checksum($store);

		$second = (new AwardEngine($store, $sink, 'UTC', '2026-10-31 13:00:00', '2026-10-31'))->recompute();

		$this->assertSame(1, $first);
		$this->assertSame(0, $second);
		$this->assertSame($idsAfterFirst, array_keys($store->memberAwards));
		$this->assertSame($checksum, $this->checksum($store));
		$this->assertSame(1, $store->memberAwards[1]['state']);
		$this->assertSame('2026-10-31 12:00:00', $store->memberAwards[1]['earned_at']);
	}

	/**
	 * A second no-change recompute performs no member_awards writes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSecondRecomputePerformsNoWrites(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);

		$engine->recompute();
		$store->writeCount = 0;

		$this->assertSame(0, $engine->recompute());
		$this->assertSame(0, $store->writeCount);
	}

	/**
	 * Trashing published metric entries revokes auto grants.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRevokeOnTrashedEntries(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);
		$engine->recompute();

		$store->entries[0]['published'] = -2;
		$changed = $engine->recompute();

		$this->assertSame(1, $changed);
		$this->assertSame(AwardState::Revoked->value, $store->memberAwards[1]['state']);
		$this->assertNotNull($store->memberAwards[1]['revoked_at']);
		$this->assertCount(1, $store->memberAwards);
	}

	/**
	 * Restoring entries re-grants the same row id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRegrantSameRowOnRestore(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);
		$engine->recompute();
		$originalId = (int) array_key_first($store->memberAwards);
		$earnedAt = $store->memberAwards[$originalId]['earned_at'];

		$store->entries[0]['published'] = -2;
		$engine->recompute();

		$store->entries[0]['published'] = 1;
		$changed = $engine->recompute();

		$this->assertSame(1, $changed);
		$this->assertArrayHasKey($originalId, $store->memberAwards);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[$originalId]['state']);
		$this->assertSame($earnedAt, $store->memberAwards[$originalId]['earned_at']);
		$this->assertNull($store->memberAwards[$originalId]['revoked_at']);
		$this->assertCount(1, $store->memberAwards);
	}

	/**
	 * Locked rows are never changed by recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLockedRowsUntouched(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);
		$engine->recompute();

		$store->memberAwards[1]['is_locked'] = 1;
		$store->memberAwards[1]['achieved_value'] = 999.0;
		$store->entries[0]['published'] = -2;

		$changed = $engine->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertSame(999.0, $store->memberAwards[1]['achieved_value']);
		$this->assertNull($store->memberAwards[1]['revoked_at']);
	}

	/**
	 * Tiered lifetime distance expands multiple tiers.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTierExpand(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Tiered->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 10000, 10000),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2024-01-01', 25000);

		$engine = $this->engine($store);
		$engine->recompute();

		$this->assertCount(2, $store->memberAwards);
		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2], $tiers);
		$this->assertSame('', $store->memberAwards[1]['period_key']);
	}

	/**
	 * Calendar-year awards store YYYY period keys.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCalendarYearPeriodKeys(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 5000, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2025-06-01', 6000);
		$store->entries[] = $this->entry(5, 1, '2026-03-01', 5500);
		$store->entries[] = $this->entry(5, 1, '2024-03-01', 100);

		$engine = $this->engine($store);
		$engine->recompute();

		$keys = array_column($store->memberAwards, 'period_key');
		sort($keys);
		$this->assertSame(['2025', '2026'], $keys);
		$this->assertNotContains('2024', $keys);
	}

	/**
	 * Lifetime awards use an empty period_key.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLifetimePeriodKeyIsEmpty(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$this->engine($store)->recompute();

		$this->assertSame('', $store->memberAwards[1]['period_key']);
	}

	/**
	 * Prerequisite type 3 requires another granted award.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrerequisiteTypeThree(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);

		$engine = $this->engine($store);

		// Only award 1 qualifies on the first pass.
		$engine->recompute(1);
		$this->assertCount(1, $store->memberAwards);

		// Award 2 needs the prerequisite; recompute after it exists.
		$engine->recompute(2);
		$this->assertCount(2, $store->memberAwards);

		$awardIds = array_column($store->memberAwards, 'award_id');
		sort($awardIds);
		$this->assertSame([1, 2], $awardIds);
	}

	/**
	 * Prerequisite cycles are rejected by DFS.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrerequisiteCycleRejection(): void
	{
		$edges = [
			1 => [2],
			2 => [3],
		];

		$this->assertTrue(AwardPrerequisiteGraph::wouldCycle($edges, 3, [1]));
		$this->assertTrue(AwardPrerequisiteGraph::wouldCycle($edges, 1, [1]));
		$this->assertFalse(AwardPrerequisiteGraph::wouldCycle($edges, 3, [4]));
		$this->assertFalse(AwardPrerequisiteGraph::wouldCycle([], 10, [11]));
	}

	/**
	 * Prerequisites with a higher ordering still finish in one recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrerequisiteOrderingIsTopological(): void
	{
		$store = new MemoryAwardStore;

		// Q (id 1, ordering 1) requires P (id 2, ordering 2).
		$store->awards[] = (object) [
			'id'             => 1,
			'title'          => 'Q',
			'period_type'    => AwardPeriodType::Lifetime->value,
			'rolling_days'   => null,
			'period_start'   => null,
			'period_end'     => null,
			'match_mode'     => AwardMatchMode::All->value,
			'repeat_mode'    => AwardRepeatMode::Once->value,
			'max_tiers'      => null,
			'streak_periods' => null,
			'revoke_policy'  => AwardRevokePolicy::Auto->value,
			'published'      => 1,
			'ordering'       => 1,
			'criteria'       => [
				$this->criterion(1, AwardCriterionType::HoldsAward->value, null, 1, null, 2),
			],
		];
		$store->awards[] = (object) [
			'id'             => 2,
			'title'          => 'P',
			'period_type'    => AwardPeriodType::Lifetime->value,
			'rolling_days'   => null,
			'period_start'   => null,
			'period_end'     => null,
			'match_mode'     => AwardMatchMode::All->value,
			'repeat_mode'    => AwardRepeatMode::Once->value,
			'max_tiers'      => null,
			'streak_periods' => null,
			'revoke_policy'  => AwardRevokePolicy::Auto->value,
			'published'      => 1,
			'ordering'       => 2,
			'criteria'       => [
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			],
		];
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);

		$engine = $this->engine($store);
		$first = $engine->recompute();
		$awardIds = array_column($store->memberAwards, 'award_id');
		sort($awardIds);

		$this->assertSame([1, 2], $awardIds);
		$this->assertSame(2, $first);
		$this->assertSame(0, $engine->recompute());
	}

	/**
	 * A lifetime prerequisite keeps past calendar-year grants after New Year.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLifetimePrerequisiteAppliesToPastCalendarYears(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null),
			]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 1, null),
				$this->criterion(3, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2025-01-01', 1);
		$store->entries[] = $this->entry(5, 2, '2025-06-01', 1);

		$engine2025 = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2025-12-31 12:00:00',
			'2025-12-31'
		);
		$engine2025->recompute();

		$keys2025 = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2)
			{
				$keys2025[] = $row['period_key'];
			}
		}

		$this->assertSame(['2025'], $keys2025);

		$engine2027 = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2027-01-15 12:00:00',
			'2027-01-15'
		);
		$engine2027->recompute();

		$yearRows = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2)
			{
				$yearRows[(string) $row['period_key']] = (int) $row['state'];
			}
		}

		$this->assertSame(AwardState::Granted->value, $yearRows['2025']);
	}

	/**
	 * A lifetime prerequisite unlocks every calendar year that also meets metrics.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLifetimePrerequisiteUnlocksAcrossCalendarYears(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null),
			]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 1, null),
				$this->criterion(3, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2024-01-01', 1);
		$store->entries[] = $this->entry(5, 2, '2024-06-01', 1);
		$store->entries[] = $this->entry(5, 2, '2026-03-01', 1);

		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-06-15 12:00:00',
			'2026-06-15'
		);
		$engine->recompute();

		$yearRows = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2)
			{
				$yearRows[(string) $row['period_key']] = (int) $row['state'];
			}
		}

		ksort($yearRows);
		$this->assertSame(
			[
				'2024' => AwardState::Granted->value,
				'2026' => AwardState::Granted->value,
			],
			$yearRows
		);
	}

	/**
	 * An unpublished lifetime prerequisite still expands across calendar years.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnpublishedLifetimePrerequisiteKeepsPastCalendarYears(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null),
			]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 1, null),
				$this->criterion(3, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2024-01-01', 1);
		$store->entries[] = $this->entry(5, 2, '2024-06-01', 1);
		$store->entries[] = $this->entry(5, 2, '2026-03-01', 1);

		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-06-15 12:00:00',
			'2026-06-15'
		);
		$engine->recompute();
		$this->assertSame(['2024', '2026'], $this->calendarKeysForAward($store, 2));

		$store->awards[0]->published = 0;
		$engine->recompute();

		$yearRows = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2)
			{
				$yearRows[(string) $row['period_key']] = (int) $row['state'];
			}
		}

		ksort($yearRows);
		$this->assertSame(
			[
				'2024' => AwardState::Granted->value,
				'2026' => AwardState::Granted->value,
			],
			$yearRows
		);
	}

	/**
	 * An unpublished custom prerequisite still covers its window years.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnpublishedCustomPrerequisiteKeepsWindowCalendarYears(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Custom->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null),
			],
			[
				'period_start' => '2024-01-01',
				'period_end'   => '2025-12-31',
			]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 1, null),
				$this->criterion(3, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2024-06-01', 1);
		$store->entries[] = $this->entry(5, 2, '2024-08-01', 1);
		$store->entries[] = $this->entry(5, 2, '2025-08-01', 1);

		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-06-15 12:00:00',
			'2026-06-15'
		);
		$engine->recompute();
		$this->assertSame(['2024', '2025'], $this->calendarKeysForAward($store, 2));

		$store->awards[0]->published = 0;
		$engine->recompute();

		$yearRows = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2)
			{
				$yearRows[(string) $row['period_key']] = (int) $row['state'];
			}
		}

		ksort($yearRows);
		$this->assertSame(
			[
				'2024' => AwardState::Granted->value,
				'2025' => AwardState::Granted->value,
			],
			$yearRows
		);
	}

	/**
	 * A rolling prerequisite must not unlock past calendar years under match-any.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingPrerequisiteDoesNotBackGrantCalendarYearsMatchAny(): void
	{
		$store = $this->rollingPrerequisiteCalendarStore(AwardMatchMode::Any->value);
		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-06-15 12:00:00',
			'2026-06-15'
		);
		$engine->recompute();

		$this->assertSame(['2026'], $this->calendarKeysForAward($store, 2));
	}

	/**
	 * A rolling prerequisite must not unlock past calendar years under match-all.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingPrerequisiteDoesNotBackGrantCalendarYearsMatchAll(): void
	{
		$store = $this->rollingPrerequisiteCalendarStore(AwardMatchMode::All->value);

		// Past-year metric would wrongly pair with a 50-year empty-key expand.
		$store->entries[] = $this->entry(5, 2, '2020-06-01', 1);
		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-06-15 12:00:00',
			'2026-06-15'
		);
		$engine->recompute();

		$this->assertSame(['2026'], $this->calendarKeysForAward($store, 2));
		$this->assertNotContains('2020', $this->calendarKeysForAward($store, 2));
	}

	/**
	 * Membership years use whole anniversary years, not days/365.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembershipYearsUseAnniversaryDiff(): void
	{
		$this->assertSame(3, AwardEngine::anniversaryYears('2020-03-15', '2024-03-14'));
		$this->assertSame(4, AwardEngine::anniversaryYears('2020-03-15', '2024-03-15'));
		$this->assertSame(5, AwardEngine::anniversaryYears('2020-03-15', '2025-03-15'));
		$this->assertSame(0, AwardEngine::anniversaryYears('2026-01-01', '2025-12-31'));

		$store = new MemoryAwardStore;
		$store->profiles[5] = (object) ['member_since' => '2020-06-01'];
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MembershipYears->value, null, 5, null),
			]
		);

		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2025-06-01 12:00:00',
			'2025-06-01'
		);
		$engine->recompute();

		$this->assertCount(1, $store->memberAwards);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Tenure prefers chapter_join_date over member_since, including blank/zero dates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembershipTenurePrefersChapterJoinDate(): void
	{
		$this->assertSame(
			'2023-06-01',
			AwardEngine::membershipTenureStart('2023-06-01', '2018-01-01')
		);
		$this->assertSame(
			'2018-01-01',
			AwardEngine::membershipTenureStart('', '2018-01-01')
		);
		$this->assertSame(
			'2018-01-01',
			AwardEngine::membershipTenureStart('0000-00-00', '2018-01-01')
		);
		$this->assertSame(
			'2018-01-01',
			AwardEngine::membershipTenureStart(null, '2018-01-01')
		);
		$this->assertNull(AwardEngine::membershipTenureStart('', ''));
		$this->assertNull(AwardEngine::membershipTenureStart('0000-00-00', null));

		$store = new MemoryAwardStore;
		$store->profiles[5] = (object) [
			'member_since'       => '2018-01-01',
			'chapter_join_date'  => '2023-06-01',
		];
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MembershipYears->value, null, 5, null),
			]
		);

		// Five years from 2018 would qualify; five years from 2023 does not yet.
		$engine = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2025-06-01 12:00:00',
			'2025-06-01'
		);
		$engine->recompute();
		$this->assertSame([], $store->memberAwards);

		// Boundary day: exactly five anniversary years from chapter_join_date.
		$onAnniversary = new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2028-06-01 12:00:00',
			'2028-06-01'
		);
		$onAnniversary->recompute();
		$this->assertCount(1, $store->memberAwards);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * An award earned only through one event is revoked when that event's
	 * metric support is removed and awards are recomputed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAwardEarnedThroughOneEventIsRevokedAfterEventDelete(): void
	{
		$store = $this->lifetimeDistanceStore(10000.0);
		$engine = $this->engine($store);
		$engine->recompute();
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);

		// Event delete removes the supporting metric entries for that profile.
		$store->entries = [];
		$changed = $engine->recompute();

		$this->assertSame(1, $changed);
		$this->assertSame(AwardState::Revoked->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Manual locked grants survive a revoke of supporting entries.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testManualLockedGrantSurvivesRevoke(): void
	{
		$store = $this->lifetimeDistanceStore(100.0);
		$store->memberAwards[7] = [
			'id'             => 7,
			'award_id'       => 1,
			'profile_id'     => 5,
			'period_key'     => '',
			'tier'           => 1,
			'achieved_value' => 100.0,
			'state'          => AwardState::Granted->value,
			'grant_source'   => AwardGrantSource::Manual->value,
			'is_locked'      => 1,
			'earned_at'      => '2026-01-01 00:00:00',
			'revoked_at'     => null,
			'modified'       => '2026-01-01 00:00:00',
		];
		$store->nextId = 8;
		$store->entries[0]['published'] = -2;

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[7]['state']);
	}

	/**
	 * AwardEngine uses WendfoldLog so skip lines work outside the component boot.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEngineRegistersLogCategoryForSkippedAwards(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardEngine.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString('WendfoldLog::ensureCategory', $source);
		$this->assertStringContainsString("'com_wendfold'", $source);
	}

	/**
	 * Unknown criterion_type on one award is skipped; peers still recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownCriterionTypeSkipsOnlyThatAward(): void
	{
		$this->assertBadEnumSkipsPeer('criterion_type', 99);
	}

	/**
	 * Unknown match_mode on one award is skipped; peers still recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownMatchModeSkipsOnlyThatAward(): void
	{
		$this->assertBadEnumSkipsPeer('match_mode', 99);
	}

	/**
	 * Unknown period_type on one award is skipped; peers still recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownPeriodTypeSkipsOnlyThatAward(): void
	{
		$this->assertBadEnumSkipsPeer('period_type', 99);
	}

	/**
	 * Unknown repeat_mode on one award is skipped; peers still recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownRepeatModeSkipsOnlyThatAward(): void
	{
		$this->assertBadEnumSkipsPeer('repeat_mode', 99);
	}

	/**
	 * Unknown revoke_policy on one award is skipped; peers still recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownRevokePolicySkipsOnlyThatAward(): void
	{
		$this->assertBadEnumSkipsPeer('revoke_policy', 99);
	}

	/**
	 * Sorting with an unknown criterion_type must not throw.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOrderAwardsToleratesUnknownCriterionType(): void
	{
		$store = new MemoryAwardStore;
		$bad = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, 99, 1, 1000, null),
			]
		);
		$good = $this->award(
			2,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			]
		);
		$store->awards[] = $bad;
		$store->awards[] = $good;
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 1000.0);

		$changed = $this->engine($store)->recompute();

		$this->assertGreaterThanOrEqual(1, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$awardIds = array_column($store->memberAwards, 'award_id');
		$this->assertContains(2, $awardIds);
	}

	/**
	 * Rolling award with NULL rolling_days is skipped and does not revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingNullDaysSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Rolling->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 100, null),
			],
			['rolling_days' => null]
		);
		$store->entries[] = $this->entry(5, 1, '2026-10-15', 500);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 500.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertNull($store->memberAwards[1]['revoked_at']);
	}

	/**
	 * Rolling award with zero rolling_days is skipped and does not revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingZeroDaysSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Rolling->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 100, null),
			],
			['rolling_days' => 0]
		);
		$store->entries[] = $this->entry(5, 1, '2026-10-15', 500);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 500.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Valid rolling window includes in-window entries and excludes older ones.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRollingValidWindowGrants(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Rolling->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 100, null),
			],
			['rolling_days' => 30]
		);

		// Today is 2026-10-31; 30-day window starts 2026-10-02.
		$store->entries[] = $this->entry(5, 1, '2026-10-02', 100);
		$store->entries[] = $this->entry(6, 1, '2026-10-01', 100);

		$this->engine($store)->recompute();

		$profiles = array_column($store->memberAwards, 'profile_id');
		sort($profiles);
		$this->assertSame([5], $profiles);
	}

	/**
	 * Custom period with missing dates is skipped without revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomMissingDatesSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Custom->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 100, null),
			],
			['period_start' => null, 'period_end' => null]
		);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 100.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Custom period with start after end is skipped without revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomInvertedDatesSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Custom->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 100, null),
			],
			['period_start' => '2026-12-01', 'period_end' => '2026-01-01']
		);
		$store->entries[] = $this->entry(5, 1, '2026-06-01', 500);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 500.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Valid custom window grants only inside the range.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCustomValidWindowGrants(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Custom->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 50, null),
			],
			['period_start' => '2026-01-01', 'period_end' => '2026-06-30']
		);
		$store->entries[] = $this->entry(5, 1, '2026-03-01', 50);
		$store->entries[] = $this->entry(6, 1, '2026-07-01', 50);

		$this->engine($store)->recompute();

		$profiles = array_column($store->memberAwards, 'profile_id');
		$this->assertSame([5], $profiles);
	}

	/**
	 * Empty criteria skip the award without revoking existing grants.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyCriteriaSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[]
		);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 1.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Metric criterion without metric_id is skipped without revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMissingMetricIdSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, null, 100, null),
			]
		);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 100.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Tiered award without a positive tier_step is skipped without revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTieredWithoutStepSkipsWithoutRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Tiered->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 5000);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 5000.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Zero threshold skips the award and does not grant or revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testZeroThresholdSkipsWithoutGrantOrRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 0, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 100);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 100.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertNull($store->memberAwards[1]['revoked_at']);
		$this->assertCount(1, $store->memberAwards);
	}

	/**
	 * NULL threshold on a legacy row skips without grant or revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNullThresholdSkipsWithoutGrantOrRevoke(): void
	{
		$store = new MemoryAwardStore;
		$criterion = $this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null);
		$criterion->threshold = null;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[$criterion]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 100);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 100.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertCount(1, $store->memberAwards);
	}

	/**
	 * Blank threshold on a legacy row skips without grant or revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBlankThresholdSkipsWithoutGrantOrRevoke(): void
	{
		$store = new MemoryAwardStore;
		$criterion = $this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null);
		$criterion->threshold = '';
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[$criterion]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 100);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame([], $store->memberAwards);
	}

	/**
	 * Negative threshold skips without grant or revoke.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNegativeThresholdSkipsWithoutGrantOrRevoke(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, -5, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 100);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 100.0);

		$changed = $this->engine($store)->recompute();

		$this->assertSame(0, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Non-tiered match-all requires every criterion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNonTieredMatchAllRequiresEveryCriterion(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::All->value, AwardRepeatMode::Once->value, null);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 5);

		$this->engine($store)->recompute();
		$this->assertCount(1, $store->memberAwards);

		$store->entries[1]['value'] = 0;
		$this->engine($store)->recompute();
		$this->assertSame(AwardState::Revoked->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Non-tiered match-any grants when either criterion passes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNonTieredMatchAnyGrantsOnEitherCriterion(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::Any->value, AwardRepeatMode::Once->value, null);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 0);

		$this->engine($store)->recompute();
		$this->assertCount(1, $store->memberAwards);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Tiered-only match-all expands tiers at the threshold boundary.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTieredOnlyMatchAllAtBoundaries(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Tiered->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, 1000),
			],
			['match_mode' => AwardMatchMode::All->value, 'max_tiers' => 5]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 999);
		$store->entries[] = $this->entry(6, 1, '2026-01-01', 1000);
		$store->entries[] = $this->entry(7, 1, '2026-01-01', 1999);
		$store->entries[] = $this->entry(8, 1, '2026-01-01', 2000);

		$this->engine($store)->recompute();

		$byProfile = [];

		foreach ($store->memberAwards as $row)
		{
			$byProfile[(int) $row['profile_id']][] = (int) $row['tier'];
		}

		$this->assertArrayNotHasKey(5, $byProfile);
		$this->assertSame([1], $byProfile[6]);
		$this->assertSame([1], $byProfile[7]);
		sort($byProfile[8]);
		$this->assertSame([1, 2], $byProfile[8]);
	}

	/**
	 * Tiered-only match-any behaves like match-all when there is one criterion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTieredOnlyMatchAnyExpandsTiers(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Tiered->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, 500),
			],
			['match_mode' => AwardMatchMode::Any->value]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 2000);

		$this->engine($store)->recompute();

		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2, 3], $tiers);
	}

	/**
	 * Mixed tiered match-all requires the non-tiered criterion and the tier.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedTieredMatchAllRequiresBoth(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::All->value, AwardRepeatMode::Tiered->value, 1000.0);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 2500);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 0);

		$this->engine($store)->recompute();
		$this->assertSame([], $store->memberAwards);

		$store->entries[1]['value'] = 10;
		$this->engine($store)->recompute();

		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2], $tiers);
	}

	/**
	 * Mixed tiered match-any grants base tier when only the non-tiered criterion passes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedTieredMatchAnyNonTieredOnlyGrantsBaseTier(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::Any->value, AwardRepeatMode::Tiered->value, 1000.0);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 100);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 10);

		$this->engine($store)->recompute();

		$this->assertCount(1, $store->memberAwards);
		$this->assertSame(1, $store->memberAwards[1]['tier']);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
	}

	/**
	 * Mixed tiered match-any expands tiers when the tiered criterion is met.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedTieredMatchAnyTierWhenTierMet(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::Any->value, AwardRepeatMode::Tiered->value, 1000.0);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 3000);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 0);

		$this->engine($store)->recompute();

		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2, 3], $tiers);
	}

	/**
	 * Mixed tiered match-any with both criteria met still expands from the tiered value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedTieredMatchAnyBothMetUsesTier(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::Any->value, AwardRepeatMode::Tiered->value, 1000.0);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 2500);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 10);

		$this->engine($store)->recompute();

		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2], $tiers);
	}

	/**
	 * Mixed tiered match-any grants nothing when neither criterion passes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedTieredMatchAnyNeitherFails(): void
	{
		$store = $this->twoMetricStore(AwardMatchMode::Any->value, AwardRepeatMode::Tiered->value, 1000.0);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 10);
		$store->entries[] = $this->entry(5, 2, '2026-01-01', 0);

		$this->engine($store)->recompute();

		$this->assertSame([], $store->memberAwards);
	}

	/**
	 * max_tiers caps expansion; blank/null means the soft engine cap.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMaxTiersCap(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Tiered->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, 1000),
			],
			['max_tiers' => 2]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 10000);

		$this->engine($store)->recompute();

		$tiers = array_column($store->memberAwards, 'tier');
		sort($tiers);
		$this->assertSame([1, 2], $tiers);
	}

	/**
	 * One bad award beside a good lifetime metric award; bad grants stay put.
	 *
	 * @param   string   $field  Award field to corrupt.
	 * @param   integer  $value  Invalid enum value.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertBadEnumSkipsPeer(string $field, int $value): void
	{
		$store = new MemoryAwardStore;
		$bad = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			]
		);

		if ($field === 'criterion_type')
		{
			$bad->criteria[0]->criterion_type = $value;
		}
		else
		{
			$bad->$field = $value;
		}

		$store->awards[] = $bad;
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 1, 1000, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-01', 1000);
		$store->memberAwards[1] = $this->grantedRow(1, 1, 5, '', 1, 1000.0);

		$changed = $this->engine($store)->recompute();

		$this->assertGreaterThanOrEqual(1, $changed);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertNull($store->memberAwards[1]['revoked_at']);

		$goodGranted = false;

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === 2 && (int) $row['state'] === AwardState::Granted->value)
			{
				$goodGranted = true;
			}
		}

		$this->assertTrue($goodGranted);
	}

	/**
	 * Lifetime distance award fixture.
	 *
	 * @param   float  $value  Distance total.
	 *
	 * @return  MemoryAwardStore
	 *
	 * @since   1.0.0
	 */
	private function lifetimeDistanceStore(float $value): MemoryAwardStore
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 10000, null),
			]
		);
		$store->entries[] = $this->entry(5, 1, '2026-01-15', $value);

		return $store;
	}

	/**
	 * Two-metric award: metric 1 is optionally tiered; metric 2 threshold is 5.
	 *
	 * @param   integer      $matchMode   Match mode.
	 * @param   integer      $repeatMode  Repeat mode.
	 * @param   float|null   $tierStep    Tier step on metric 1, or null.
	 *
	 * @return  MemoryAwardStore
	 *
	 * @since   1.0.0
	 */
	private function twoMetricStore(int $matchMode, int $repeatMode, ?float $tierStep): MemoryAwardStore
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Lifetime->value,
			$repeatMode,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1000, $tierStep),
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 5, null),
			],
			['match_mode' => $matchMode]
		);

		return $store;
	}

	/**
	 * @param   MemoryAwardStore  $store  Store.
	 *
	 * @return  AwardEngine
	 *
	 * @since   1.0.0
	 */
	/**
	 * Rolling prereq plus calendar-year dependent that requires it.
	 *
	 * @param   integer  $matchMode  Match-any or match-all.
	 *
	 * @return  MemoryAwardStore
	 *
	 * @since   1.0.0
	 */
	private function rollingPrerequisiteCalendarStore(int $matchMode): MemoryAwardStore
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->award(
			1,
			AwardPeriodType::Rolling->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(1, AwardCriterionType::MetricTotal->value, 1, 1, null),
			],
			['rolling_days' => 30]
		);
		$store->awards[] = $this->award(
			2,
			AwardPeriodType::CalendarYear->value,
			AwardRepeatMode::Once->value,
			[
				$this->criterion(2, AwardCriterionType::MetricTotal->value, 2, 1, null),
				$this->criterion(3, AwardCriterionType::HoldsAward->value, null, 1, null, 1),
			],
			['match_mode' => $matchMode]
		);

		// Rolling window relative to 2026-06-15.
		$store->entries[] = $this->entry(5, 1, '2026-06-01', 1);
		$store->entries[] = $this->entry(5, 2, '2026-05-01', 1);

		return $store;
	}

	/**
	 * Sorted period keys for granted rows of one award.
	 *
	 * @param   MemoryAwardStore  $store    Store under test.
	 * @param   integer           $awardId  Award id.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function calendarKeysForAward(MemoryAwardStore $store, int $awardId): array
	{
		$keys = [];

		foreach ($store->memberAwards as $row)
		{
			if ((int) $row['award_id'] === $awardId
				&& (int) $row['state'] === AwardState::Granted->value)
			{
				$keys[] = (string) $row['period_key'];
			}
		}

		sort($keys);

		return $keys;
	}

	/**
	 * Award engine fixed to a UTC clock for assertions.
	 *
	 * @param   MemoryAwardStore  $store  In-memory award ledger.
	 *
	 * @return  AwardEngine
	 *
	 * @since   1.0.0
	 */
	private function engine(MemoryAwardStore $store): AwardEngine
	{
		return new AwardEngine(
			$store,
			new RecordingAwardChangeSink,
			'UTC',
			'2026-10-31 12:00:00',
			'2026-10-31'
		);
	}

	/**
	 * @param   integer               $id          Award id.
	 * @param   integer               $periodType  Period type.
	 * @param   integer               $repeatMode  Repeat mode.
	 * @param   list<object>          $criteria    Criteria.
	 * @param   array<string, mixed>  $overrides   Extra award fields.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function award(int $id, int $periodType, int $repeatMode, array $criteria, array $overrides = []): object
	{
		return (object) array_merge(
			[
				'id'             => $id,
				'title'          => 'Award ' . $id,
				'period_type'    => $periodType,
				'rolling_days'   => null,
				'period_start'   => null,
				'period_end'     => null,
				'match_mode'     => AwardMatchMode::All->value,
				'repeat_mode'    => $repeatMode,
				'max_tiers'      => null,
				'streak_periods' => null,
				'revoke_policy'  => AwardRevokePolicy::Auto->value,
				'published'      => 1,
				'criteria'       => $criteria,
			],
			$overrides
		);
	}

	/**
	 * Pre-seeded granted auto row.
	 *
	 * @param   integer  $id         Row id.
	 * @param   integer  $awardId    Award id.
	 * @param   integer  $profileId  Profile id.
	 * @param   string   $periodKey  Period key.
	 * @param   integer  $tier       Tier.
	 * @param   float    $achieved   Achieved value.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	private function grantedRow(
		int $id,
		int $awardId,
		int $profileId,
		string $periodKey,
		int $tier,
		float $achieved
	): array {
		return [
			'id'             => $id,
			'award_id'       => $awardId,
			'profile_id'     => $profileId,
			'period_key'     => $periodKey,
			'tier'           => $tier,
			'achieved_value' => $achieved,
			'state'          => AwardState::Granted->value,
			'grant_source'   => AwardGrantSource::Auto->value,
			'is_locked'      => 0,
			'earned_at'      => '2026-01-01 00:00:00',
			'revoked_at'     => null,
			'modified'       => '2026-01-01 00:00:00',
		];
	}

	/**
	 * @param   integer       $id               Criterion id.
	 * @param   integer       $type             Criterion type.
	 * @param   integer|null  $metricId         Metric id.
	 * @param   float         $threshold        Threshold.
	 * @param   float|null    $tierStep         Tier step.
	 * @param   integer|null  $requiredAwardId  Prerequisite award.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function criterion(
		int $id,
		int $type,
		?int $metricId,
		float $threshold,
		?float $tierStep,
		?int $requiredAwardId = null
	): object {
		return (object) [
			'id'                => $id,
			'criterion_type'    => $type,
			'metric_id'         => $metricId,
			'required_award_id' => $requiredAwardId,
			'threshold'         => $threshold,
			'tier_step'         => $tierStep,
			'ordering'          => 0,
		];
	}

	/**
	 * @param   integer  $profileId  Profile id.
	 * @param   integer  $metricId   Metric id.
	 * @param   string   $day        Entry date.
	 * @param   float    $value      Value.
	 *
	 * @return  array<string, mixed>
	 *
	 * @since   1.0.0
	 */
	private function entry(int $profileId, int $metricId, string $day, float $value): array
	{
		return [
			'profile_id' => $profileId,
			'metric_id'  => $metricId,
			'entry_date' => $day,
			'value'      => $value,
			'published'  => 1,
		];
	}

	/**
	 * Stable checksum of member award rows.
	 *
	 * @param   MemoryAwardStore  $store  Store.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function checksum(MemoryAwardStore $store): string
	{
		$rows = $store->memberAwards;
		ksort($rows);

		return md5(serialize($rows));
	}
}

/**
 * Records award state-change notifications for assertions.
 *
 * @since  1.0.0
 */
final class RecordingAwardChangeSink implements AwardChangeSink
{
	/**
	 * @var    list<object>
	 * @since  1.0.0
	 */
	public array $changes = [];

	/**
	 * @param   list<object>  $changes  Changes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function notify(array $changes): void
	{
		foreach ($changes as $change)
		{
			$this->changes[] = $change;
		}
	}
}
