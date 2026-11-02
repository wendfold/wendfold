<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

// Database column names are snake_case on award/criterion objects.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;

/**
 * Idempotent award recompute shared by the nightly task, Recalculate, and metric hooks.
 *
 * One transaction per award. Desired (profile, period_key, tier) rows are
 * upserted, then unsupported auto unlocked grants are revoked (never deleted).
 * Notifications fire only when state changes. Locked rows are never touched.
 *
 * @since  1.0.0
 */
final class AwardEngine implements AwardRecomputer
{
	/**
	 * Soft cap when max_tiers is null on a tiered award.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private const MAX_TIER_EXPANSION = 1000;

	/**
	 * @param   AwardStore       $store       Persistence.
	 * @param   AwardChangeSink  $sink        State-change notifications.
	 * @param   string           $siteTz      Site time zone for period windows.
	 * @param   string|null      $nowUtc      Fixed UTC clock for tests.
	 * @param   string|null      $todayLocal  Fixed local Y-m-d for tests.
	 *
	 * @since   1.0.0
	 */
	public function __construct(
		private AwardStore $store,
		private AwardChangeSink $sink,
		private string $siteTz,
		private ?string $nowUtc = null,
		private ?string $todayLocal = null
	)
	{
	}

	/**
	 * Engine backed by the component database.
	 *
	 * @param   DatabaseInterface     $db        Database driver.
	 * @param   AwardChangeSink|null  $sink      Optional notifier.
	 * @param   string|null           $siteTz    Site zone; null reads MetricPeriod::siteZone().
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public static function fromDatabase(
		DatabaseInterface $db,
		?AwardChangeSink $sink = null,
		?string $siteTz = null
	): self
	{
		return new self(
			new SqlAwardStore($db),
			$sink ?? new NullAwardChangeSink,
			$siteTz ?? MetricPeriod::siteZone()
		);
	}

	/**
	 * Recompute one award or every published award, optionally for a profile set.
	 *
	 * @param   integer|null    $awardId     Award id, or null for all published awards.
	 * @param   integer[]|null  $profileIds  Profiles to limit, or null for all members.
	 *
	 * @return  integer  Number of member-award rows whose state changed.
	 *
	 * @since   1.0.0
	 */
	public function recompute(?int $awardId = null, ?array $profileIds = null): int
	{
		$profileIds = $this->normaliseProfileIds($profileIds);
		$changes = [];
		$awards = $this->orderAwards($this->store->publishedAwards($awardId));

		foreach ($awards as $award)
		{
			$awardId = (int) ($award->id ?? 0);

			try
			{
				$awardChanges = $this->recomputeAward($award, $profileIds);
			}
			catch (AwardLockTimeoutException $error)
			{
				// Contended lock: skip this award; peers still recompute.
				$this->logLockTimeout($awardId, $error);

				continue;
			}
			catch (\Throwable $error)
			{
				// One broken award must not abort the rest of the batch.
				WendfoldLog::throwable(
					sprintf('AwardEngine recompute failed for award %d', $awardId),
					$error
				);

				continue;
			}

			foreach ($awardChanges as $change)
			{
				$changes[] = $change;
			}
		}

		if ($changes !== [])
		{
			$this->sink->notify($changes);
		}

		return \count($changes);
	}

	/**
	 * Topological order: prerequisites before dependents. Ordering is a tie-break.
	 *
	 * The graph is acyclic (save rejects cycles). Awards with no type-3 edges
	 * keep relative published/ordering sequence among themselves.
	 *
	 * @param   list<object>  $awards  Published awards with criteria.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	private function orderAwards(array $awards): array
	{
		if (\count($awards) < 2)
		{
			return $awards;
		}

		$byId = [];
		$edges = [];
		$indegree = [];

		foreach ($awards as $award)
		{
			$id = (int) $award->id;
			$byId[$id] = $award;
			$edges[$id] = [];
			$indegree[$id] = 0;
		}

		foreach ($awards as $award)
		{
			$id = (int) $award->id;
			$criteria = \is_array($award->criteria ?? null) ? $award->criteria : [];

			foreach ($criteria as $criterion)
			{
				$type = AwardCriterionType::tryFrom((int) ($criterion->criterion_type ?? 0));

				if ($type !== AwardCriterionType::HoldsAward)
				{
					continue;
				}

				$required = (int) ($criterion->required_award_id ?? 0);

				if ($required < 1 || !isset($byId[$required]) || isset($edges[$required][$id]))
				{
					continue;
				}

				// Edge required -> award so prerequisites come first.
				$edges[$required][$id] = $id;
				$indegree[$id]++;
			}
		}

		$queue = [];

		foreach ($awards as $award)
		{
			$id = (int) $award->id;

			if ($indegree[$id] === 0)
			{
				$queue[] = $id;
			}
		}

		$ordered = [];

		while ($queue !== [])
		{
			$id = array_shift($queue);
			$ordered[] = $byId[$id];

			foreach ($edges[$id] as $next)
			{
				$indegree[$next]--;

				if ($indegree[$next] === 0)
				{
					$queue[] = $next;
				}
			}
		}

		// Cycle should be impossible; fall back to input order for any leftover.
		if (\count($ordered) !== \count($awards))
		{
			return $awards;
		}

		return $ordered;
	}

	/**
	 * One award inside its own top-level transaction.
	 *
	 * Callers must not nest this inside an outer database transaction. Metric
	 * writers that open their own txn (check-in, attendance, event save/
	 * publish/delete, activity log save, activity approve/reject) defer via
	 * MetricService::deferAwardRecompute and flush after commit so this method
	 * opens a fresh transaction. Inside that transaction lockAward() is the
	 * first statement, then memberGrants() takes SELECT … FOR UPDATE on the
	 * rows that may change. Concurrent recomputes of the same award serialize;
	 * lock timeouts and snapshot/deadlock conflicts skip this award without
	 * aborting the batch.
	 *
	 * @param   object          $award       Award definition with criteria.
	 * @param   integer[]|null  $profileIds  Profiles to limit.
	 *
	 * @return  list<object>
	 *
	 * @throws  AwardLockTimeoutException
	 *
	 * @since   1.0.0
	 */
	private function recomputeAward(object $award, ?array $profileIds): array
	{
		$awardId = (int) $award->id;

		try
		{
			return $this->recomputeAwardAttempt($award, $profileIds);
		}
		catch (AwardLockTimeoutException $error)
		{
			throw $error;
		}
		catch (\Throwable $error)
		{
			if (!self::isRetryableConflict($error))
			{
				throw $error;
			}

			try
			{
				return $this->recomputeAwardAttempt($award, $profileIds);
			}
			catch (AwardLockTimeoutException $retryLock)
			{
				throw $retryLock;
			}
			catch (\Throwable $retryError)
			{
				if (self::isRetryableConflict($retryError))
				{
					$this->logTransientSkip($awardId, $retryError);

					return [];
				}

				throw $retryError;
			}
		}
	}

