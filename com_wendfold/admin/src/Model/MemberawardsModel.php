<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;
use Wendfold\Component\Wendfold\Administrator\Service\AwardGrantSource;
use Wendfold\Component\Wendfold\Administrator\Service\AwardPeriodType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardRepeatMode;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * Member award grants list and admin mutations.
 *
 * @since  1.0.0
 */
class MemberawardsModel extends ListModel
{
	/**
	 * Bound LIKE search value (must outlive QueryInterface bind-by-ref).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $boundSearch = '';

	/**
	 * @param   array  $config  Configuration.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		if (empty($config['filter_fields']))
		{
			$config['filter_fields'] = [
				'id', 'ma.id',
				'award_title',
				'member_name',
				'period_key', 'ma.period_key',
				'tier', 'ma.tier',
				'state', 'ma.state',
				'earned_at', 'ma.earned_at',
				'is_locked', 'ma.is_locked',
				'presented_on', 'ma.presented_on',
			];
		}

		parent::__construct($config);
	}

	/**
	 * Manually grant a locked award row.
	 *
	 * Existing auto grants are converted to locked manual. Existing manual
	 * grants are re-asserted as locked and granted. Missing rows are inserted.
	 *
	 * @param   integer  $awardId    Award definition id.
	 * @param   integer  $profileId  Member profile id.
	 * @param   string   $periodKey  Period key (empty for lifetime/rolling/custom).
	 * @param   integer  $tier       Tier number.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function grantManual(int $awardId, int $profileId, string $periodKey = '', int $tier = 1): bool
	{
		if ($awardId < 1 || $profileId < 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_IDS'));

			return false;
		}

		$tier = max(1, $tier);
		$periodKey = substr(trim($periodKey), 0, 20);
		$db = $this->getDatabase();

		try
		{
			$awardQuery = $db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('published'),
					$db->quoteName('period_type'),
					$db->quoteName('repeat_mode'),
					]
				)
				->from($db->quoteName('#__wendfold_award_definitions'))
				->where($db->quoteName('id') . ' = :awardId')
				->bind(':awardId', $awardId, ParameterType::INTEGER);
			$award = $db->setQuery($awardQuery)->loadObject();
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards grant: load award failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'));

			return false;
		}

		if (!$award || (int) $award->published !== 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_AWARD_INVALID'));

			return false;
		}

		if ((int) $award->repeat_mode !== AwardRepeatMode::Tiered->value)
		{
			if ($tier > 1)
			{
				$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_TIER_NOT_TIERED'));

				return false;
			}

			$tier = 1;
		}

		try
		{
			$profileQuery = $db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_profiles'))
				->where($db->quoteName('id') . ' = :profileId')
				->bind(':profileId', $profileId, ParameterType::INTEGER);
			$profileExists = (int) $db->setQuery($profileQuery)->loadResult();
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards grant: load profile failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'));

			return false;
		}

		if ($profileExists < 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PROFILE_INVALID'));

			return false;
		}

		$periodType = (int) $award->period_type;

		if ($periodType === AwardPeriodType::CalendarYear->value)
		{
			if (!preg_match('/^\d{4}$/', $periodKey))
			{
				$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PERIOD_KEY'));

				return false;
			}
		}
		else
		{
			$periodKey = '';
		}

		$now = Factory::getDate()->toSql();
		$state = AwardState::Granted->value;
		$sourceManual = AwardGrantSource::Manual->value;
		$sourceAuto = AwardGrantSource::Auto->value;
		$locked = 1;

		try
		{
			$existingQuery = $db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('grant_source'),
					]
				)
				->from($db->quoteName('#__wendfold_member_awards'))
				->where($db->quoteName('award_id') . ' = :awardId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->where($db->quoteName('period_key') . ' = :periodKey')
				->where($db->quoteName('tier') . ' = :tier')
				->bind(':awardId', $awardId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER)
				->bind(':periodKey', $periodKey)
				->bind(':tier', $tier, ParameterType::INTEGER);
			$existing = $db->setQuery($existingQuery)->loadObject();
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards grant: load existing row failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'));

			return false;
		}

		try
		{
			if ($existing)
			{
				$this->updateManualGrant(
					$db,
					(int) $existing->id,
					(int) $existing->grant_source,
					$state,
					$sourceAuto,
					$sourceManual,
					$locked,
					$now
				);

				return true;
			}

			$notes = '';
			$query = $db->createQuery()
				->insert($db->quoteName('#__wendfold_member_awards'))
				->columns([
					$db->quoteName('award_id'),
					$db->quoteName('profile_id'),
					$db->quoteName('period_key'),
					$db->quoteName('tier'),
					$db->quoteName('state'),
					$db->quoteName('grant_source'),
					$db->quoteName('is_locked'),
					$db->quoteName('earned_at'),
					$db->quoteName('notes'),
					$db->quoteName('modified'),
					]
				)
				->values(':awardId, :profileId, :periodKey, :tier, :state, :source, :locked, :earnedAt, :notes, :modified')
				->bind(':awardId', $awardId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER)
				->bind(':periodKey', $periodKey)
				->bind(':tier', $tier, ParameterType::INTEGER)
				->bind(':state', $state, ParameterType::INTEGER)
				->bind(':source', $sourceManual, ParameterType::INTEGER)
				->bind(':locked', $locked, ParameterType::INTEGER)
				->bind(':earnedAt', $now)
				->bind(':notes', $notes)
				->bind(':modified', $now);

			$db->setQuery($query)->execute();
		}
		catch (\Throwable $error)
		{
			// Recompute may insert the unique key between our select and insert.
			if (!$existing && $this->isDuplicateKey($error))
			{
				try
				{
					$retryQuery = $db->createQuery()
						->select([
							$db->quoteName('id'),
							$db->quoteName('grant_source'),
							]
						)
						->from($db->quoteName('#__wendfold_member_awards'))
						->where($db->quoteName('award_id') . ' = :awardId')
						->where($db->quoteName('profile_id') . ' = :profileId')
						->where($db->quoteName('period_key') . ' = :periodKey')
						->where($db->quoteName('tier') . ' = :tier')
						->bind(':awardId', $awardId, ParameterType::INTEGER)
						->bind(':profileId', $profileId, ParameterType::INTEGER)
						->bind(':periodKey', $periodKey)
						->bind(':tier', $tier, ParameterType::INTEGER);
					$retryRow = $db->setQuery($retryQuery)->loadObject();

					if ($retryRow)
					{
						$this->updateManualGrant(
							$db,
							(int) $retryRow->id,
							(int) $retryRow->grant_source,
							$state,
							$sourceAuto,
							$sourceManual,
							$locked,
							$now
						);

						return true;
					}
				}
				catch (\Throwable $retryError)
				{
					WendfoldLog::throwable('Member awards grant: duplicate-key retry failed', $retryError);
					$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'));

					return false;
				}
			}

			WendfoldLog::throwable('Member awards grant: write failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED'));

			return false;
		}

		return true;
	}

	/**
	 * Convert or re-assert an existing row as a locked manual grant.
	 *
	 * @param   object   $db            Database driver.
	 * @param   integer  $rowId         Member award id.
	 * @param   integer  $grantSource   Current grant_source.
	 * @param   integer  $state         Granted state value.
	 * @param   integer  $sourceAuto    Auto grant_source value.
	 * @param   integer  $sourceManual  Manual grant_source value.
	 * @param   integer  $locked        is_locked value (1).
	 * @param   string   $now           UTC modified timestamp.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function updateManualGrant(
		object $db,
		int $rowId,
		int $grantSource,
		int $state,
		int $sourceAuto,
		int $sourceManual,
		int $locked,
		string $now
	): void {
		$sets = [
			$db->quoteName('state') . ' = :state',
			$db->quoteName('is_locked') . ' = :locked',
			$db->quoteName('revoked_at') . ' = NULL',
			$db->quoteName('modified') . ' = :modified',
		];

		if ($grantSource === $sourceAuto)
		{
			$sets[] = $db->quoteName('grant_source') . ' = :source';
		}

		$query = $db->createQuery()
			->update($db->quoteName('#__wendfold_member_awards'))
			->set($sets)
			->where($db->quoteName('id') . ' = :id')
			->bind(':state', $state, ParameterType::INTEGER)
			->bind(':locked', $locked, ParameterType::INTEGER)
			->bind(':modified', $now)
			->bind(':id', $rowId, ParameterType::INTEGER);

		if ($grantSource === $sourceAuto)
		{
			$query->bind(':source', $sourceManual, ParameterType::INTEGER);
		}

		$db->setQuery($query)->execute();
	}

	/**
	 * Whether a driver error is a unique-key collision (MySQL 1062 / SQLSTATE 23000).
	 *
	 * @param   \Throwable  $error  Database exception.
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

	/**
	 * Published award options for the grant form (id => title).
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public function getAwardOptions(): array
	{
		$db = $this->getDatabase();
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('title'),
				]
			)
			->from($db->quoteName('#__wendfold_award_definitions'))
			->where($db->quoteName('published') . ' = :published')
			->bind(':published', $published, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

		try
		{
			$rows = $db->setQuery($query)->loadObjectList() ?: [];
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards: load award options failed', $error);

			return [];
		}

		$options = [];

		foreach ($rows as $row)
		{
			$options[(int) $row->id] = (string) $row->title;
		}

		return $options;
	}

	/**
	 * Member profile options for the grant form (profile_id => name).
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public function getMemberOptions(): array
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('p.id'),
				$db->quoteName('u.name'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'INNER',
				$db->quoteName('#__users', 'u')
					. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->order($db->quoteName('u.name') . ' ASC');

		try
		{
			$rows = $db->setQuery($query)->loadObjectList() ?: [];
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards: load member options failed', $error);

			return [];
		}

		$options = [];

		foreach ($rows as $row)
		{
			$options[(int) $row->id] = (string) $row->name;
		}

		return $options;
	}

	/**
	 * Set is_locked on one grant.
	 *
	 * @param   integer  $id      Grant id.
	 * @param   boolean  $locked  True to lock.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function setLocked(int $id, bool $locked): bool
	{
		if ($id < 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_NO_ID'));

			return false;
		}

		$flag = $locked ? 1 : 0;
		$now = Factory::getDate()->toSql();
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->update($db->quoteName('#__wendfold_member_awards'))
			->set([
				$db->quoteName('is_locked') . ' = :locked',
				$db->quoteName('modified') . ' = :modified',
				]
			)
			->where($db->quoteName('id') . ' = :id')
			->bind(':locked', $flag, ParameterType::INTEGER)
			->bind(':modified', $now)
			->bind(':id', $id, ParameterType::INTEGER);

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards: setLocked failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_LOCK_FAILED'));

			return false;
		}

		return true;
	}

	/**
	 * Set presented_on for one grant.
	 *
	 * @param   integer  $id           Grant id.
	 * @param   string   $presentedOn  Y-m-d or empty to clear.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function setPresented(int $id, string $presentedOn): bool
	{
		if ($id < 1)
		{
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_NO_ID'));

			return false;
		}

		$presentedOn = trim($presentedOn);

		if ($presentedOn !== '')
		{
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $presentedOn))
			{
				$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_DATE'));

				return false;
			}

			$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $presentedOn);

			if ($parsed === false || $parsed->format('Y-m-d') !== $presentedOn)
			{
				$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_DATE'));

				return false;
			}
		}

		$now = Factory::getDate()->toSql();
		$db = $this->getDatabase();
		$sets = [$db->quoteName('modified') . ' = :modified'];

		if ($presentedOn === '')
		{
			$sets[] = $db->quoteName('presented_on') . ' = NULL';
		}
		else
		{
			$sets[] = $db->quoteName('presented_on') . ' = :presentedOn';
		}

		$query = $db->createQuery()
			->update($db->quoteName('#__wendfold_member_awards'))
			->set($sets)
			->where($db->quoteName('id') . ' = :id')
			->bind(':modified', $now)
			->bind(':id', $id, ParameterType::INTEGER);

		if ($presentedOn !== '')
		{
			$query->bind(':presentedOn', $presentedOn);
		}

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $error)
		{
			WendfoldLog::throwable('Member awards: setPresented failed', $error);
			$this->setError(Text::_('COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_FAILED'));

			return false;
		}

		return true;
	}

	/**
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('ma.id'),
				$db->quoteName('ma.award_id'),
				$db->quoteName('ma.profile_id'),
				$db->quoteName('ma.period_key'),
				$db->quoteName('ma.tier'),
				$db->quoteName('ma.achieved_value'),
				$db->quoteName('ma.state'),
				$db->quoteName('ma.grant_source'),
				$db->quoteName('ma.is_locked'),
				$db->quoteName('ma.earned_at'),
				$db->quoteName('ma.revoked_at'),
				$db->quoteName('ma.presented_on'),
				$db->quoteName('ma.notes'),
				$db->quoteName('ad.title', 'award_title'),
				$db->quoteName('u.name', 'member_name'),
				$db->quoteName('p.member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_member_awards', 'ma'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_award_definitions', 'ad')
					. ' ON ' . $db->quoteName('ad.id') . ' = ' . $db->quoteName('ma.award_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__wendfold_profiles', 'p')
					. ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('ma.profile_id')
			)
			->join(
				'INNER',
				$db->quoteName('#__users', 'u')
					. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			);

		$search = trim((string) StateValue::get($this->getState(), 'filter.search', ''));

		if ($search !== '')
		{
			$this->boundSearch = '%' . $db->escape($search, true) . '%';
			$query->where(
				'(' . $db->quoteName('ad.title') . ' LIKE :searchTitle'
				. ' OR ' . $db->quoteName('u.name') . ' LIKE :searchName'
				. ' OR ' . $db->quoteName('p.member_number') . ' LIKE :searchNumber)'
			)
				->bind(':searchTitle', $this->boundSearch)
				->bind(':searchName', $this->boundSearch)
				->bind(':searchNumber', $this->boundSearch);
		}

		$state = StateValue::get($this->getState(), 'filter.state');

		if ($state !== '' && $state !== null)
		{
			$state = (int) $state;
			$query->where($db->quoteName('ma.state') . ' = :state')
				->bind(':state', $state, ParameterType::INTEGER);
		}

		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'ma.earned_at');
		$allowed = [
			'ma.id',
			'award_title',
			'member_name',
			'ma.period_key',
			'ma.tier',
			'ma.state',
			'ma.earned_at',
			'ma.is_locked',
			'ma.presented_on',
		];

		if (!\in_array($orderCol, $allowed, true))
		{
			$orderCol = 'ma.earned_at';
		}

		$orderDir = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
		$query->order($db->escape($orderCol) . ' ' . $orderDir);

		return $query;
	}

	/**
	 * @param   string  $ordering   Default ordering.
	 * @param   string  $direction  Default direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'ma.earned_at', $direction = 'DESC')
	{
		$app = Factory::getApplication();
		$this->setState('filter.search', $app->getInput()->getString('filter_search', ''));
		$this->setState('filter.state', $app->getInput()->getString('filter_state', ''));
		parent::populateState($ordering, $direction);
	}
}
