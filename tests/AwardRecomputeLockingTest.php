<?php

/**
 * Wendfold - Per-award recompute locking and lock-safe revoke/grant races.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Service\AwardChangeSink;
use Wendfold\Component\Wendfold\Administrator\Service\AwardCriterionType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardEngine;
use Wendfold\Component\Wendfold\Administrator\Service\AwardGrantSource;
use Wendfold\Component\Wendfold\Administrator\Service\AwardLockTimeoutException;
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
require_once __DIR__ . '/support/MemoryAwardStore.php';

/**
 * R9/R10: serialize recompute per award; revoke and grantManual stay lock-safe.
 *
 * @since  1.0.0
 */
final class AwardRecomputeLockingTest extends TestCase
{
	/**
	 * recomputeAward locks before any desired-state read.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRecomputeLocksAwardBeforeComputingDesiredState(): void
	{
		$store = new MemoryAwardStore;
		$txOrder = [];
		$store->callObserver = static function (string $method) use (&$txOrder): void {
			$txOrder[] = $method;
		};

		$store->awards[] = $this->lifetimeAward(1);
		$store->entries[] = [
			'profile_id' => 5,
			'metric_id'  => 1,
			'entry_date' => '2026-01-01',
			'value'      => 10000.0,
			'published'  => 1,
		];

		$this->engine($store)->recompute(1);

		$this->assertSame([1], $store->lockCalls);
		$this->assertSame('begin', $txOrder[0] ?? null);
		$this->assertSame('lockAward', $txOrder[1] ?? null);
		$this->assertContains('memberGrants', $txOrder);
		$this->assertLessThan(
			array_search('memberGrants', $txOrder, true),
			array_search('lockAward', $txOrder, true)
		);
		$this->assertContains('metricTotals', $txOrder);
		$this->assertLessThan(
			array_search('metricTotals', $txOrder, true),
			array_search('lockAward', $txOrder, true)
		);
	}

	/**
	 * MetricService, nightly task, and admin Recalculate all go through AwardEngine.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllThreeRecomputeEntryPathsUseAwardEngine(): void
	{
		$metric = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php'
		);
		$task = file_get_contents(
			dirname(__DIR__) . '/plg_task_wendfold/src/Extension/Wendfold.php'
		);
		$admin = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/MemberawardsController.php'
		);
		$engine = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardEngine.php'
		);

		$this->assertIsString($metric);
		$this->assertIsString($task);
		$this->assertIsString($admin);
		$this->assertIsString($engine);

		$this->assertStringContainsString('$this->awards->recompute(null, $profileIds)', $metric);
		$this->assertStringContainsString('AwardEngine::fromDatabase', $metric);
		$this->assertStringContainsString(
			'AwardEngine::fromDatabase($this->getDatabase())->recompute()',
			$task
		);
		$this->assertStringContainsString(
			'AwardEngine::fromDatabase($db)->recompute()',
			$admin
		);
		$this->assertStringContainsString('$this->store->lockAward($awardId)', $engine);
		$this->assertMatchesRegularExpression(
			'/begin\(\);\s+try\s*\{\s+\$this->store->lockAward\(\$awardId\);/s',
			$engine
		);
	}

	/**
	 * A lock timeout skips that award and lets peers continue.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLockTimeoutSkipsAwardAndContinuesPeers(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->lifetimeAward(1);
		$store->awards[] = $this->lifetimeAward(2);
		$store->entries[] = [
			'profile_id' => 5,
			'metric_id'  => 1,
			'entry_date' => '2026-01-01',
			'value'      => 10000.0,
			'published'  => 1,
		];
		$store->lockTimeoutIds[1] = true;

		$changed = $this->engine($store)->recompute();

		$this->assertSame([1, 2], $store->lockCalls);
		$this->assertSame(1, $changed);

		$byAward = [];

		foreach ($store->memberAwards as $row)
		{
			$byAward[(int) $row['award_id']] = (int) $row['state'];
		}

		$this->assertArrayNotHasKey(1, $byAward);
		$this->assertSame(AwardState::Granted->value, $byAward[2] ?? null);
	}

	/**
	 * SqlAwardStore revoke UPDATE re-checks locked/manual/state predicates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRevokeUpdateSqlReChecksLockAndGrantSource(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/SqlAwardStore.php'
		);
		$this->assertIsString($source);

		$method = $this->methodBody($source, 'revokeAbsent');
		$this->assertStringContainsString("quoteName('is_locked') . ' = 0'", $method);
		$this->assertStringContainsString("quoteName('state') . ' = :grantedStill'", $method);
		$this->assertStringContainsString("quoteName('grant_source') . ' = :autoStill'", $method);
		$this->assertStringContainsString('getAffectedRows()', $method);
		$this->assertStringContainsString(': int', $method);
	}

	/**
	 * A row locked between revoke select and update is left untouched.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRevokeLeavesRowUntouchedWhenLockedBetweenSelectAndUpdate(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->lifetimeAward(1);
		$store->memberAwards[1] = [
			'id'             => 1,
			'award_id'       => 1,
			'profile_id'     => 5,
			'period_key'     => '',
			'tier'           => 1,
			'achieved_value' => 10000.0,
			'state'          => AwardState::Granted->value,
			'grant_source'   => AwardGrantSource::Auto->value,
			'is_locked'      => 0,
			'earned_at'      => '2026-01-01 00:00:00',
			'revoked_at'     => null,
			'modified'       => '2026-01-01 00:00:00',
		];
		$store->revokeRaceMutation = ['id' => 1, 'is_locked' => 1];

		$affected = $store->revokeAbsent(
			1,
			[],
			null,
			AwardRevokePolicy::Auto->value,
			'2026-10-31 12:00:00'
		);

		$this->assertSame(0, $affected);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertSame(1, $store->memberAwards[1]['is_locked']);
		$this->assertNull($store->memberAwards[1]['revoked_at']);
	}

	/**
	 * A row converted to manual between revoke select and update is left untouched.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRevokeLeavesRowUntouchedWhenManualBetweenSelectAndUpdate(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->lifetimeAward(1);
		$store->memberAwards[1] = [
			'id'             => 1,
			'award_id'       => 1,
			'profile_id'     => 5,
			'period_key'     => '',
			'tier'           => 1,
			'achieved_value' => 10000.0,
			'state'          => AwardState::Granted->value,
			'grant_source'   => AwardGrantSource::Auto->value,
			'is_locked'      => 0,
			'earned_at'      => '2026-01-01 00:00:00',
			'revoked_at'     => null,
			'modified'       => '2026-01-01 00:00:00',
		];
		$store->revokeRaceMutation = [
			'id'           => 1,
			'grant_source' => AwardGrantSource::Manual->value,
			'is_locked'    => 1,
		];

		$affected = $store->revokeAbsent(
			1,
			[],
			null,
			AwardRevokePolicy::FlagReview->value,
			'2026-10-31 12:00:00'
		);

		$this->assertSame(0, $affected);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state']);
		$this->assertSame(AwardGrantSource::Manual->value, $store->memberAwards[1]['grant_source']);
	}

	/**
	 * grantManual turns a duplicate-key race into an update, not an error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGrantManualDuplicateKeyRaceRetriesAsUpdate(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/MemberawardsModel.php'
		);
		$this->assertIsString($source);

		$grantManual = $this->methodBody($source, 'grantManual');
		$this->assertStringContainsString('isDuplicateKey', $grantManual);
		$this->assertStringContainsString('updateManualGrant', $grantManual);
		$this->assertStringContainsString('duplicate-key retry', $grantManual);

		$helper = $this->methodBody($source, 'isDuplicateKey');
		$this->assertStringContainsString('1062', $helper);
		$this->assertStringContainsString('23000', $helper);

		$db = new GrantManualRaceDatabase;
		$model = new GrantManualModelDouble($db);

		$this->assertTrue($model->grantManual(7, 5, '', 1));
		$this->assertSame(1, $db->insertAttempts);
		$this->assertSame(1, $db->updateAttempts);
		$this->assertSame(
			[
				'state'        => AwardState::Granted->value,
				'is_locked'    => 1,
				'grant_source' => AwardGrantSource::Manual->value,
			],
			$db->finalRow
		);
		$this->assertNull($model->getError());
	}

	/**
	 * SqlAwardStore lockAward uses FOR UPDATE as the exclusive lock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSqlLockAwardUsesForUpdate(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/SqlAwardStore.php'
		);
		$this->assertIsString($source);
		$method = $this->methodBody($source, 'lockAward');
		$this->assertStringContainsString('FOR UPDATE', $method);
		$this->assertStringContainsString('#__wendfold_award_definitions', $method);
		$this->assertStringContainsString('AwardLockTimeoutException', $method);
		$this->assertStringContainsString('isLockWaitTimeout', $method);
	}

	/**
	 * memberGrants takes a locking read after lockAward.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberGrantsIsLockingReadAfterLockAward(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/SqlAwardStore.php'
		);
		$this->assertIsString($source);
		$method = $this->methodBody($source, 'memberGrants');
		$this->assertStringContainsString('FOR UPDATE', $method);
		$this->assertStringContainsString('#__wendfold_member_awards', $method);

		$store = new MemoryAwardStore;
		$txOrder = [];
		$store->callObserver = static function (string $method) use (&$txOrder): void {
			$txOrder[] = $method;
		};
		$store->awards[] = $this->lifetimeAward(1);
		$store->entries[] = [
			'profile_id' => 5,
			'metric_id'  => 1,
			'entry_date' => '2026-01-01',
			'value'      => 10000.0,
			'published'  => 1,
		];

		$this->engine($store)->recompute(1);

		$lockAt = array_search('lockAward', $txOrder, true);
		$grantsAt = array_search('memberGrants', $txOrder, true);
		$this->assertNotFalse($lockAt);
		$this->assertNotFalse($grantsAt);
		$this->assertLessThan($grantsAt, $lockAt);
		$this->assertSame('begin', $txOrder[0] ?? null);
		$this->assertSame('lockAward', $txOrder[1] ?? null);
		$this->assertSame('memberGrants', $txOrder[2] ?? null);
	}

	/**
	 * A 1020 conflict retries once then succeeds.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSnapshotConflictRetriesOnceThenSucceeds(): void
	{
		$store = new MemoryAwardStore;
		$store->awards[] = $this->lifetimeAward(1);
		$store->entries[] = [
			'profile_id' => 5,
			'metric_id'  => 1,
			'entry_date' => '2026-01-01',
			'value'      => 10000.0,
			'published'  => 1,
		];
		$store->conflictThrowsRemaining = 1;
		$store->conflictCode = 1020;

		$changed = $this->engine($store)->recompute(1);

		$this->assertSame(1, $changed);
		$this->assertSame(0, $store->conflictThrowsRemaining);
		$this->assertCount(2, $store->lockCalls);
		$this->assertSame(AwardState::Granted->value, $store->memberAwards[1]['state'] ?? null);
	}

	/**
	 * A 1213 deadlock that persists after one retry is skipped with a WARNING.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPersistentDeadlockSkipsAwardWithWarning(): void
	{
		\Joomla\CMS\Log\Log::$messages = [];
		$store = new MemoryAwardStore;
		$store->awards[] = $this->lifetimeAward(1);
		$store->awards[] = $this->lifetimeAward(2);
		$store->entries[] = [
			'profile_id' => 5,
			'metric_id'  => 1,
			'entry_date' => '2026-01-01',
			'value'      => 10000.0,
			'published'  => 1,
		];
		$store->conflictThrowsRemaining = 100;
		$store->conflictCode = 1213;
		$store->conflictMessage = 'Deadlock found when trying to get lock';

		// Only award 1 conflicts; after two failures, award 1 is skipped.
		$store->callObserver = static function (string $method, mixed ...$args) use ($store): void {
			if ($method === 'lockAward' && (int) ($args[0] ?? 0) === 2)
			{
				$store->conflictThrowsRemaining = 0;
			}
		};

		$changed = $this->engine($store)->recompute();

		$this->assertSame(1, $changed);
		$byAward = [];

		foreach ($store->memberAwards as $row)
		{
			$byAward[(int) $row['award_id']] = (int) $row['state'];
		}

		$this->assertArrayNotHasKey(1, $byAward);
		$this->assertSame(AwardState::Granted->value, $byAward[2] ?? null);
		$this->assertNotSame([], \Joomla\CMS\Log\Log::$messages);
		$this->assertSame(\Joomla\CMS\Log\Log::WARNING, \Joomla\CMS\Log\Log::$messages[0][1]);
		$this->assertStringContainsString(
			'AwardEngine skipped award 1 after retry: transient conflict',
			\Joomla\CMS\Log\Log::$messages[0][0]
		);
		$this->assertTrue(AwardEngine::isRetryableConflict(new \RuntimeException('x', 1020)));
		$this->assertTrue(AwardEngine::isRetryableConflict(new \RuntimeException('x', 1213)));
	}

	/**
	 * Flush after commit runs AwardEngine with lockAward as the first txn statement.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFlushAfterCommitRunsRecomputeWithLockFirst(): void
	{
		require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
		require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
		require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
		require_once __DIR__ . '/support/MemoryMetricEntryStore.php';

		$awardStore = new MemoryAwardStore;
		$txOrder = [];
		$awardStore->callObserver = static function (string $method) use (&$txOrder): void {
			$txOrder[] = $method;
		};
		$awardStore->awards[] = $this->lifetimeAward(1);

		$metricStore = new \Wendfold\Tests\Support\MemoryMetricEntryStore;
		$metricStore->eventDates[4] = '2026-06-15';
		$metricStore->eventStates[4] = 1;
		$metricStore->attendances[10] = (object) [
			'id'         => 10,
			'profile_id' => 5,
			'event_id'   => 4,
			'event_date' => '2026-06-15',
		];
		$metricStore->credits[4] = [
			(object) ['metric_id' => 1, 'value' => '10000.000'],
		];

		$engine = new AwardEngine(
			$awardStore,
			new class implements AwardChangeSink
			{
				/**
				 * @param   list<object>  $changes  Changes.
				 *
				 * @return  void
				 */
				public function notify(array $changes): void
				{
				}
			},
			'UTC',
			'2026-10-31 12:00:00',
			'2026-10-31'
		);
		$service = new \Wendfold\Component\Wendfold\Administrator\Service\MetricService(
			$metricStore,
			7,
			$engine
		);
		$service->deferAwardRecompute(true);

		$txOrder[] = 'outer_begin';
		$service->creditAttendance(4, 10);
		$txOrder[] = 'outer_commit';
		$this->assertNotContains('lockAward', $txOrder);

		$service->flushAwardRecompute();

		$outerCommit = array_search('outer_commit', $txOrder, true);
		$begin = array_search('begin', $txOrder, true);
		$lock = array_search('lockAward', $txOrder, true);
		$grants = array_search('memberGrants', $txOrder, true);
		$this->assertNotFalse($outerCommit);
		$this->assertNotFalse($begin);
		$this->assertNotFalse($lock);
		$this->assertNotFalse($grants);
		$this->assertLessThan($begin, $outerCommit);
		$this->assertSame($begin + 1, $lock);
		$this->assertSame($lock + 1, $grants);
		$this->assertSame([1], $awardStore->lockCalls);
	}

	/**
	 * Attendance add paths defer recompute until after commit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAttendanceAddPathsDeferAndFlushAfterCommit(): void
	{
		$files = [
			dirname(__DIR__) . '/com_wendfold/site/src/Model/CheckinModel.php',
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/AttendanceController.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/MeetingattendanceModel.php',
		];

		foreach ($files as $file)
		{
			$source = file_get_contents($file);
			$this->assertIsString($source);
			$this->assertStringContainsString('deferAwardRecompute(true)', $source);
			$this->assertStringContainsString('flushAwardRecompute()', $source);
			$this->assertStringContainsString('discardDeferredAwardRecompute()', $source);
			$this->assertStringContainsString('EventRowLock::lock', $source);
			$this->assertMatchesRegularExpression(
				'/EventRowLock::lock.*?creditAttendance.*?transactionCommit\(true\);\s+\$metrics->flushAwardRecompute\(\);/s',
				$source
			);
		}

		$removeFiles = [
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/AttendanceController.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/MeetingattendanceModel.php',
		];

		foreach ($removeFiles as $file)
		{
			$source = file_get_contents($file);
			$this->assertIsString($source);
			$this->assertMatchesRegularExpression(
				'/trashAttendance\(\$eventId, \$attendanceId, \$profileId\);\s*.*?transactionCommit\(true\);\s+\$metrics->flushAwardRecompute\(\);/s',
				$source
			);
		}

		$engine = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/AwardEngine.php'
		);
		$this->assertIsString($engine);
		$this->assertStringContainsString(
			'Callers must not nest this inside an outer database transaction',
			$engine
		);
		$this->assertStringContainsString('deferAwardRecompute', $engine);
	}

	/**
	 * Event and activity callers that open a txn around metrics also defer.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventAndActivityPathsDeferAndFlushAfterCommit(): void
	{
		$event = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php'
		);
		$this->assertIsString($event);
		$this->assertSame(3, substr_count($event, 'deferAwardRecompute(true)'));
		$this->assertSame(3, substr_count($event, 'flushAwardRecompute()'));
		$this->assertStringContainsString('discardDeferredAwardRecompute()', $event);
		$this->assertStringContainsString('replaceDeferredAwardProfiles', $event);

		$activityLog = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/ActivitylogModel.php'
		);
		$this->assertIsString($activityLog);
		$this->assertStringContainsString('deferAwardRecompute(true)', $activityLog);
		$this->assertMatchesRegularExpression(
			'/transactionCommit\(true\);\s+\$metrics->flushAwardRecompute\(\);/s',
			$activityLog
		);
		$this->assertStringContainsString('discardDeferredAwardRecompute()', $activityLog);

		$activities = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/ActivitiesModel.php'
		);
		$this->assertIsString($activities);
		$this->assertStringContainsString('deferAwardRecompute(true)', $activities);
		$this->assertStringContainsString('flushAwardRecompute()', $activities);
		$this->assertStringContainsString('replaceDeferredAwardProfiles', $activities);

		$metric = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php'
		);
		$this->assertIsString($metric);
		$this->assertStringContainsString('assertAwardRecomputeOutsideCallerTransaction', $metric);
		$this->assertStringContainsString('openTransactionDepth()', $metric);
	}

	/**
	 * Lifetime metric award definition used by locking tests.
	 *
	 * @param   integer  $id  Award id.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function lifetimeAward(int $id): object
	{
		return (object) [
			'id'             => $id,
			'title'          => 'Award ' . $id,
			'published'      => 1,
			'ordering'       => $id,
			'period_type'    => AwardPeriodType::Lifetime->value,
			'repeat_mode'    => AwardRepeatMode::Once->value,
			'match_mode'     => AwardMatchMode::All->value,
			'revoke_policy'  => AwardRevokePolicy::Auto->value,
			'rolling_days'   => null,
			'period_start'   => null,
			'period_end'     => null,
			'streak_periods' => 0,
			'max_tiers'      => null,
			'criteria'       => [
				(object) [
					'id'                 => $id,
					'award_id'           => $id,
					'criterion_type'     => AwardCriterionType::MetricTotal->value,
					'metric_id'          => 1,
					'threshold'          => 1,
					'tier_step'          => null,
					'required_award_id'  => null,
					'ordering'           => 1,
				],
			],
		];
	}

	/**
	 * @param   MemoryAwardStore  $store  Ledger.
	 *
	 * @return  AwardEngine
	 *
	 * @since   1.0.0
	 */
	private function engine(MemoryAwardStore $store): AwardEngine
	{
		return new AwardEngine(
			$store,
			new class implements AwardChangeSink
			{
				/**
				 * @param   list<object>  $changes  Changes.
				 *
				 * @return  void
				 */
				public function notify(array $changes): void
				{
				}
			},
			'UTC',
			'2026-10-31 12:00:00',
			'2026-10-31'
		);
	}

	/**
	 * Isolate one public/private method body from a PHP source file.
	 *
	 * @param   string  $source  File contents.
	 * @param   string  $name    Method name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function methodBody(string $source, string $name): string
	{
		$pattern = '/(?:public|private|protected) function ' . preg_quote($name, '/')
			. '\b.*?(?=\n\t(?:public|protected|private) function |\n\}\n?\z)/s';

		if (preg_match($pattern, $source, $match) !== 1)
		{
			$this->fail('Could not isolate method ' . $name);
		}

		return $match[0];
	}
}

/**
 * Minimal MemberawardsModel stand-in that exercises grantManual race handling.
 *
 * @since  1.0.0
 */