	/**
	 * Single attempt at one award's recompute transaction.
	 *
	 * @param   object          $award       Award definition with criteria.
	 * @param   integer[]|null  $profileIds  Profiles to limit.
	 *
	 * @return  list<object>
	 *
	 * @throws  AwardLockTimeoutException
	 *
	 * @since   1.0.0
	 */
	private function recomputeAwardAttempt(object $award, ?array $profileIds): array
	{
		$awardId = (int) $award->id;
		$nowUtc = $this->clockUtc();

		$this->store->begin();

		try
		{
			$this->store->lockAward($awardId);

			$existing = $this->store->memberGrants($awardId, $profileIds);
			$before = [];

			foreach ($existing as $row)
			{
				$before[(int) $row->id] = (int) $row->state;
			}

			$desired = $this->buildDesired($award, $profileIds);

			// Null desired means invalid config: leave existing grants alone (never mass-revoke).
			if ($desired === null)
			{
				$this->store->commit();

				return [];
			}

			$toWrite = $this->desiredNeedingWrite($desired, $existing);
			$this->store->upsertDesired($awardId, $toWrite, $nowUtc);
			$this->store->revokeAbsent(
				$awardId,
				$desired,
				$profileIds,
				(int) $award->revoke_policy,
				$nowUtc
			);
			$after = $this->store->stateSnapshot($awardId, $profileIds);
			$this->store->commit();
		}
		catch (\Throwable $error)
		{
			$this->store->rollback();

			throw $error;
		}

		return $this->diffStates($awardId, $before, $after);
	}

