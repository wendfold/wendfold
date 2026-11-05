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

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
// phpcs:disable PEAR.Functions.FunctionCallSignature.CloseBracketLine
// phpcs:disable PEAR.Functions.FunctionCallSignature.Indent

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * MySQL and MariaDB persistence for AwardEngine.
 *
 * Upserts use INSERT ... SELECT ... ON DUPLICATE KEY UPDATE with a select
 * alias. There is no VALUES() function and no INSERT IGNORE. Rows are never
 * deleted on revoke.
 *
 * @since  1.0.0
 */
final class SqlAwardStore implements AwardStore
{
	/**
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private DatabaseInterface $db)
	{
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function begin(): void
	{
		$this->db->transactionStart(true);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function commit(): void
	{
		$this->db->transactionCommit(true);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function rollback(): void
	{
		$this->db->transactionRollback(true);
	}

	/**
	 * Lock the award definition row with SELECT … FOR UPDATE.
	 *
	 * First statement of the recompute transaction: under InnoDB REPEATABLE
	 * READ the consistent snapshot starts after this lock is held, so later
	 * reads cannot race another recompute of the same award.
	 *
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
		$db = $this->db;
		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_award_definitions'))
			->where($db->quoteName('id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);

		// QueryInterface has no forUpdate(); append the clause on the SQL string.
		$sql = ((string) $query) . ' FOR UPDATE';

		try
		{
			$query->setQuery($sql);
			$db->setQuery($query)->loadResult();
		}
		catch (\Throwable $error)
		{
			if ($this->isLockWaitTimeout($error))
			{
				throw new AwardLockTimeoutException($awardId, $error);
			}

			throw $error;
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
		$db = $this->db;
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_award_definitions'))
			->where($db->quoteName('published') . ' = 1')
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		if ($awardId !== null)
		{
			$query->where($db->quoteName('id') . ' = :awardId')
				->bind(':awardId', $awardId, ParameterType::INTEGER);
		}

		$awards = $db->setQuery($query)->loadObjectList() ?: [];

		if ($awards === [])
		{
			return [];
		}

		$ids = array_map(static fn (object $row): int => (int) $row->id, $awards);
		$criteriaByAward = $this->loadCriteria($ids);

		foreach ($awards as $award)
		{
			$award->criteria = $criteriaByAward[(int) $award->id] ?? [];
		}

		return $awards;
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

		$db = $this->db;
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_award_definitions'))
			->where($db->quoteName('id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);
		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
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
		$db = $this->db;
		$periodExpr = $byYear
			? 'CAST(YEAR(' . $db->quoteName('entry_date') . ') AS CHAR(4))'
			: $db->quote('');

		$query = $db->createQuery()
			->select([
				$db->quoteName('profile_id'),
				$periodExpr . ' AS ' . $db->quoteName('period_key'),
				'SUM(' . $db->quoteName('value') . ') AS ' . $db->quoteName('total'),
			])
			->from($db->quoteName('#__wendfold_metric_entries'))
			->where($db->quoteName('metric_id') . ' = :metricId')
			->where($db->quoteName('published') . ' = 1')
			->bind(':metricId', $metricId, ParameterType::INTEGER)
			->group($db->quoteName('profile_id') . ($byYear ? ', YEAR(' . $db->quoteName('entry_date') . ')' : ''));

		if ($startDate !== null)
		{
			$query->where($db->quoteName('entry_date') . ' >= :startDate')
				->bind(':startDate', $startDate);
		}

		if ($endDate !== null)
		{
			$query->where($db->quoteName('entry_date') . ' <= :endDate')
				->bind(':endDate', $endDate);
		}

		$this->bindProfileIds($query, $profileIds);

		return $db->setQuery($query)->loadObjectList() ?: [];
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
		$db = $this->db;
		$joinCol = $db->quoteName('chapter_join_date');
		$sinceCol = $db->quoteName('member_since');
		$zeroDate = $db->quote('0000-00-00');
		$tenure = 'COALESCE(NULLIF(' . $joinCol . ', ' . $zeroDate . '), NULLIF(' . $sinceCol . ', ' . $zeroDate . '))';
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$tenure . ' AS ' . $db->quoteName('tenure_start'),
			])
			->from($db->quoteName('#__wendfold_profiles'))
			->where($tenure . ' IS NOT NULL');

		$this->bindProfileIds($query, $profileIds, 'id');

		$out = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$since = AwardEngine::membershipTenureStart(
				isset($row->tenure_start) ? (string) $row->tenure_start : null,
				null
			);

			if ($since === null)
			{
				continue;
			}

			$out[(int) $row->id] = $since;
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
		$db = $this->db;
		$granted = AwardState::Granted->value;
		$query = $db->createQuery()
			->select([
				$db->quoteName('profile_id'),
				$db->quoteName('period_key'),
			])
			->from($db->quoteName('#__wendfold_member_awards'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->where($db->quoteName('state') . ' = :granted')
			->bind(':awardId', $requiredAwardId, ParameterType::INTEGER)
			->bind(':granted', $granted, ParameterType::INTEGER);

		$this->bindProfileIds($query, $profileIds);

		$out = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$out[(int) $row->profile_id][(string) $row->period_key] = true;
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
	 * Locking read of member_awards rows the recompute may change.
	 *
	 * @param   integer         $awardId     Award id.
	 * @param   integer[]|null  $profileIds  Profiles.
	 *
	 * @return  list<object>
	 *
	 * @since   1.0.0
	 */
	public function memberGrants(int $awardId, ?array $profileIds): array
	{
		$db = $this->db;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('profile_id'),
				$db->quoteName('period_key'),
				$db->quoteName('tier'),
				$db->quoteName('achieved_value'),
				$db->quoteName('state'),
				$db->quoteName('grant_source'),
				$db->quoteName('is_locked'),
			])
			->from($db->quoteName('#__wendfold_member_awards'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->bind(':awardId', $awardId, ParameterType::INTEGER);

		$this->bindProfileIds($query, $profileIds);

		// FOR UPDATE so officer lock/manual grant cannot race the revoke UPDATE.
		$sql = ((string) $query) . ' FOR UPDATE';
		$query->setQuery($sql);

		return $db->setQuery($query)->loadObjectList() ?: [];
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
		if ($desired === [])
		{
			return;
		}

		$db = $this->db;
		$grantSource = AwardGrantSource::Auto->value;
		$granted = AwardState::Granted->value;
		$table = $db->quoteName('#__wendfold_member_awards');
		$d = $db->quoteName('d');

		foreach ($desired as $row)
		{
			$profileId = (int) $row->profile_id;
			$periodKey = (string) $row->period_key;
			$tier = (int) $row->tier;
			$achieved = $row->achieved_value;
			$achievedSql = $achieved === null ? 'NULL' : ':achieved';

			$sql = 'INSERT INTO ' . $table
				. ' ('
				. $db->quoteName('award_id') . ', '
				. $db->quoteName('profile_id') . ', '
				. $db->quoteName('period_key') . ', '
				. $db->quoteName('tier') . ', '
				. $db->quoteName('achieved_value') . ', '
				. $db->quoteName('state') . ', '
				. $db->quoteName('grant_source') . ', '
				. $db->quoteName('is_locked') . ', '
				. $db->quoteName('earned_at') . ', '
				. $db->quoteName('revoked_at') . ', '
				. $db->quoteName('notes') . ', '
				. $db->quoteName('modified')
				. ') SELECT '
				. $d . '.' . $db->quoteName('award_id') . ', '
				. $d . '.' . $db->quoteName('profile_id') . ', '
				. $d . '.' . $db->quoteName('period_key') . ', '
				. $d . '.' . $db->quoteName('tier') . ', '
				. $d . '.' . $db->quoteName('achieved_value') . ', '
				. $d . '.' . $db->quoteName('state') . ', '
				. $d . '.' . $db->quoteName('grant_source') . ', '
				. $d . '.' . $db->quoteName('is_locked') . ', '
				. $d . '.' . $db->quoteName('earned_at') . ', '
				. 'NULL, '
				. $d . '.' . $db->quoteName('notes') . ', '
				. $d . '.' . $db->quoteName('modified')
				. ' FROM (SELECT '
				. ':awardId AS ' . $db->quoteName('award_id') . ', '
				. ':profileId AS ' . $db->quoteName('profile_id') . ', '
				. ':periodKey AS ' . $db->quoteName('period_key') . ', '
				. ':tier AS ' . $db->quoteName('tier') . ', '
				. $achievedSql . ' AS ' . $db->quoteName('achieved_value') . ', '
				. ':granted AS ' . $db->quoteName('state') . ', '
				. ':grantSource AS ' . $db->quoteName('grant_source') . ', '
				. '0 AS ' . $db->quoteName('is_locked') . ', '
				. ':earnedAt AS ' . $db->quoteName('earned_at') . ', '
				. $db->quote('') . ' AS ' . $db->quoteName('notes') . ', '
				. ':modified AS ' . $db->quoteName('modified')
				. ') AS ' . $d
				. ' ON DUPLICATE KEY UPDATE '
				. $db->quoteName('achieved_value') . ' = IF(' . $table . '.' . $db->quoteName('is_locked')
				. ' = 1, ' . $table . '.' . $db->quoteName('achieved_value')
				. ', ' . $d . '.' . $db->quoteName('achieved_value') . '), '
				. $db->quoteName('revoked_at') . ' = IF(' . $table . '.' . $db->quoteName('is_locked')
				. ' = 1 OR ' . $table . '.' . $db->quoteName('state') . ' = 1, '
				. $table . '.' . $db->quoteName('revoked_at') . ', NULL), '
				. $db->quoteName('modified') . ' = IF(' . $table . '.' . $db->quoteName('is_locked')
				. ' = 1 OR ' . $table . '.' . $db->quoteName('state') . ' = 1, '
				. $table . '.' . $db->quoteName('modified') . ', ' . $d . '.' . $db->quoteName('modified') . '), '
				. $db->quoteName('state') . ' = IF(' . $table . '.' . $db->quoteName('is_locked')
				. ' = 1, ' . $table . '.' . $db->quoteName('state')
				. ', ' . $d . '.' . $db->quoteName('state') . ')';

			$query = $db->createQuery();
			$query->bind(':awardId', $awardId, ParameterType::INTEGER);
			$query->bind(':profileId', $profileId, ParameterType::INTEGER);
			$query->bind(':periodKey', $periodKey);
			$query->bind(':tier', $tier, ParameterType::INTEGER);
			$query->bind(':granted', $granted, ParameterType::INTEGER);
			$query->bind(':grantSource', $grantSource, ParameterType::INTEGER);
			$query->bind(':earnedAt', $nowUtc);
			$query->bind(':modified', $nowUtc);

			if ($achieved !== null)
			{
				$achievedString = (string) $achieved;
				$query->bind(':achieved', $achievedString);
			}

			$query->setQuery($sql);
			$db->setQuery($query)->execute();
		}
	}

	/**
	 * @param   integer         $awardId       Award id.
	 * @param   list<object>    $desired       Desired rows.
	 * @param   integer[]|null  $profileIds    Profiles.
	 * @param   integer         $revokePolicy  Policy.
	 * @param   string          $nowUtc        UTC now.
	 *
	 * @return  integer  Rows actually updated.
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
		$db = $this->db;
		$desiredKeys = [];

		foreach ($desired as $row)
		{
			$desiredKeys[(int) $row->profile_id . "\0" . (string) $row->period_key . "\0" . (int) $row->tier] = true;
		}

		$granted = AwardState::Granted->value;
		$auto = AwardGrantSource::Auto->value;
		$newState = $revokePolicy === AwardRevokePolicy::FlagReview->value
			? AwardState::NeedsReview->value
			: AwardState::Revoked->value;

		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('profile_id'),
				$db->quoteName('period_key'),
				$db->quoteName('tier'),
			])
			->from($db->quoteName('#__wendfold_member_awards'))
			->where($db->quoteName('award_id') . ' = :awardId')
			->where($db->quoteName('state') . ' = :granted')
			->where($db->quoteName('grant_source') . ' = :auto')
			->where($db->quoteName('is_locked') . ' = 0')
			->bind(':awardId', $awardId, ParameterType::INTEGER)
			->bind(':granted', $granted, ParameterType::INTEGER)
			->bind(':auto', $auto, ParameterType::INTEGER);

		$this->bindProfileIds($query, $profileIds);

		$revokeIds = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$key = (int) $row->profile_id . "\0" . (string) $row->period_key . "\0" . (int) $row->tier;

			if (!isset($desiredKeys[$key]))
			{
				$revokeIds[] = (int) $row->id;
			}
		}

		if ($revokeIds === [])
		{
			return 0;
		}

		$affected = 0;

		foreach (array_chunk($revokeIds, 500) as $chunk)
		{
			$update = $db->createQuery()
				->update($db->quoteName('#__wendfold_member_awards'))
				->set($db->quoteName('state') . ' = :newState')
				->set($db->quoteName('revoked_at') . ' = :nowUtc')
				->set($db->quoteName('modified') . ' = :modified')
				->whereIn($db->quoteName('id'), $chunk, ParameterType::INTEGER)
				->where($db->quoteName('is_locked') . ' = 0')
				->where($db->quoteName('state') . ' = :grantedStill')
				->where($db->quoteName('grant_source') . ' = :autoStill')
				->bind(':newState', $newState, ParameterType::INTEGER)
				->bind(':nowUtc', $nowUtc)
				->bind(':modified', $nowUtc)
				->bind(':grantedStill', $granted, ParameterType::INTEGER)
				->bind(':autoStill', $auto, ParameterType::INTEGER);

			$db->setQuery($update)->execute();
			$affected += (int) $db->getAffectedRows();
		}

		return $affected;
	}

	/**
	 * Whether a driver error is an InnoDB lock wait timeout (MySQL 1205).
	 *
	 * @param   \Throwable  $error  Driver exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isLockWaitTimeout(\Throwable $error): bool
	{
		if ((int) $error->getCode() === 1205)
		{
			return true;
		}

		$message = $error->getMessage();

		return str_contains($message, '1205')
			|| str_contains(strtolower($message), 'lock wait timeout');
	}

	/**
	 * Load criteria for many awards.
	 *
	 * @param   list<int>  $awardIds  Award ids.
	 *
	 * @return  array<int, list<object>>
	 *
	 * @since   1.0.0
	 */
	private function loadCriteria(array $awardIds): array
	{
		if ($awardIds === [])
		{
			return [];
		}

		$db = $this->db;
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_award_criteria'))
			->whereIn($db->quoteName('award_id'), $awardIds, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');

		$out = [];

		foreach ($db->setQuery($query)->loadObjectList() ?: [] as $row)
		{
			$out[(int) $row->award_id][] = $row;
		}

		return $out;
	}

	/**
	 * Bind an optional profile id list onto a query.
	 *
	 * @param   object          $query       Query.
	 * @param   integer[]|null  $profileIds  Profiles.
	 * @param   string          $column      Column name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function bindProfileIds(object $query, ?array $profileIds, string $column = 'profile_id'): void
	{
		if ($profileIds === null)
		{
			return;
		}

		if ($profileIds === [])
		{
			$query->where('0 = 1');

			return;
		}

		$query->whereIn($this->db->quoteName($column), array_map('intval', $profileIds), ParameterType::INTEGER);
	}
}
