<?php

/**
 * Wendfold - in-memory award ledger for AwardEngine tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Service\AwardEngine;
use Wendfold\Component\Wendfold\Administrator\Service\AwardGrantSource;
use Wendfold\Component\Wendfold\Administrator\Service\AwardLockTimeoutException;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRevokePolicy;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;
use Wendfold\Component\Wendfold\Administrator\Service\AwardStore;

/**
 * Award store that applies unique (award, profile, period_key, tier) in PHP.
 *
 * @since  1.0.0
 */
final class MemoryAwardStore implements AwardStore
{
	/**
	 * Award definitions with criteria arrays.
	 *
	 * @var    list<object>
	 * @since  1.0.0
	 */
	public array $awards = [];

	/**
	 * Metric entries: profile_id, metric_id, entry_date, value, published.
	 *
	 * @var    list<array<string, mixed>>
	 * @since  1.0.0
	 */
	public array $entries = [];

	/**
	 * Profiles keyed by id with member_since (Y-m-d).
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $profiles = [];

	/**
	 * Member award rows keyed by id.
	 *
	 * @var    array<int, array<string, mixed>>
	 * @since  1.0.0
	 */
	public array $memberAwards = [];

	/**
	 * Next member_awards id.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $nextId = 1;

	/**
	 * Number of member_awards mutations from upsertDesired and revokeAbsent.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $writeCount = 0;

	/**
	 * Open transaction depth.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $tx = 0;

	/**
	 * Snapshot taken at begin for rollback.
	 *
	 * @var    array<int, array<string, mixed>>|null
	 * @since  1.0.0
	 */
	private ?array $snapshot = null;

	/**
	 * Award ids passed to lockAward, in call order.
	 *
	 * @var    list<int>
	 * @since  1.0.0
	 */
	public array $lockCalls = [];

	/**
	 * Award ids that should throw AwardLockTimeoutException from lockAward.
	 *
	 * @var    array<int, true>
	 * @since  1.0.0
	 */
	public array $lockTimeoutIds = [];

	/**
	 * When set, mutate this member_awards id between revoke select and update.
	 *
	 * @var    array{id: int, is_locked?: int, grant_source?: int, state?: int}|null
	 * @since  1.0.0
	 */
	public ?array $revokeRaceMutation = null;

	/**
	 * Optional observer invoked as ($method, ...$args) for lock/read ordering tests.
	 *
	 * @var    callable|null
	 * @since  1.0.0
	 */
	public $callObserver = null;

	/**
	 * Remaining transient conflicts to throw from memberGrants (1020/1213 tests).
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $conflictThrowsRemaining = 0;

	/**
	 * Exception code used when conflictThrowsRemaining > 0.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $conflictCode = 1020;

	/**
	 * Exception message used when conflictThrowsRemaining > 0.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public string $conflictMessage = 'Record has changed since last read in table';

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void
	{
		$this->observe('begin');

		if ($this->tx === 0)
		{
			$this->snapshot = $this->memberAwards;
		}

		$this->tx++;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void
	{
		$this->tx = max(0, $this->tx - 1);

		if ($this->tx === 0)
		{
			$this->snapshot = null;
		}
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void
	{
		if ($this->snapshot !== null)
		{
			$this->memberAwards = $this->snapshot;
		}

		$this->tx = 0;
		$this->snapshot = null;
	}

	/**
	 * @param   integer  $awardId  Award definition id.
	 *
	 * @return  void
	 *
	 * @throws  AwardLockTimeoutException
	 *
	 * @since   1.0.0
	 */
	public function lockAward(int $awardId): void
	{
		if ($this->tx < 1)
		{
			throw new \LogicException('lockAward requires an open transaction.');
		}

		$this->observe('lockAward', $awardId);
		$this->lockCalls[] = $awardId;

		if (isset($this->lockTimeoutIds[$awardId]))
		{
			throw new AwardLockTimeoutException($awardId);
		}
	}