	/**
	 * Log a skipped award when the per-award lock timed out.
	 *
	 * @param   integer                    $awardId  Award id.
	 * @param   AwardLockTimeoutException  $error    Lock timeout.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logLockTimeout(int $awardId, AwardLockTimeoutException $error): void
	{
		$this->logSkipWarning(
			sprintf(
				'AwardEngine skipped award %d: recompute lock timed out (%s)',
				$awardId,
				$error->getMessage()
			)
		);
	}

	/**
	 * Log a skipped award after a snapshot conflict or deadlock retry failed.
	 *
	 * @param   integer     $awardId  Award id.
	 * @param   \Throwable  $error    Conflict or deadlock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logTransientSkip(int $awardId, \Throwable $error): void
	{
		$this->logSkipWarning(
			sprintf(
				'AwardEngine skipped award %d after retry: transient conflict (%s)',
				$awardId,
				$error->getMessage()
			)
		);
	}

	/**
	 * WARNING log that must never abort other awards.
	 *
	 * @param   string  $message  Log line.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logSkipWarning(string $message): void
	{
		if (!\class_exists(Log::class))
		{
			return;
		}

		try
		{
			WendfoldLog::ensureCategory();
			Log::add($message, Log::WARNING, 'com_wendfold');
		}
		catch (\Throwable)
		{
			// Logging must never abort recompute of other awards.
		}
	}

	/**
	 * Snapshot isolation conflict (1020) or deadlock (1213) — retry once then skip.
	 *
	 * @param   \Throwable  $error  Driver or wrapped exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isRetryableConflict(\Throwable $error): bool
	{
		$code = (int) $error->getCode();

		if ($code === 1020 || $code === 1213)
		{
			return true;
		}

		$message = strtolower($error->getMessage());

		return str_contains($message, '1020')
			|| str_contains($message, '1213')
			|| str_contains($message, 'record has changed since last read')
			|| str_contains($message, 'deadlock found');
	}

	/**
	 * Desired rows that need an insert or unlocked update.
	 *
	 * Locked rows and unchanged granted auto rows are skipped so a no-change
	 * recompute does not issue write queries.
	 *
	 * @param   list<object>  $desired   Desired grants.
	 * @param   list<object>  $existing  Current member_awards rows.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	private function desiredNeedingWrite(array $desired, array $existing): array
	{
		$byKey = [];

		foreach ($existing as $row)
		{
			$key = (int) $row->profile_id . "\0" . (string) $row->period_key . "\0" . (int) $row->tier;
			$byKey[$key] = $row;
		}

		$out = [];

		foreach ($desired as $row)
		{
			$key = (int) $row->profile_id . "\0" . (string) $row->period_key . "\0" . (int) $row->tier;
			$current = $byKey[$key] ?? null;

			if ($current === null)
			{
				$out[] = $row;

				continue;
			}

			if ((int) $current->is_locked === 1)
			{
				continue;
			}

			if ((int) $current->state === AwardState::Granted->value
				&& self::achievedEqual($current->achieved_value ?? null, $row->achieved_value ?? null)
			)
			{
				continue;
			}

			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Whether two achieved_value fields match for skip-write decisions.
	 *
	 * @param   mixed  $left   Stored value.
	 * @param   mixed  $right  Desired value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function achievedEqual(mixed $left, mixed $right): bool
	{
		if ($left === null && $right === null)
		{
			return true;
		}

		if ($left === null || $right === null)
		{
			return false;
		}

		return abs((float) $left - (float) $right) < 0.0000001;
	}

	/**
	 * Build the desired (profile_id, period_key, tier, achieved_value) set.
	 *
	 * Returns null when the award configuration is invalid so recompute can
	 * skip the award without revoking existing grants.
	 *
	 * @param   object          $award       Award definition.
	 * @param   integer[]|null  $profileIds  Profiles to limit.
	 *
	 * @return  list<object>|null
	 *
	 * @since   1.0.0
	 */
	private function buildDesired(object $award, ?array $profileIds): ?array
	{
		$reason = $this->invalidConfigReason($award);

		if ($reason !== null)
		{
			$this->logInvalidAward($award, $reason);

			return null;
		}

		$criteria = \is_array($award->criteria ?? null) ? $award->criteria : [];
		$periodType = AwardPeriodType::tryFrom((int) $award->period_type);
		$matchMode = AwardMatchMode::tryFrom((int) $award->match_mode);
		$repeatMode = AwardRepeatMode::tryFrom((int) $award->repeat_mode);

		// InvalidConfigReason already checked these; defend against races.
		if ($periodType === null || $matchMode === null || $repeatMode === null)
		{
			$this->logInvalidAward($award, 'unknown period_type, match_mode, or repeat_mode');

			return null;
		}

		$window = $this->periodWindow($award, $periodType);
		$byYear = $periodType === AwardPeriodType::CalendarYear;

		$evaluations = [];

		foreach ($criteria as $index => $criterion)
		{
			$evaluations[$index] = $this->evaluateCriterion(
				$criterion,
				$profileIds,
				$window['start'],
				$window['end'],
				$byYear
			);
		}

		$combined = $this->combineCriteria($evaluations, $matchMode, $byYear);

		if ($repeatMode === AwardRepeatMode::Once)
		{
			return $this->onceDesired($combined, $award);
		}

		return $this->tieredDesired($combined, $criteria, $award);
	}