final class GrantManualModelDouble
{
	/**
	 * @var    string|null
	 * @since  1.0.0
	 */
	private ?string $error = null;

	/**
	 * @param   GrantManualRaceDatabase  $db  Race-simulating database.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private GrantManualRaceDatabase $db)
	{
	}

	/**
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function getError(): ?string
	{
		return $this->error;
	}

	/**
	 * Mirror of MemberawardsModel::grantManual insert/retry path.
	 *
	 * @param   integer  $awardId    Award id.
	 * @param   integer  $profileId  Profile id.
	 * @param   string   $periodKey  Period key.
	 * @param   integer  $tier       Tier.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function grantManual(int $awardId, int $profileId, string $periodKey = '', int $tier = 1): bool
	{
		$existing = $this->db->findExisting($awardId, $profileId, $periodKey, $tier);
		$state = AwardState::Granted->value;
		$sourceManual = AwardGrantSource::Manual->value;
		$sourceAuto = AwardGrantSource::Auto->value;
		$locked = 1;
		$now = '2026-10-31 12:00:00';

		try
		{
			if ($existing !== null)
			{
				$this->db->updateManual(
					(int) $existing['id'],
					(int) $existing['grant_source'],
					$state,
					$sourceAuto,
					$sourceManual,
					$locked,
					$now
				);

				return true;
			}

			$this->db->insertManual($awardId, $profileId, $periodKey, $tier, $state, $sourceManual, $locked, $now);
		}
		catch (\Throwable $error)
		{
			if ($existing === null && $this->isDuplicateKey($error))
			{
				$retryRow = $this->db->findExisting($awardId, $profileId, $periodKey, $tier);

				if ($retryRow !== null)
				{
					$this->db->updateManual(
						(int) $retryRow['id'],
						(int) $retryRow['grant_source'],
						$state,
						$sourceAuto,
						$sourceManual,
						$locked,
						$now
					);

					return true;
				}
			}

			$this->error = 'COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED';

			return false;
		}

		return true;
	}

	/**
	 * @param   \Throwable  $error  Error.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isDuplicateKey(\Throwable $error): bool
	{
		if ((int) $error->getCode() === 1062)
		{
			return true;
		}

		$message = $error->getMessage();

		return str_contains($message, '1062')
			|| str_contains($message, '23000')
			|| str_contains($message, 'Duplicate')
			|| str_contains($message, 'UNIQUE');
	}
}

/**
 * Database double that inserts a racing auto row then fails the first INSERT with 1062.
 *
 * @since  1.0.0
 */
final class GrantManualRaceDatabase
{
	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $insertAttempts = 0;