	/**
	 * Notify the optional call observer.
	 *
	 * @param   string  $method   Method name.
	 * @param   mixed   ...$args  Arguments.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function observe(string $method, mixed ...$args): void
	{
		if ($this->callObserver !== null)
		{
			($this->callObserver)($method, ...$args);
		}
	}

	/**
	 * @param   integer|null  $awardId  Award filter.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function publishedAwards(?int $awardId): array
	{
		$out = [];

		foreach ($this->awards as $award)
		{
			if ((int) ($award->published ?? 1) !== 1)
			{
				continue;
			}

			if ($awardId !== null && (int) $award->id !== $awardId)
			{
				continue;
			}

			$out[] = $award;
		}

		return $out;
	}

	/**
	 * @param   integer  $awardId  Award id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function awardDefinition(int $awardId): ?object
	{
		if ($awardId < 1)
		{
			return null;
		}

		foreach ($this->awards as $award)
		{
			if ((int) $award->id === $awardId)
			{
				return $award;
			}
		}

		return null;
	}

	/**
	 * @param   integer         $metricId    Metric id.
	 * @param   integer[]|null  $profileIds  Profiles.
	 * @param   string|null     $startDate   Start.
	 * @param   string|null     $endDate     End.
	 * @param   boolean         $byYear      Group by year.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function metricTotals(
		int $metricId,
		?array $profileIds,
		?string $startDate,
		?string $endDate,
		bool $byYear
	): array {
		$this->observe('metricTotals', $metricId, $profileIds, $startDate, $endDate, $byYear);
		$sums = [];

		foreach ($this->entries as $entry)
		{
			if ((int) $entry['metric_id'] !== $metricId || (int) $entry['published'] !== 1)
			{
				continue;
			}

			$profileId = (int) $entry['profile_id'];

			if ($profileIds !== null && !\in_array($profileId, $profileIds, true))
			{
				continue;
			}

			$day = (string) $entry['entry_date'];

			if ($startDate !== null && $day < $startDate)
			{
				continue;
			}

			if ($endDate !== null && $day > $endDate)
			{
				continue;
			}

			$periodKey = $byYear ? substr($day, 0, 4) : '';
			$key = $periodKey . ':' . $profileId;
			$sums[$key] = [
				'profile_id' => $profileId,
				'period_key' => $periodKey,
				'total'      => ($sums[$key]['total'] ?? 0.0) + (float) $entry['value'],
			];
		}

		$out = [];

		foreach ($sums as $row)
		{
			$out[] = (object) $row;
		}

		return $out;
	}

	/**
	 * @param   integer[]|null  $profileIds  Profiles.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public function membershipSince(?array $profileIds): array
	{
		$out = [];

		foreach ($this->profiles as $profileId => $profile)
		{
			$profileId = (int) $profileId;

			if ($profileIds !== null && !\in_array($profileId, $profileIds, true))
			{
				continue;
			}

			$join = isset($profile->chapter_join_date) ? (string) $profile->chapter_join_date : null;
			$since = isset($profile->member_since) ? (string) $profile->member_since : null;
			$tenure = AwardEngine::membershipTenureStart($join, $since);

			if ($tenure === null)
			{
				continue;
			}

			$out[$profileId] = $tenure;
		}

		return $out;
	}

	/**
	 * @param   integer         $requiredAwardId  Prerequisite award.
	 * @param   integer[]|null  $profileIds       Profiles.
	 *
	 * @return  array<int, array<string, true>>
	 *
	 * @since   1.0.0
	 */
	public function heldAwards(int $requiredAwardId, ?array $profileIds): array
	{
		$out = [];

		foreach ($this->memberAwards as $row)
		{
			if ((int) $row['award_id'] !== $requiredAwardId || (int) $row['state'] !== AwardState::Granted->value)
			{
				continue;
			}

			$profileId = (int) $row['profile_id'];

			if ($profileIds !== null && !\in_array($profileId, $profileIds, true))
			{
				continue;
			}

			$out[$profileId][(string) $row['period_key']] = true;
		}

		return $out;
	}