	/**
	 * Why an award definition cannot be recomputed, or null when valid.
	 *
	 * Blank/zero rolling days, inverted custom windows, missing criterion
	 * targets, blank/zero/negative thresholds, and broken tier setup are
	 * rejected so recompute never invents an empty window, divides by zero,
	 * or mass-grants from a zero threshold.
	 *
	 * @param   object  $award  Award definition with criteria.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private function invalidConfigReason(object $award): ?string
	{
		$criteria = \is_array($award->criteria ?? null) ? $award->criteria : [];

		if ($criteria === [])
		{
			return 'no criteria';
		}

		$periodType = AwardPeriodType::tryFrom((int) ($award->period_type ?? 0));
		$repeatMode = AwardRepeatMode::tryFrom((int) ($award->repeat_mode ?? -1));
		$matchMode = AwardMatchMode::tryFrom((int) ($award->match_mode ?? 0));
		$revokePolicy = AwardRevokePolicy::tryFrom((int) ($award->revoke_policy ?? 0));

		if ($periodType === null)
		{
			return 'unknown period_type';
		}

		if ($repeatMode === null)
		{
			return 'unknown repeat_mode';
		}

		if ($matchMode === null)
		{
			return 'unknown match_mode';
		}

		if ($revokePolicy === null)
		{
			return 'unknown revoke_policy';
		}

		if ($periodType === AwardPeriodType::Rolling)
		{
			$days = $award->rolling_days ?? null;

			if ($days === null || $days === '' || (int) $days < 1)
			{
				return 'rolling_days must be greater than zero';
			}
		}

		if ($periodType === AwardPeriodType::Custom)
		{
			$start = $this->nullableDay((string) ($award->period_start ?? ''));
			$end = $this->nullableDay((string) ($award->period_end ?? ''));

			if ($start === null || $end === null)
			{
				return 'custom period requires period_start and period_end';
			}

			if ($start > $end)
			{
				return 'custom period_start is after period_end';
			}
		}

		$tieredCount = 0;

		foreach ($criteria as $criterion)
		{
			$type = AwardCriterionType::tryFrom((int) ($criterion->criterion_type ?? 0));

			if ($type === null)
			{
				return 'unknown criterion_type';
			}

			if ($type === AwardCriterionType::MetricTotal
				&& (int) ($criterion->metric_id ?? 0) < 1
			)
			{
				return 'metric criterion missing metric_id';
			}

			if ($type === AwardCriterionType::HoldsAward
				&& (int) ($criterion->required_award_id ?? 0) < 1
			)
			{
				return 'holds-award criterion missing required_award_id';
			}

			$thresholdRaw = $criterion->threshold ?? null;

			if ($thresholdRaw === null || $thresholdRaw === ''
				|| !\is_numeric($thresholdRaw)
				|| (float) $thresholdRaw < 1.0
			)
			{
				return 'threshold must be greater than or equal to one';
			}

			$step = $criterion->tier_step ?? null;

			if ($step !== null && $step !== '' && (float) $step > 0)
			{
				$tieredCount++;
			}
		}

		if ($repeatMode === AwardRepeatMode::Tiered && $tieredCount !== 1)
		{
			return 'tiered award needs exactly one positive tier_step';
		}

		return null;
	}

	/**
	 * Log a skipped award; swallow failures when Log is unavailable (unit tests).
	 *
	 * @param   object  $award   Award definition.
	 * @param   string  $reason  Skip reason.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logInvalidAward(object $award, string $reason): void
	{
		if (!\class_exists(Log::class))
		{
			return;
		}

		try
		{
			WendfoldLog::ensureCategory();
			Log::add(
				sprintf(
					'AwardEngine skipped award %d (%s): %s',
					(int) $award->id,
					(string) ($award->title ?? ''),
					$reason
				),
				Log::WARNING,
				'com_wendfold'
			);
		}
		catch (\Throwable)
		{
			// Logging must never abort recompute of other awards.
		}
	}

	/**
	 * Inclusive entry_date window for a validated award period.
	 *
	 * Caller must run invalidConfigReason() first so rolling_days > 0 and
	 * custom dates are present and ordered.
	 *
	 * @param   object           $award       Award definition.
	 * @param   AwardPeriodType  $periodType  Period kind.
	 *
	 * @return  array{start: ?string, end: ?string}
	 *
	 * @since   1.0.0
	 */
	private function periodWindow(object $award, AwardPeriodType $periodType): array
	{
		$today = $this->todayLocal();

		return match ($periodType)
		{
			AwardPeriodType::Lifetime => ['start' => null, 'end' => null],
			AwardPeriodType::CalendarYear => ['start' => null, 'end' => null],
			AwardPeriodType::Rolling => [
				'start' => $this->shiftDays($today, -(int) $award->rolling_days + 1),
				'end'   => $today,
			],
			AwardPeriodType::Custom => [
				'start' => $this->nullableDay((string) ($award->period_start ?? '')),
				'end'   => $this->nullableDay((string) ($award->period_end ?? '')),
			],
		};
	}

	/**
	 * Evaluate one criterion into period totals / pass flags.
	 *
	 * @param   object          $criterion   Criterion row.
	 * @param   integer[]|null  $profileIds  Profiles to limit.
	 * @param   string|null     $startDate   Inclusive start.
	 * @param   string|null     $endDate     Inclusive end.
	 * @param   boolean         $byYear      Calendar-year grouping.
	 *
	 * @return  array<string, array<int, float>>  period_key => [profile_id => value]
	 *
	 * @since   1.0.0
	 */
	private function evaluateCriterion(
		object $criterion,
		?array $profileIds,
		?string $startDate,
		?string $endDate,
		bool $byYear
	): array
	{
		$type = AwardCriterionType::tryFrom((int) ($criterion->criterion_type ?? 0));

		if ($type === null)
		{
			return [];
		}

		return match ($type)
		{
			AwardCriterionType::MetricTotal => $this->metricMap(
				(int) ($criterion->metric_id ?? 0),
				$profileIds,
				$startDate,
				$endDate,
				$byYear
			),
			AwardCriterionType::MembershipYears => $this->membershipMap($profileIds, $byYear),
			AwardCriterionType::HoldsAward => $this->holdsAwardMap(
				(int) ($criterion->required_award_id ?? 0),
				$profileIds,
				$byYear
			),
		};
	}

	/**
	 * Metric totals keyed by period then profile.
	 *
	 * @param   integer         $metricId    Metric id.
	 * @param   integer[]|null  $profileIds  Profiles.
	 * @param   string|null     $startDate   Start.
	 * @param   string|null     $endDate     End.
	 * @param   boolean         $byYear      Group by year.
	 *
	 * @return  array<string, array<int, float>>
	 *
	 * @since   1.0.0
	 */
	private function metricMap(
		int $metricId,
		?array $profileIds,
		?string $startDate,
		?string $endDate,
		bool $byYear
	): array
	{
		if ($metricId < 1)
		{
			return [];
		}

		$map = [];

		foreach ($this->store->metricTotals($metricId, $profileIds, $startDate, $endDate, $byYear) as $row)
		{
			$periodKey = (string) $row->period_key;
			$profileId = (int) $row->profile_id;
			$map[$periodKey][$profileId] = (float) $row->total;
		}

		return $map;
	}