	/**
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $updateAttempts = 0;

	/**
	 * @var    array<string, int>|null
	 * @since  1.0.0
	 */
	public ?array $finalRow = null;

	/**
	 * @var    array<string, mixed>|null
	 * @since  1.0.0
	 */
	private ?array $row = null;

	/**
	 * @param   integer  $awardId    Award id.
	 * @param   integer  $profileId  Profile id.
	 * @param   string   $periodKey  Period key.
	 * @param   integer  $tier       Tier.
	 *
	 * @return  array<string, mixed>|null
	 *
	 * @since   1.0.0
	 */
	public function findExisting(int $awardId, int $profileId, string $periodKey, int $tier): ?array
	{
		if ($this->row === null)
		{
			return null;
		}

		if ((int) $this->row['award_id'] !== $awardId
			|| (int) $this->row['profile_id'] !== $profileId
			|| (string) $this->row['period_key'] !== $periodKey
			|| (int) $this->row['tier'] !== $tier
		)
		{
			return null;
		}

		return $this->row;
	}

	/**
	 * First insert races with recompute: plant an auto row, then raise 1062.
	 *
	 * @param   integer  $awardId       Award id.
	 * @param   integer  $profileId     Profile id.
	 * @param   string   $periodKey     Period key.
	 * @param   integer  $tier          Tier.
	 * @param   integer  $state         State.
	 * @param   integer  $sourceManual  Manual source.
	 * @param   integer  $locked        Locked flag.
	 * @param   string   $now           Timestamp.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function insertManual(
		int $awardId,
		int $profileId,
		string $periodKey,
		int $tier,
		int $state,
		int $sourceManual,
		int $locked,
		string $now
	): void {
		$this->insertAttempts++;

		if ($this->row === null)
		{
			$this->row = [
				'id'           => 99,
				'award_id'     => $awardId,
				'profile_id'   => $profileId,
				'period_key'   => $periodKey,
				'tier'         => $tier,
				'state'        => AwardState::Granted->value,
				'grant_source' => AwardGrantSource::Auto->value,
				'is_locked'    => 0,
				'modified'     => $now,
			];

			throw new \RuntimeException('Duplicate entry for key', 1062);
		}

		$this->row = [
			'id'           => 99,
			'award_id'     => $awardId,
			'profile_id'   => $profileId,
			'period_key'   => $periodKey,
			'tier'         => $tier,
			'state'        => $state,
			'grant_source' => $sourceManual,
			'is_locked'    => $locked,
			'modified'     => $now,
		];
		$this->finalRow = [
			'state'        => $state,
			'is_locked'    => $locked,
			'grant_source' => $sourceManual,
		];
	}

	/**
	 * @param   integer  $rowId         Row id.
	 * @param   integer  $grantSource   Current source.
	 * @param   integer  $state         Granted.
	 * @param   integer  $sourceAuto    Auto.
	 * @param   integer  $sourceManual  Manual.
	 * @param   integer  $locked        Locked.
	 * @param   string   $now           Timestamp.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function updateManual(
		int $rowId,
		int $grantSource,
		int $state,
		int $sourceAuto,
		int $sourceManual,
		int $locked,
		string $now
	): void {
		$this->updateAttempts++;

		if ($this->row === null || (int) $this->row['id'] !== $rowId)
		{
			throw new \RuntimeException('missing row');
		}

		$this->row['state'] = $state;
		$this->row['is_locked'] = $locked;
		$this->row['modified'] = $now;

		if ($grantSource === $sourceAuto)
		{
			$this->row['grant_source'] = $sourceManual;
		}

		$this->finalRow = [
			'state'        => (int) $this->row['state'],
			'is_locked'    => (int) $this->row['is_locked'],
			'grant_source' => (int) $this->row['grant_source'],
		];
	}
}
