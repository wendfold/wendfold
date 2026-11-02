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

/**
 * Persistence used only by AwardEngine.
 *
 * @since  1.0.0
 */
interface AwardStore
{
	/**
	 * Begin a transaction for one award recompute.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void;

	/**
	 * Commit the open transaction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void;

	/**
	 * Roll back the open transaction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void;

	/**
	 * Take an exclusive lock for one award before reading desired state.
	 *
	 * Must be the first statement inside the recompute transaction so the
	 * InnoDB REPEATABLE READ snapshot is established after the lock wait.
	 * Throws AwardLockTimeoutException when the lock cannot be acquired.
	 *
	 * @param   integer  $awardId  Award definition id.
	 *
	 * @return  void
	 *
	 * @throws  AwardLockTimeoutException
	 *
	 * @since   1.0.0
	 */
	public function lockAward(int $awardId): void;

	/**
	 * Published award definitions with their criteria.
	 *
	 * @param   integer|null  $awardId  One award, or null for every published award.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function publishedAwards(?int $awardId): array;

	/**
	 * One award definition by id, published or not (no criteria).
	 *
	 * Used when expanding empty period_key holds so an unpublished
	 * prerequisite still contributes its period_type and custom window.
	 *
	 * @param   integer  $awardId  Award id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function awardDefinition(int $awardId): ?object;

	/**
	 * Metric totals from published entries, optionally grouped by calendar year.
	 *
	 * Each row has profile_id, period_key ('' or YYYY), and total.
	 *
	 * @param   integer             $metricId    Metric id.
	 * @param   integer[]|null      $profileIds  Profiles to limit, or null for all.
	 * @param   string|null         $startDate   Inclusive Y-m-d, or null.
	 * @param   string|null         $endDate     Inclusive Y-m-d, or null.
	 * @param   boolean             $byYear      True groups into YYYY period keys.
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
	): array;

	/**
	 * Tenure-start dates for membership-year criteria.
	 *
	 * Uses chapter_join_date when set, otherwise member_since.
	 *
	 * @param   integer[]|null  $profileIds  Profiles to limit, or null for all.
	 *
	 * @return  array<int, string>  profile_id => Y-m-d tenure start
	 *
	 * @since   1.0.0
	 */
	public function membershipSince(?array $profileIds): array;

	/**
	 * Profiles that currently hold a granted award, keyed by profile then period_key.
	 *
	 * @param   integer         $requiredAwardId  Prerequisite award id.
	 * @param   integer[]|null  $profileIds       Profiles to limit, or null for all.
	 *
	 * @return  array<int, array<string, true>>
	 *
	 * @since   1.0.0
	 */
	public function heldAwards(int $requiredAwardId, ?array $profileIds): array;

	/**
	 * Current (id => state) map for an award before or after upsert/revoke.
	 *
	 * @param   integer         $awardId     Award id.
	 * @param   integer[]|null  $profileIds  Profiles to limit, or null for all.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public function stateSnapshot(int $awardId, ?array $profileIds): array;

	/**
	 * Existing member-award rows for one award, for in-memory diffing.
	 *
	 * Each row has id, profile_id, period_key, tier, achieved_value, state,
	 * grant_source, and is_locked. Called after lockAward inside the recompute
	 * transaction; SQL implementations take SELECT … FOR UPDATE so later
	 * upsert/revoke decisions see current committed rows.
	 *
	 * @param   integer         $awardId     Award id.
	 * @param   integer[]|null  $profileIds  Profiles to limit, or null for all.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function memberGrants(int $awardId, ?array $profileIds): array;

	/**
	 * Upsert the desired (profile, period_key, tier, achieved) rows.
	 *
	 * Locked rows are never changed. earned_at is set only on insert.
	 * Callers should pass only rows that need a write.
	 *
	 * @param   integer             $awardId  Award id.
	 * @param   list<object>        $desired  Desired rows.
	 * @param   string              $nowUtc   UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function upsertDesired(int $awardId, array $desired, string $nowUtc): void;

	/**
	 * Revoke auto unlocked grants that are absent from the desired set.
	 *
	 * Rows are never deleted. The UPDATE re-checks is_locked, state, and
	 * grant_source so an officer lock or manual grant between the select and
	 * the update is left untouched.
	 *
	 * @param   integer         $awardId       Award id.
	 * @param   list<object>    $desired       Desired rows.
	 * @param   integer[]|null  $profileIds    Profiles to limit, or null for all.
	 * @param   integer         $revokePolicy  AwardRevokePolicy value.
	 * @param   string          $nowUtc        UTC datetime.
	 *
	 * @return  integer  Rows actually revoked (affected by the UPDATE).
	 *
	 * @since   1.0.0
	 */
	public function revokeAbsent(
		int $awardId,
		array $desired,
		?array $profileIds,
		int $revokePolicy,
		string $nowUtc
	): int;
}