	/**
	 * Tenure start for membership-year awards.
	 *
	 * Prefers chapter_join_date, falling back to member_since. Blank and
	 * zero dates (0000-00-00) are ignored.
	 *
	 * @param   string|null  $chapterJoinDate  Chapter rejoin day.
	 * @param   string|null  $memberSince      Original member-since day.
	 *
	 * @return  string|null  Y-m-d, or null when neither is usable.
	 *
	 * @since   1.0.0
	 */
	public static function membershipTenureStart(?string $chapterJoinDate, ?string $memberSince): ?string
	{
		foreach ([$chapterJoinDate, $memberSince] as $candidate)
		{
			if ($candidate === null)
			{
				continue;
			}

			$day = trim($candidate);

			if ($day === '' || str_starts_with($day, '0000-00-00'))
			{
				continue;
			}

			$day = substr($day, 0, 10);

			if (MetricPeriod::isDay($day))
			{
				return $day;
			}
		}

		return null;
	}

	/**
	 * Membership years keyed by period then profile.
	 *
	 * Loads tenure start (chapter_join_date, else member_since) once, then
	 * computes whole anniversary years in PHP.
	 *
	 * @param   integer[]|null  $profileIds  Profiles.
	 * @param   boolean         $byYear      Emit one key per year when true.
	 *
	 * @return  array<string, array<int, float>>
	 *
	 * @since   1.0.0
	 */
	private function membershipMap(?array $profileIds, bool $byYear): array
	{
		$today = $this->todayLocal();
		$sinceDates = $this->store->membershipSince($profileIds);

		if (!$byYear)
		{
			$years = [];

			foreach ($sinceDates as $profileId => $since)
			{
				$years[(int) $profileId] = (float) self::anniversaryYears((string) $since, $today);
			}

			return ['' => $years];
		}

		$map = [];
		$currentYear = (int) substr($today, 0, 4);

		foreach ($sinceDates as $profileId => $since)
		{
			$profileId = (int) $profileId;

			for ($year = $currentYear; $year >= $currentYear - 50 && $year >= 1; $year--)
			{
				$asOf = $year === $currentYear ? $today : sprintf('%04d-12-31', $year);
				$map[sprintf('%04d', $year)][$profileId] = (float) self::anniversaryYears((string) $since, $asOf);
			}
		}

		return $map;
	}

	/**
	 * Whole anniversary years between two Y-m-d days.
	 *
	 * @param   string  $since  Member-since day.
	 * @param   string  $asOf   As-of day.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function anniversaryYears(string $since, string $asOf): int
	{
		$start = \DateTimeImmutable::createFromFormat('!Y-m-d', $since);
		$end = \DateTimeImmutable::createFromFormat('!Y-m-d', $asOf);

		if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable || $start > $end)
		{
			return 0;
		}

		return (int) $start->diff($end)->y;
	}

	/**
	 * Prerequisite holds keyed by period then profile (value 1 when held).
	 *
	 * Lifetime, rolling, and custom grants all store period_key as ''. Only a
	 * lifetime prerequisite expands across calendar years. Rolling counts for
	 * the current year only; custom counts for years that overlap its window.
	 *
	 * @param   integer         $requiredAwardId  Prerequisite award.
	 * @param   integer[]|null  $profileIds       Profiles.
	 * @param   boolean         $byYear           Match period keys when true.
	 *
	 * @return  array<string, array<int, float>>
	 *
	 * @since   1.0.0
	 */
	private function holdsAwardMap(int $requiredAwardId, ?array $profileIds, bool $byYear): array
	{
		if ($requiredAwardId < 1)
		{
			return [];
		}

		$held = $this->store->heldAwards($requiredAwardId, $profileIds);
		$map = [];
		$requiredAward = null;

		foreach ($held as $profileId => $periods)
		{
			if (!$byYear)
			{
				if ($periods !== [])
				{
					$map[''][$profileId] = 1.0;
				}

				continue;
			}

			foreach (array_keys($periods) as $periodKey)
			{
				if ($periodKey !== '')
				{
					$map[$periodKey][$profileId] = 1.0;

					continue;
				}

				if ($requiredAward === null)
				{
					// Period type must resolve even when the prerequisite is unpublished.
					$requiredAward = $this->store->awardDefinition($requiredAwardId);
				}

				foreach ($this->calendarYearsForEmptyPeriodHold($requiredAward) as $yearKey)
				{
					$map[$yearKey][$profileId] = 1.0;
				}
			}
		}

		return $map;
	}

