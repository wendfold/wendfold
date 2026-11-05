<?php

/**
 * Wendfold - Profile Edit Model (Site)
 *
 * Loads and saves the logged-in member's own editable fields only.
 *
 * Fields members CAN edit:
 *   - photo, shirt_size
 *   - emergency_name, emergency_phone, emergency_relation
 *   - Motorcycles: year, make, model, color, nickname, vin
 *
 * Fields members CANNOT edit (admin-only):
 *   member_number, membership_type, national_verified, dues fields,
 *   waiver fields, published, sponsor_id, notes
 *
 * SECURITY NOTE:
 *   profile_id is ALWAYS resolved from the database by user_id.
 *   POST values for profile_id are never trusted for the actual save.
 *   Motorcycle ids are updated or deleted only when they belong to that profile.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Component\Wendfold\Site\Helper\ProfileMotorcycleInput;
use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;

/**
 * Site profile edit model.
 *
 * @since  1.0.0
 */
class ProfileeditModel extends BaseDatabaseModel
{
	/*
	 * =========================================================================
	 * READ METHODS
	 * =========================================================================
	 */

	/**
	 * Load the profile row for the currently logged-in user.
	 *
	 * Selects only the fields the member is allowed to edit, plus
	 * the id and user_id needed to resolve ownership.
	 *
	 * @return  object|null  Profile row, or null if not found / guest
	 *
	 * @since   1.0.0
	 */
	public function getProfile(): ?object
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user || $user->guest)
		{
			return null;
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('user_id'),
					$db->quoteName('photo'),
					$db->quoteName('shirt_size'),
					$db->quoteName('emergency_name'),
					$db->quoteName('emergency_phone'),
					$db->quoteName('emergency_relation'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->bind(':userId', $user->id, ParameterType::INTEGER);

		$profile = $db->setQuery($query)->loadObject() ?: null;

		if ($profile === null)
		{
			return null;
		}

		$optOutField = RenewalReminderOptOut::FORM_FIELD;
		$storedOptOut = false;

		try
		{
			/** @var UserFactoryInterface $users */
			$users = Factory::getContainer()->get(UserFactoryInterface::class);
			$account = $users->loadUserById((int) $user->id);
			$storedOptOut = RenewalReminderOptOut::isOptedOut($account);
		}
		catch (\Throwable $e)
		{
			$storedOptOut = false;
		}

		// Prefer posted form data held after a validation error over stored params.
		$formState = null;
		$app       = Factory::getApplication();

		if ($app instanceof CMSWebApplicationInterface)
		{
			$formState = $app->getUserState(RenewalReminderOptOut::SITE_FORM_STATE, null);
		}

		$profile->$optOutField = RenewalReminderOptOut::resolveFormOptOut(
			$formState,
			$storedOptOut
		);

		return $profile;
	}

	/**
	 * Load the primary motorcycle for a given profile ID.
	 *
	 * @param   int  $profileId  Profile primary key
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getMotorcycle(int $profileId): ?object
	{
		$motorcycles = $this->getMotorcycles($profileId);

		foreach ($motorcycles as $motorcycle)
		{
			$isPrimary = (int) ($motorcycle->{'is_primary'} ?? 0);

			if ($isPrimary === 1)
			{
				return $motorcycle;
			}
		}

		return $motorcycles[0] ?? null;
	}

	/**
	 * Load every motorcycle that belongs to a profile.
	 *
	 * @param   int  $profileId  Profile primary key
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getMotorcycles(int $profileId): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('year'),
					$db->quoteName('model_year'),
					$db->quoteName('make'),
					$db->quoteName('model'),
					$db->quoteName('color'),
					$db->quoteName('nickname'),
					$db->quoteName('vin'),
					$db->quoteName('is_primary'),
				]
			)
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER)
			->order($db->quoteName('is_primary') . ' DESC, ' . $db->quoteName('id') . ' ASC');

		$rows = $db->setQuery($query)->loadObjectList();

		return \is_array($rows) ? $rows : [];
	}

	/**
	 * Granted awards for the member profile, newest first.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	public function getEarnedAwards(int $profileId): array
	{
		if ($profileId < 1)
		{
			return [];
		}

		$db = $this->getDatabase();
		$granted = AwardState::Granted->value;
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('ma.id'),
					$db->quoteName('ma.award_id'),
					$db->quoteName('ma.period_key'),
					$db->quoteName('ma.tier'),
					$db->quoteName('ma.earned_at'),
					$db->quoteName('ma.presented_on'),
					$db->quoteName('ad.title'),
					$db->quoteName('ad.image'),
					$db->quoteName('ad.period_type'),
				]
			)
			->from($db->quoteName('#__wendfold_member_awards', 'ma'))
			->join(
				'INNER',
				$db->quoteName('#__wendfold_award_definitions', 'ad')
					. ' ON ' . $db->quoteName('ad.id') . ' = ' . $db->quoteName('ma.award_id')
			)
			->where($db->quoteName('ma.profile_id') . ' = :profileId')
			->where($db->quoteName('ma.state') . ' = :granted')
			->bind(':profileId', $profileId, ParameterType::INTEGER)
			->bind(':granted', $granted, ParameterType::INTEGER)
			->order($db->quoteName('ma.earned_at') . ' DESC, ' . $db->quoteName('ma.id') . ' DESC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/*
	 * =========================================================================
	 * WRITE METHODS
	 * =========================================================================
	 */

	/**
	 * Save the member's editable profile fields and motorcycle subform.
	 *
	 * SECURITY: profile_id is resolved from the DB by the session user_id.
	 * The $data['profile_id'] POST value is used ONLY to cross-check that
	 * the record belongs to this user — it is never used as the save target
	 * without DB verification. Motorcycle rows are updated or deleted only
	 * when they belong to that resolved profile.
	 *
	 * @param   array  $data  Sanitized POST data from the controller
	 *
	 * @return  boolean          True on success, false on failure
	 *
	 * @since   1.0.0
	 */
	public function save(array $data): bool
	{
		$user = Factory::getApplication()->getIdentity();

		if (!$user || $user->guest)
		{
			return false;
		}

		$db  = $this->getDatabase();
		$now = Factory::getDate()->toSql();

		// Resolve profile_id from the DB by session user_id.
		// Never trust the POST value as the authoritative ID.
		$ownerQuery = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = :userId')
			->bind(':userId', $user->id, ParameterType::INTEGER);

		$resolvedProfileId = (int) $db->setQuery($ownerQuery)->loadResult();

		if (!$resolvedProfileId)
		{
			return false;
		}

		$postedRows = $data['motorcycles'] ?? [];

		if (!\is_array($postedRows))
		{
			$postedRows = [];
		}

		$ownedStore = $this->ownedMotorcycleStore($resolvedProfileId);
		$ownedIds   = array_map('intval', array_keys($ownedStore));

		try
		{
			$plan = ProfileMotorcycleInput::plan($ownedIds, $postedRows, null, $ownedStore);
		}
		catch (\InvalidArgumentException $error)
		{
			$key = $error->getMessage();

			if ($key === ProfileMotorcycleInput::MSG_YEAR || $key === ProfileMotorcycleInput::MSG_VIN)
			{
				$this->setError($key);
			}
			else
			{
				$this->setError('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR');
			}

			return false;
		}

		$db->transactionStart(true);

		try
		{
			$profileUpdate = $db->createQuery()
				->update($db->quoteName('#__wendfold_profiles'))
				->set($db->quoteName('shirt_size') . ' = ' . $db->quote(trim((string) ($data['shirt_size'] ?? ''))))
				->set($db->quoteName('emergency_name') . ' = ' . $db->quote(trim((string) ($data['emergency_name'] ?? ''))))
				->set($db->quoteName('emergency_phone') . ' = ' . $db->quote(trim((string) ($data['emergency_phone'] ?? ''))))
				->set($db->quoteName('emergency_relation') . ' = ' . $db->quote(trim((string) ($data['emergency_relation'] ?? ''))))
				->set($db->quoteName('modified') . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by') . ' = :modifiedBy')
				->where($db->quoteName('id') . ' = :profileId')
				->bind(':modifiedBy', $user->id, ParameterType::INTEGER)
				->bind(':profileId', $resolvedProfileId, ParameterType::INTEGER);

			if (!empty($data['photo']))
			{
				$profileUpdate->set($db->quoteName('photo') . ' = ' . $db->quote(trim((string) $data['photo'])));
			}

			$db->setQuery($profileUpdate)->execute();

			$this->applyMotorcyclePlan($resolvedProfileId, (int) $user->id, $plan, $now, \count($ownedIds));
			$this->ensureOnePrimary($resolvedProfileId);

			$db->transactionCommit(true);
		}
		catch (\Throwable $error)
		{
			$db->transactionRollback(true);
			Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
			$this->setError('COM_WENDFOLD_PROFILEEDIT_SAVE_ERROR');

			return false;
		}

		return true;
	}

	/**
	 * Stored year and VIN for motorcycles that belong to this profile.
	 *
	 * @param   int  $profileId  Profile primary key
	 *
	 * @return  array<int, array{year: int|null, vin: string}>
	 *
	 * @since   1.0.0
	 */
	public function ownedMotorcycleStore(int $profileId): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('year'),
					$db->quoteName('model_year'),
					$db->quoteName('vin'),
				]
			)
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$rows = $db->setQuery($query)->loadObjectList();

		if (!\is_array($rows))
		{
			return [];
		}

		$store = [];

		foreach ($rows as $row)
		{
			$id = (int) ($row->id ?? 0);

			if ($id <= 0)
			{
				continue;
			}

			$year = $row->year ?? $row->{'model_year'} ?? null;

			$store[$id] = [
				'year' => $year === null || $year === '' ? null : (int) $year,
				'vin'  => (string) ($row->vin ?? ''),
			];
		}

		return $store;
	}

	/**
	 * Apply a planned motorcycle insert/update/delete set.
	 *
	 * Deletes only run after a second ownership check. Updates bind the
	 * profile id in the WHERE clause so a crafted id cannot touch another
	 * member's row.
	 *
	 * @param   int                            $profileId     Resolved profile id
	 * @param   int                            $userId        Session user id
	 * @param   array<string, array>           $plan          Plan from ProfileMotorcycleInput::plan()
	 * @param   string                         $now           SQL datetime
	 * @param   int                            $ownedCount    Owned rows before this save
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function applyMotorcyclePlan(
		int $profileId,
		int $userId,
		array $plan,
		string $now,
		int $ownedCount
	): void
	{
		$db = $this->getDatabase();

		foreach ($plan['delete'] as $deleteId)
		{
			$motorcycleId = (int) $deleteId;

			if ($motorcycleId <= 0)
			{
				continue;
			}

			$delete = $db->createQuery()
				->delete($db->quoteName('#__wendfold_motorcycles'))
				->where($db->quoteName('id') . ' = :motorcycleId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->bind(':motorcycleId', $motorcycleId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER);

			$db->setQuery($delete)->execute();
		}

		foreach ($plan['update'] as $row)
		{
			$motorcycleId = (int) ($row['id'] ?? 0);
			$yearSql      = $row['year'] !== null ? (string) (int) $row['year'] : 'NULL';

			$update = $db->createQuery()
				->update($db->quoteName('#__wendfold_motorcycles'))
				->set($db->quoteName('year') . ' = ' . $yearSql)
				->set($db->quoteName('model_year') . ' = ' . $yearSql)
				->set($db->quoteName('make') . ' = ' . $db->quote((string) $row['make']))
				->set($db->quoteName('model') . ' = ' . $db->quote((string) $row['model']))
				->set($db->quoteName('color') . ' = ' . $db->quote((string) $row['color']))
				->set($db->quoteName('nickname') . ' = ' . $db->quote((string) $row['nickname']))
				->set($db->quoteName('vin') . ' = ' . $db->quote((string) $row['vin']))
				->set($db->quoteName('modified') . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by') . ' = :modifiedBy')
				->where($db->quoteName('id') . ' = :motorcycleId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->bind(':modifiedBy', $userId, ParameterType::INTEGER)
				->bind(':motorcycleId', $motorcycleId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER);

			$db->setQuery($update)->execute();
		}

		$remainingOwned = $ownedCount - \count($plan['delete']);
		$primaryAssigned = $remainingOwned > 0;

		foreach ($plan['insert'] as $row)
		{
			$yearSql   = $row['year'] !== null ? (string) (int) $row['year'] : 'NULL';
			$isPrimary = $primaryAssigned ? 0 : 1;
			$primaryAssigned = true;

			$insert = $db->createQuery()
				->insert($db->quoteName('#__wendfold_motorcycles'))
				->columns(
					[
						$db->quoteName('profile_id'),
						$db->quoteName('year'),
						$db->quoteName('model_year'),
						$db->quoteName('make'),
						$db->quoteName('model'),
						$db->quoteName('color'),
						$db->quoteName('nickname'),
						$db->quoteName('vin'),
						$db->quoteName('is_primary'),
						$db->quoteName('published'),
						$db->quoteName('created'),
						$db->quoteName('created_by'),
						$db->quoteName('modified'),
						$db->quoteName('modified_by'),
					]
				)
				->values(
					(int) $profileId . ', '
					. $yearSql . ', '
					. $yearSql . ', '
					. $db->quote((string) $row['make']) . ', '
					. $db->quote((string) $row['model']) . ', '
					. $db->quote((string) $row['color']) . ', '
					. $db->quote((string) $row['nickname']) . ', '
					. $db->quote((string) $row['vin']) . ', '
					. (int) $isPrimary . ', 1, '
					. $db->quote($now) . ', '
					. (int) $userId . ', '
					. $db->quote($now) . ', '
					. (int) $userId
				);

			$db->setQuery($insert)->execute();
		}
	}

	/**
	 * Ensure exactly one primary when the member still has motorcycles.
	 *
	 * If at least one bike remains and none is marked primary, the lowest id
	 * is set as primary inside the open save transaction.
	 *
	 * @param   int  $profileId  Resolved profile id
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function ensureOnePrimary(int $profileId): void
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('is_primary'),
				]
			)
			->from($db->quoteName('#__wendfold_motorcycles'))
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$rows = $db->setQuery($query)->loadObjectList();

		if (!\is_array($rows) || $rows === [])
		{
			return;
		}

		$remainingIds = [];
		$primaryIds   = [];

		foreach ($rows as $row)
		{
			$id = (int) ($row->id ?? 0);

			if ($id <= 0)
			{
				continue;
			}

			$remainingIds[] = $id;

			if ((int) ($row->{'is_primary'} ?? 0) === 1)
			{
				$primaryIds[] = $id;
			}
		}

		$assignId = ProfileMotorcycleInput::primaryIdToAssign($remainingIds, $primaryIds);

		if ($assignId === null)
		{
			return;
		}

		$update = $db->createQuery()
			->update($db->quoteName('#__wendfold_motorcycles'))
			->set($db->quoteName('is_primary') . ' = 1')
			->where($db->quoteName('id') . ' = :motorcycleId')
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':motorcycleId', $assignId, ParameterType::INTEGER)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$db->setQuery($update)->execute();
	}
}
