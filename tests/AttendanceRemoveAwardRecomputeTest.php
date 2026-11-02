<?php

/**
 * Wendfold - Removing a check-in must revoke awards that drop below threshold.
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
use Wendfold\Component\Wendfold\Administrator\Service\AwardMatchMode;
use Wendfold\Component\Wendfold\Administrator\Service\AwardPeriodType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRecomputer;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRevokePolicy;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Tests\Support\MemoryAwardStore;
use Wendfold\Tests\Support\MemoryMetricEntryStore;

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
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricSource.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricEntryStore.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php';
require_once __DIR__ . '/support/MemoryAwardStore.php';
require_once __DIR__ . '/support/MemoryMetricEntryStore.php';

/**
 * Admin, RC, and meeting remove paths pass profile_id into trashAttendance.
 *
 * @since  1.0.0
 */
final class AttendanceRemoveAwardRecomputeTest extends TestCase
{
	/**
	 * Removing the third check-in revokes a threshold-3 award after flush.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminStyleRemoveRevokesAwardBelowThreshold(): void
	{
		$this->assertRemoveRevokesUnlockedGrant();
	}

	/**
	 * RC-style remove (profile known before delete) revokes the same way.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRcStyleRemoveRevokesAwardBelowThreshold(): void
	{
		$this->assertRemoveRevokesUnlockedGrant();
	}

	/**
	 * Meeting-style remove revokes after flush.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingStyleRemoveRevokesAwardBelowThreshold(): void
	{
		$this->assertRemoveRevokesUnlockedGrant();
	}

	/**
	 * A locked grant is not revoked when the member drops below threshold.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemoveDoesNotRevokeLockedGrant(): void
	{
		$fixture = $this->thresholdThreeFixture();
		$fixture['awardStore']->memberAwards[1]['is_locked'] = 1;

		$result = $this->runRemoveSequence($fixture, 30, 3, 5, true);

		$this->assertTrue($result);
		$this->assertSame(AwardState::Granted->value, $fixture['awardStore']->memberAwards[1]['state']);
		$this->assertSame(1, $fixture['awardStore']->memberAwards[1]['is_locked']);
	}

	/**
	 * A rolled-back remove discards the deferred recompute queue.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRolledBackRemoveQueuesNothing(): void
	{
		$fixture = $this->thresholdThreeFixture();
		$service = $fixture['service'];
		$service->deferAwardRecompute(true);

		// Simulate a failed remove: nothing trashed, then discard.
		$this->assertSame([], $service->deferredAwardProfileIds());
		$service->discardDeferredAwardRecompute();
		$this->assertSame([], $service->deferredAwardProfileIds());

		$service->flushAwardRecompute();
		$this->assertSame(
			AwardState::Granted->value,
			$fixture['awardStore']->memberAwards[1]['state']
		);

		// Trash was never called; three event credits remain counted.
		$published = 0;

		foreach ($fixture['metricStore']->entries as $entry)
		{
			if ((int) $entry['metric_id'] === 2 && (int) $entry['published'] === 1)
			{
				$published++;
			}
		}

		$this->assertSame(3, $published);
	}

	/**
	 * The three production remove paths pass profile_id into trashAttendance.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemoveCallersPassProfileIdToTrashAttendance(): void
	{
		$files = [
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/AttendanceController.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php',
			dirname(__DIR__) . '/com_wendfold/site/src/Model/MeetingattendanceModel.php',
		];

		foreach ($files as $file)
		{
			$source = file_get_contents($file);
			$this->assertIsString($source);
			$this->assertMatchesRegularExpression(
				'/trashAttendance\(\s*\$eventId\s*,\s*\$attendanceId\s*,\s*\$profileId\s*\)/',
				$source,
				'Expected profile id argument in ' . basename($file)
			);
			$this->assertStringContainsString('profile_id', $source);
		}

		$metric = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/MetricService.php'
		);
		$this->assertIsString($metric);
		$this->assertStringContainsString(
			'function trashAttendance(int $eventId, int $attendanceId, int $profileId)',
			$metric
		);
		$this->assertStringNotContainsString(
			'$attendance = $this->store->attendance($attendanceId);',
			$this->methodBody($metric, 'trashAttendance')
		);
	}

	/**
	 * Shared remove→trash→flush assertion for unlocked grants.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertRemoveRevokesUnlockedGrant(): void
	{
		$fixture = $this->thresholdThreeFixture();
		$result = $this->runRemoveSequence($fixture, 30, 3, 5, true);

		$this->assertTrue($result);
		$this->assertSame(AwardState::Revoked->value, $fixture['awardStore']->memberAwards[1]['state']);
	}

	/**
	 * Mirror admin/RC/meeting: delete attendance context, trash metrics, flush.
	 *
	 * @param   array    $fixture       metricStore, awardStore, and service.
	 * @param   integer  $attendanceId  Attendance to remove.
	 * @param   integer  $eventId       Event id.
	 * @param   integer  $profileId     Profile id.
	 * @param   boolean  $commit        True to commit+flush; false to discard.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function runRemoveSequence(
		array $fixture,
		int $attendanceId,
		int $eventId,
		int $profileId,
		bool $commit
	): bool {
		$service = $fixture['service'];
		$metricStore = $fixture['metricStore'];
		$service->deferAwardRecompute(true);

		if (!$commit)
		{
			$service->discardDeferredAwardRecompute();

			return false;
		}

		// Attendance row is gone before trashAttendance, as in production.
		unset($metricStore->attendances[$attendanceId]);
		$changed = $service->trashAttendance($eventId, $attendanceId, $profileId);
		$service->flushAwardRecompute();

		return $changed;
	}

	/**
	 * Member with three event credits holds a threshold-3 lifetime award.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function thresholdThreeFixture(): array
	{
		$metricStore = new MemoryMetricEntryStore;
		$awardStore = new MemoryAwardStore;
		$awardStore->awards[] = (object) [
			'id'             => 1,
			'title'          => 'Three Events',
			'published'      => 1,
			'ordering'       => 1,
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
					'id'                => 1,
					'award_id'          => 1,
					'criterion_type'    => AwardCriterionType::MetricTotal->value,
					'metric_id'         => 2,
					'threshold'         => 3,
					'tier_step'         => null,
					'required_award_id' => null,
					'ordering'          => 1,
				],
			],
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

		$recomputer = new class ($metricStore, $awardStore, $engine) implements AwardRecomputer
		{
			/**
			 * @param   MemoryMetricEntryStore  $metrics  Metric ledger.
			 * @param   MemoryAwardStore        $awards   Award ledger.
			 * @param   AwardEngine             $engine   Engine.
			 */
			public function __construct(
				private MemoryMetricEntryStore $metrics,
				private MemoryAwardStore $awards,
				private AwardEngine $engine
			)
			{
			}