	/**
	 * Calendar-year keys covered by a prerequisite grant with period_key ''.
	 *
	 * Rule (narrowest correct): look at the prerequisite award's period_type.
	 * Lifetime → every year back 50 from today. Rolling → today's year only
	 * (the hold is current-window, not historical). Custom → years that
	 * overlap period_start..period_end; broken/missing dates fall back to
	 * today's year. Unknown types also use today's year only.
	 *
	 * @param   object|null  $requiredAward  Prerequisite definition.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function calendarYearsForEmptyPeriodHold(?object $requiredAward): array
	{
		$currentYear = (int) substr($this->todayLocal(), 0, 4);
		$currentKey = sprintf('%04d', $currentYear);

		if ($requiredAward === null)
		{
			return [$currentKey];
		}

		$periodType = AwardPeriodType::tryFrom((int) ($requiredAward->period_type ?? 0));

		if ($periodType === AwardPeriodType::Lifetime)
		{
			$keys = [];

			for ($year = $currentYear; $year >= $currentYear - 50 && $year >= 1; $year--)
			{
				$keys[] = sprintf('%04d', $year);
			}

			return $keys;
		}

		if ($periodType === AwardPeriodType::Custom)
		{
			$start = $this->nullableDay((string) ($requiredAward->period_start ?? ''));
			$end = $this->nullableDay((string) ($requiredAward->period_end ?? ''));

			if ($start !== null && $end !== null && $start <= $end)
			{
				$startYear = (int) substr($start, 0, 4);
				$endYear = (int) substr($end, 0, 4);
				$keys = [];

				for ($year = $startYear; $year <= $endYear; $year++)
				{
					$keys[] = sprintf('%04d', $year);
				}

				return $keys;
			}
		}

		return [$currentKey];
	}

	/**
	 * Combine criterion maps with all/any match mode.
	 *
	 * @param   array<int, array<string, array<int, float>>>  $evaluations  Per criterion maps.
	 * @param   AwardMatchMode                                $matchMode    All or any.
	 * @param   boolean                                       $byYear       Calendar year periods.
	 *
	 * @return  array<string, array<int, array{values: array<int, float>, ok: bool}>>
	 *
	 * @since   1.0.0
	 */
	private function combineCriteria(array $evaluations, AwardMatchMode $matchMode, bool $byYear): array
	{
		$periodKeys = [];

		foreach ($evaluations as $map)
		{
			foreach (array_keys($map) as $periodKey)
			{
				$periodKeys[$periodKey] = $periodKey;
			}
		}

		if ($periodKeys === [] && !$byYear)
		{
			$periodKeys[''] = '';
		}

		$combined = [];

		foreach ($periodKeys as $periodKey)
		{
			$profiles = [];

			foreach ($evaluations as $map)
			{
				foreach (array_keys($map[$periodKey] ?? []) as $profileId)
				{
					$profiles[(int) $profileId] = (int) $profileId;
				}
			}

			foreach ($profiles as $profileId)
			{
				$values = [];
				$passes = [];

				foreach ($evaluations as $index => $map)
				{
					$value = (float) ($map[$periodKey][$profileId] ?? 0.0);
					$values[$index] = $value;

					// Threshold is applied later per criterion; here we only collect values.
					$passes[$index] = $value;
				}

				$combined[$periodKey][$profileId] = [
					'values' => $values,
					'ok'     => true,
				];
			}
		}

		return $combined;
	}

