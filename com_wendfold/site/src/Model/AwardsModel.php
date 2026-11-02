<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Service\AwardState;

/**
 * Site award catalog and earned grants.
 *
 * @since  1.0.0
 */
class AwardsModel extends BaseDatabaseModel
{
	/**
	 * @var    object|null
	 * @since  1.0.0
	 */
	protected $profileCache = null;

	/**
	 * @var    array<int, object>|null
	 * @since  1.0.0
	 */
	protected $catalogCache = null;

	/**
	 * Logged-in member profile, or null.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getProfile(): ?object
	{
		if ($this->profileCache !== null)
		{
			return $this->profileCache;
		}

		$user = Factory::getApplication()->getIdentity();

		if (!$user || $user->guest)
		{
			return null;
		}

		$this->profileCache = $this->getMemberProfileByUserId((int) $user->id);

		return $this->profileCache;
	}

	/**
	 * Profile row for a Joomla user id.
	 *
	 * @param   integer  $userId  User id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getMemberProfileByUserId(int $userId): ?object
	{
		if ($userId < 1)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select('p.*, u.name, u.email')
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join(
				'LEFT',
				$db->quoteName('#__users', 'u')
					. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.user_id') . ' = :userId')
			->bind(':userId', $userId, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Published award definitions for the public catalog.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	public function getCatalog(): array
	{
		if ($this->catalogCache !== null)
		{
			return $this->catalogCache;
		}

		$db = $this->getDatabase();
		$published = 1;
		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_award_definitions'))
			->where($db->quoteName('published') . ' = :published')
			->bind(':published', $published, ParameterType::INTEGER)
			->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');

		$this->catalogCache = $db->setQuery($query)->loadObjectList() ?: [];

		return $this->catalogCache;
	}

	/**
	 * Granted awards for a profile, newest first.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  array<int, object>
	 *
	 * @since   1.0.0
	 */
	public function getMemberEarnedAwards(int $profileId): array
	{
		if ($profileId < 1)
		{
			return [];
		}

		$db = $this->getDatabase();
		$granted = AwardState::Granted->value;
		$query = $db->createQuery()
			->select([
				$db->quoteName('ma.id'),
				$db->quoteName('ma.award_id'),
				$db->quoteName('ma.profile_id'),
				$db->quoteName('ma.period_key'),
				$db->quoteName('ma.tier'),
				$db->quoteName('ma.earned_at'),
				$db->quoteName('ma.presented_on'),
				$db->quoteName('ad.title'),
				$db->quoteName('ad.description'),
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

	/**
	 * Latest granted award, or null.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getLatestEarnedAward(int $profileId): ?object
	{
		$earned = $this->getMemberEarnedAwards($profileId);

		return $earned[0] ?? null;
	}

	/**
	 * Compact awards payload for the dashboard.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getDashboardSnapshot(): ?object
	{
		$profile = $this->getProfile();

		if (!$profile)
		{
			return null;
		}

		$profileId = (int) $profile->id;
		$earned = $this->getMemberEarnedAwards($profileId);

		return (object) [
			'latest' => $earned[0] ?? null,
			'earned' => \array_slice($earned, 0, 5),
			'count'  => \count($earned),
		];
	}
}