			/**
			 * @param   integer|null    $awardId     Award filter.
			 * @param   integer[]|null  $profileIds  Profiles.
			 *
			 * @return  integer
			 */
			public function recompute(?int $awardId = null, ?array $profileIds = null): int
			{
				$this->awards->entries = [];

				foreach ($this->metrics->entries as $entry)
				{
					$this->awards->entries[] = [
						'profile_id' => (int) $entry['profile_id'],
						'metric_id'  => (int) $entry['metric_id'],
						'entry_date' => (string) $entry['entry_date'],
						'value'      => (float) $entry['value'],
						'published'  => (int) $entry['published'],
					];
				}

				return $this->engine->recompute($awardId, $profileIds);
			}
		};

		$service = new MetricService($metricStore, 7, $recomputer);

		foreach ([1 => 10, 2 => 20, 3 => 30] as $eventId => $attendanceId)
		{
			$metricStore->eventDates[$eventId] = sprintf('2026-0%d-01', $eventId);
			$metricStore->eventStates[$eventId] = 1;
			$metricStore->attendances[$attendanceId] = (object) [
				'id'         => $attendanceId,
				'profile_id' => 5,
				'event_id'   => $eventId,
				'event_date' => $metricStore->eventDates[$eventId],
			];
			$metricStore->credits[$eventId] = [
				(object) ['metric_id' => 2, 'value' => '1.000'],
			];
			$service->creditAttendance($eventId, $attendanceId);
		}

		$recomputer->recompute();
		$this->assertSame(AwardState::Granted->value, $awardStore->memberAwards[1]['state'] ?? null);
		$this->assertSame(AwardGrantSource::Auto->value, $awardStore->memberAwards[1]['grant_source'] ?? null);

		return [
			'metricStore' => $metricStore,
			'awardStore'  => $awardStore,
			'service'     => $service,
		];
	}

	/**
	 * Isolate one method body from a PHP source string.
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