	/**
	 * Once-per-period desired rows after applying thresholds.
	 *
	 * @param   array   $combined  Combined period/profile value maps.
	 * @param   object  $award     Award definition.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	private function onceDesired(array $combined, object $award): array
	{
		$criteria = \is_array($award->criteria ?? null) ? $award->criteria : [];
		$matchMode = AwardMatchMode::tryFrom((int) ($award->match_mode ?? 0));
		$periodType = AwardPeriodType::tryFrom((int) ($award->period_type ?? 0));

		if ($matchMode === null || $periodType === null)
		{
			return [];
		}

		$desired = [];
		$streak = (int) ($award->streak_periods ?? 0);

		$qualifiedByYear = [];

		foreach ($combined as $periodKey => $profiles)
		{
			foreach ($profiles as $profileId => $payload)
			{
				if (!$this->passesThresholds($criteria, $payload['values'], $matchMode))
				{
					continue;
				}

				$achieved = $this->primaryAchieved($criteria, $payload['values']);

				if ($periodType === AwardPeriodType::CalendarYear && $streak > 1)
				{
					$qualifiedByYear[$profileId][(int) $periodKey] = $achieved;

					continue;
				}

				$desired[] = $this->desiredRow((int) $profileId, (string) $periodKey, 1, $achieved);
			}
		}

		if ($periodType === AwardPeriodType::CalendarYear && $streak > 1)
		{
			foreach ($qualifiedByYear as $profileId => $years)
			{
				krsort($years, SORT_NUMERIC);

				foreach ($years as $year => $achieved)
				{
					if ($this->hasStreak($years, (int) $year, $streak))
					{
						$desired[] = $this->desiredRow(
							(int) $profileId,
							sprintf('%04d', (int) $year),
							1,
							$achieved
						);
					}
				}
			}
		}

		return $desired;
	}

	/**
	 * Tiered desired rows from the single tiered criterion.
	 *
	 * Match-all: every non-tiered criterion must pass and the tiered
	 * criterion must meet its threshold; tiers expand from that value.
	 *
	 * Match-any: the award is granted when any criterion passes. When the
	 * tiered criterion is met, tiers expand from it. When only a non-tiered
	 * criterion passes, the member receives the base tier (1).
	 *
	 * @param   array         $combined  Combined period/profile value maps.
	 * @param   list<object>  $criteria  Criteria.
	 * @param   object        $award     Award definition.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	private function tieredDesired(array $combined, array $criteria, object $award): array
	{
		$matchMode = AwardMatchMode::tryFrom((int) ($award->match_mode ?? 0));

		if ($matchMode === null)
		{
			return [];
		}

		$tierIndex = $this->tieredCriterionIndex($criteria);

		if ($tierIndex === null)
		{
			return $this->onceDesired($combined, $award);
		}

		$tierCriterion = $criteria[$tierIndex];
		$threshold = (float) ($tierCriterion->threshold ?? 0);
		$tierStep = (float) ($tierCriterion->tier_step ?? 0);
		$maxTiers = $award->max_tiers !== null && $award->max_tiers !== ''
			? max(1, (int) $award->max_tiers)
			: self::MAX_TIER_EXPANSION;

		// Config validation requires tier_step > 0; keep a safe fallback.
		if ($tierStep <= 0)
		{
			$tierStep = $threshold > 0 ? $threshold : 1.0;
		}

		$desired = [];

		foreach ($combined as $periodKey => $profiles)
		{
			foreach ($profiles as $profileId => $payload)
			{
				$values = $payload['values'];
				$tierValue = (float) ($values[$tierIndex] ?? 0.0);
				$tierMet = $this->meetsThreshold($tierCriterion, $tierValue);
				$nonTiered = $this->nonTieredPassFlags($criteria, $values, $tierIndex);

				if ($matchMode === AwardMatchMode::Any)
				{
					$anyNonTiered = \in_array(true, $nonTiered, true);

					if (!$tierMet && !$anyNonTiered)
					{
						continue;
					}

					if ($tierMet)
					{
						$tiers = $this->tierCount($tierValue, $threshold, $tierStep, $maxTiers);

						for ($tier = 1; $tier <= $tiers; $tier++)
						{
							$desired[] = $this->desiredRow(
								(int) $profileId,
								(string) $periodKey,
								$tier,
								$tierValue
							);
						}

						continue;
					}

					// Only a non-tiered criterion earned the award → base tier.
					$desired[] = $this->desiredRow(
						(int) $profileId,
						(string) $periodKey,
						1,
						$this->primaryAchieved($criteria, $values)
					);

					continue;
				}

				// Match-all: every non-tiered criterion and the tier threshold.
				if (\in_array(false, $nonTiered, true) || !$tierMet)
				{
					continue;
				}

				$tiers = $this->tierCount($tierValue, $threshold, $tierStep, $maxTiers);

				for ($tier = 1; $tier <= $tiers; $tier++)
				{
					$desired[] = $this->desiredRow(
						(int) $profileId,
						(string) $periodKey,
						$tier,
						$tierValue
					);
				}
			}
		}

		return $desired;
	}

	/**
	 * Pass/fail for every criterion except the tiered one.
	 *
	 * @param   list<object>       $criteria   Criteria.
	 * @param   array<int, float>  $values     Per-criterion values.
	 * @param   integer            $tierIndex  Index of the tiered criterion.
	 *
	 * @return  list<bool>
	 *
	 * @since   1.0.0
	 */
	private function nonTieredPassFlags(array $criteria, array $values, int $tierIndex): array
	{
		$results = [];

		foreach ($criteria as $index => $criterion)
		{
			if ((int) $index === $tierIndex)
			{
				continue;
			}

			$results[] = $this->meetsThreshold(
				$criterion,
				(float) ($values[$index] ?? 0.0)
			);
		}

		return $results;
	}

	/**
	 * How many tiers a value unlocks (at least 1 when called for a met threshold).
	 *
	 * @param   float    $value      Achieved tiered value.
	 * @param   float    $threshold  Base threshold.
	 * @param   float    $tierStep   Positive step size.
	 * @param   integer  $maxTiers   Cap.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function tierCount(float $value, float $threshold, float $tierStep, int $maxTiers): int
	{
		$tiers = 1 + (int) floor(($value - $threshold) / $tierStep);

		return min(max(1, $tiers), $maxTiers);
	}

	/**
	 * Whether criterion values pass thresholds under the match mode.
	 *
	 * @param   list<object>       $criteria   Criteria.
	 * @param   array<int, float>  $values     Per-criterion values.
	 * @param   AwardMatchMode     $matchMode  All or any.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function passesThresholds(
		array $criteria,
		array $values,
		AwardMatchMode $matchMode
	): bool
	{
		$results = [];

		foreach ($criteria as $index => $criterion)
		{
			$results[] = $this->meetsThreshold(
				$criterion,
				(float) ($values[$index] ?? 0.0)
			);
		}

		if ($results === [])
		{
			return false;
		}

		if ($matchMode === AwardMatchMode::Any)
		{
			return \in_array(true, $results, true);
		}

		return !\in_array(false, $results, true);
	}

	/**
	 * Whether one criterion value meets its threshold (holds-award is binary).
	 *
	 * @param   object  $criterion  Criterion row.
	 * @param   float   $value      Evaluated value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function meetsThreshold(object $criterion, float $value): bool
	{
		$type = AwardCriterionType::tryFrom((int) ($criterion->criterion_type ?? 0));

		if ($type === null)
		{
			return false;
		}

		if ($type === AwardCriterionType::HoldsAward)
		{
			return $value >= 1.0;
		}

		$threshold = (float) $criterion->threshold;

		return $value + 0.0000001 >= $threshold;
	}

	/**
	 * Achieved value shown on a single-criterion (or first metric) grant.
	 *
	 * @param   list<object>       $criteria  Criteria.
	 * @param   array<int, float>  $values    Values.
	 *
	 * @return  float|null
	 *
	 * @since   1.0.0
	 */
	private function primaryAchieved(array $criteria, array $values): ?float
	{
		if (\count($criteria) === 1)
		{
			return (float) ($values[0] ?? 0.0);
		}

		foreach ($criteria as $index => $criterion)
		{
			$type = AwardCriterionType::tryFrom((int) ($criterion->criterion_type ?? 0));

			if ($type === AwardCriterionType::MetricTotal)
			{
				return (float) ($values[$index] ?? 0.0);
			}
		}

		return isset($values[0]) ? (float) $values[0] : null;
	}