	/**
	 * @param   integer         $awardId     Award id.
	 * @param   integer[]|null  $profileIds  Profiles.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public function stateSnapshot(int $awardId, ?array $profileIds): array
	{
		$out = [];

		foreach ($this->memberGrants($awardId, $profileIds) as $row)
		{
			$out[(int) $row->id] = (int) $row->state;
		}

		return $out;
	}

	/**
	 * @param   integer         $awardId     Award id.
	 * @param   integer[]|null  $profileIds  Profiles.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function memberGrants(int $awardId, ?array $profileIds): array
	{
		$this->observe('memberGrants', $awardId, $profileIds);

		if ($this->conflictThrowsRemaining > 0)
		{
			$this->conflictThrowsRemaining--;

			throw new \RuntimeException($this->conflictMessage, $this->conflictCode);
		}

		$out = [];

		foreach ($this->memberAwards as $id => $row)
		{
			if ((int) $row['award_id'] !== $awardId)
			{
				continue;
			}

			if ($profileIds !== null && !\in_array((int) $row['profile_id'], $profileIds, true))
			{
				continue;
			}

			$out[] = (object) [
				'id'             => (int) $id,
				'profile_id'     => (int) $row['profile_id'],
				'period_key'     => (string) $row['period_key'],
				'tier'           => (int) $row['tier'],
				'achieved_value' => $row['achieved_value'],
				'state'          => (int) $row['state'],
				'grant_source'   => (int) $row['grant_source'],
				'is_locked'      => (int) $row['is_locked'],
			];
		}

		return $out;
	}

	/**
	 * @param   integer       $awardId  Award id.
	 * @param   list<object>  $desired  Desired rows.
	 * @param   string        $nowUtc   UTC now.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upsertDesired(int $awardId, array $desired, string $nowUtc): void
	{
		foreach ($desired as $row)
		{
			$key = $this->uniqueKey(
				$awardId,
				(int) $row->profile_id,
				(string) $row->period_key,
				(int) $row->tier
			);
			$existingId = $this->findByKey($key);

			if ($existingId === null)
			{
				$id = $this->nextId++;
				$this->memberAwards[$id] = [
					'id'             => $id,
					'award_id'       => $awardId,
					'profile_id'     => (int) $row->profile_id,
					'period_key'     => (string) $row->period_key,
					'tier'           => (int) $row->tier,
					'achieved_value' => $row->achieved_value,
					'state'          => AwardState::Granted->value,
					'grant_source'   => AwardGrantSource::Auto->value,
					'is_locked'      => 0,
					'earned_at'      => $nowUtc,
					'revoked_at'     => null,
					'modified'       => $nowUtc,
				];
				$this->writeCount++;

				continue;
			}

			$existing = &$this->memberAwards[$existingId];

			if ((int) $existing['is_locked'] === 1)
			{
				continue;
			}

			$existing['achieved_value'] = $row->achieved_value;
			$state = (int) $existing['state'];

			if ($state !== AwardState::Granted->value)
			{
				$existing['revoked_at'] = null;
				$existing['modified'] = $nowUtc;
			}

			$existing['state'] = AwardState::Granted->value;
			$this->writeCount++;
		}
	}

	/**
	 * @param   integer         $awardId       Award id.
	 * @param   list<object>    $desired       Desired rows.
	 * @param   integer[]|null  $profileIds    Profiles.
	 * @param   integer         $revokePolicy  Policy.
	 * @param   string          $nowUtc        UTC now.
	 *
	 * @return  integer  Rows actually revoked.
	 *
	 * @since   1.0.0
	 */
	public function revokeAbsent(
		int $awardId,
		array $desired,
		?array $profileIds,
		int $revokePolicy,
		string $nowUtc
	): int {
		$desiredKeys = [];

		foreach ($desired as $row)
		{
			$desiredKeys[$this->uniqueKey(
				$awardId,
				(int) $row->profile_id,
				(string) $row->period_key,
				(int) $row->tier
			)] = true;
		}

		$newState = $revokePolicy === AwardRevokePolicy::FlagReview->value
			? AwardState::NeedsReview->value
			: AwardState::Revoked->value;

		$revokeIds = [];

		foreach ($this->memberAwards as $id => $row)
		{
			if ((int) $row['award_id'] !== $awardId)
			{
				continue;
			}

			if ($profileIds !== null && !\in_array((int) $row['profile_id'], $profileIds, true))
			{
				continue;
			}

			if ((int) $row['is_locked'] === 1)
			{
				continue;
			}

			if ((int) $row['grant_source'] !== AwardGrantSource::Auto->value)
			{
				continue;
			}

			if ((int) $row['state'] !== AwardState::Granted->value)
			{
				continue;
			}

			$key = $this->uniqueKey(
				$awardId,
				(int) $row['profile_id'],
				(string) $row['period_key'],
				(int) $row['tier']
			);

			if (isset($desiredKeys[$key]))
			{
				continue;
			}

			$revokeIds[] = (int) $id;
		}

		// Simulate an officer lock/manual grant between the select and the UPDATE.
		if ($this->revokeRaceMutation !== null)
		{
			$raceId = (int) $this->revokeRaceMutation['id'];

			if (isset($this->memberAwards[$raceId]))
			{
				foreach (['is_locked', 'grant_source', 'state'] as $field)
				{
					if (array_key_exists($field, $this->revokeRaceMutation))
					{
						$this->memberAwards[$raceId][$field] = $this->revokeRaceMutation[$field];
					}
				}
			}

			$this->revokeRaceMutation = null;
		}

		$affected = 0;

		foreach ($revokeIds as $id)
		{
			if (!isset($this->memberAwards[$id]))
			{
				continue;
			}

			$row = &$this->memberAwards[$id];

			// Optimistic re-check matching SqlAwardStore UPDATE predicates.
			if ((int) $row['is_locked'] !== 0
				|| (int) $row['state'] !== AwardState::Granted->value
				|| (int) $row['grant_source'] !== AwardGrantSource::Auto->value
			)
			{
				unset($row);

				continue;
			}

			$row['state'] = $newState;
			$row['revoked_at'] = $nowUtc;
			$row['modified'] = $nowUtc;
			$this->writeCount++;
			$affected++;
			unset($row);
		}

		return $affected;
	}

	/**
	 * Unique key string.
	 *
	 * @param   integer  $awardId    Award id.
	 * @param   integer  $profileId  Profile id.
	 * @param   string   $periodKey  Period key.
	 * @param   integer  $tier       Tier.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function uniqueKey(int $awardId, int $profileId, string $periodKey, int $tier): string
	{
		return $awardId . "\0" . $profileId . "\0" . $periodKey . "\0" . $tier;
	}

	/**
	 * Find an existing row id by unique key.
	 *
	 * @param   string  $key  Unique key.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private function findByKey(string $key): ?int
	{
		foreach ($this->memberAwards as $id => $row)
		{
			$candidate = $this->uniqueKey(
				(int) $row['award_id'],
				(int) $row['profile_id'],
				(string) $row['period_key'],
				(int) $row['tier']
			);

			if ($candidate === $key)
			{
				return (int) $id;
			}
		}

		return null;
	}
}