	/**
	 * Index of the criterion that carries a positive tier_step, if any.
	 *
	 * @param   list<object>  $criteria  Criteria.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private function tieredCriterionIndex(array $criteria): ?int
	{
		foreach ($criteria as $index => $criterion)
		{
			if ($criterion->tier_step !== null && $criterion->tier_step !== '' && (float) $criterion->tier_step > 0)
			{
				return (int) $index;
			}
		}

		return null;
	}

	/**
	 * Whether $year ends a streak of $streak consecutive qualified years.
	 *
	 * @param   array<int, float>  $years   year => achieved.
	 * @param   integer            $year    Candidate end year.
	 * @param   integer            $streak  Required length.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function hasStreak(array $years, int $year, int $streak): bool
	{
		for ($offset = 0; $offset < $streak; $offset++)
		{
			if (!isset($years[$year - $offset]))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Desired row object.
	 *
	 * @param   integer      $profileId      Profile id.
	 * @param   string       $periodKey      '' or YYYY.
	 * @param   integer      $tier           Tier number.
	 * @param   float|null   $achievedValue  Achieved total.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	private function desiredRow(int $profileId, string $periodKey, int $tier, ?float $achievedValue): object
	{
		return (object) [
			'profile_id'     => $profileId,
			'period_key'     => $periodKey,
			'tier'           => $tier,
			'achieved_value' => $achievedValue,
		];
	}

	/**
	 * Diff before/after snapshots into change objects.
	 *
	 * @param   integer          $awardId  Award id.
	 * @param   array<int, int>  $before   id => state.
	 * @param   array<int, int>  $after    id => state.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	private function diffStates(int $awardId, array $before, array $after): array
	{
		$changes = [];
		$ids = $before + $after;

		foreach (array_keys($ids) as $id)
		{
			$from = $before[$id] ?? null;
			$to = $after[$id] ?? null;

			if ($from === $to)
			{
				continue;
			}

			$changes[] = (object) [
				'id'         => (int) $id,
				'award_id'   => $awardId,
				'from_state' => $from,
				'to_state'   => $to,
			];
		}

		return $changes;
	}

	/**
	 * Normalise and de-duplicate profile ids.
	 *
	 * @param   integer[]|null  $profileIds  Raw ids.
	 *
	 * @return  integer[]|null
	 *
	 * @since   1.0.0
	 */
	private function normaliseProfileIds(?array $profileIds): ?array
	{
		if ($profileIds === null)
		{
			return null;
		}

		$clean = [];

		foreach ($profileIds as $profileId)
		{
			$profileId = (int) $profileId;

			if ($profileId > 0)
			{
				$clean[$profileId] = $profileId;
			}
		}

		return array_values($clean);
	}

	/**
	 * UTC clock used for earned_at / revoked_at / modified.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function clockUtc(): string
	{
		if ($this->nowUtc !== null)
		{
			return $this->nowUtc;
		}

		return Factory::getDate('now', 'UTC')->format('Y-m-d H:i:s', false);
	}

	/**
	 * Local calendar day in the site zone.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function todayLocal(): string
	{
		if ($this->todayLocal !== null)
		{
			return $this->todayLocal;
		}

		return Factory::getDate('now', $this->siteTz)->format('Y-m-d', true);
	}

	/**
	 * Shift a Y-m-d day by a signed day count.
	 *
	 * @param   string   $day   Y-m-d.
	 * @param   integer  $days  Signed day delta.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function shiftDays(string $day, int $days): string
	{
		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);

		if (!$date instanceof \DateTimeImmutable)
		{
			return $day;
		}

		return $date->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
	}

	/**
	 * Valid Y-m-d or null.
	 *
	 * @param   string  $day  Candidate.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private function nullableDay(string $day): ?string
	{
		$day = trim($day);

		return MetricPeriod::isDay($day) ? $day : null;
	}
}
