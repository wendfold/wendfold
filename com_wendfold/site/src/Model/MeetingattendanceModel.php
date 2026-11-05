<?php
/**
 * Wendfold - Chapter Management System
 * Site Meeting Attendance Model
 *
 * Provides roll-call views and manual attendance actions for meeting events
 * using the shared event, RSVP, and attendance tables.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Site\Helper\AttendanceEventAccess;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

class MeetingattendanceModel extends BaseDatabaseModel
{
	public function getEvent(int $eventId): ?object
	{
		if (!$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$trashed = AttendanceEventAccess::TRASHED;

		$limitQuery = $db->createQuery()
			->select([
					'e.id',
					'e.title',
					'e.event_date',
					'e.status',
					'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title',
					'e.workflow_mode',

					'e.audience_scope',
					'e.allow_rsvp',
					'e.event_location_custom',
					'e.event_city',
					'e.event_state',
					'evt_loc.name AS event_location_name',
					]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin($db->quoteName('#__wendfold_locations', 'evt_loc') . ' ON evt_loc.id = e.event_location_id')
			->where($db->quoteName('e.id') . ' = :eventId')
			->where($db->quoteName('e.published') . ' <> :trashed')
			->bind(':eventId', $eventId, ParameterType::INTEGER)
			->bind(':trashed', $trashed, ParameterType::INTEGER);
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	/**
	 * Language key when this event may not open or save attendance, or null when allowed.
	 *
	 * Shared by the roll-call view and the add/remove controller tasks.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function attendanceEventDenialKey(int $eventId): ?string
	{
		return AttendanceEventAccess::denialKeyForEvent($this->getDatabase(), $eventId);
	}

	public function getProfileId(int $userId): ?int
	{
		if (!$userId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$result = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_profiles'))
				->where($db->quoteName('user_id') . ' = ' . (int) $userId)
				->where($db->quoteName('published') . ' = 1')
		)->loadResult();

		return $result ? (int) $result : null;
	}

	private function getUserFlags(int $userId): ?object
	{
		if (!$userId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select([
					'p.id AS profile_id',
					'p.is_road_captain',
					'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
						. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
					]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->where($db->quoteName('p.user_id') . ' = ' . (int) $userId)
			->where($db->quoteName('p.published') . ' = 1');
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)->loadObject();

		if (!$row)
		{
			return null;
		}

		$row->profile_id = (int) ($row->profile_id ?? 0);
		$row->is_road_captain = !empty($row->is_road_captain);
		$row->is_officer = !empty($row->is_officer);

		return $row;
	}

	private function canAccessAudience(int $audienceScope, ?object $flags): bool
	{
		return AudienceHelper::canAccess($audienceScope, AudienceHelper::flagsFromProfile($flags));
	}

	private function isMeetingEvent(?object $event): bool
	{
		if (!$event)
		{
			return false;
		}

		return \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0)); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	}

	private function canManageMeeting(?object $event, ?object $flags, int $userId): bool
	{
		if (!$event || !$flags || !$userId)
		{
			return false;
		}

		$identity = Factory::getApplication()->getIdentity();

		if ((int) ($identity->id ?? 0) === $userId
			&& ($identity->authorise('core.admin', 'com_wendfold') || $identity->authorise('core.manage', 'com_wendfold')))
		{
			return true;
		}

		if (!$this->isMeetingEvent($event) || !$this->canAccessAudience((int) ($event->audience_scope ?? 1), $flags)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return false;
		}

		return \Wendfold\Component\Wendfold\Administrator\Helper\MeetingAudience::canTakeRoll(
			(int) ($event->audience_scope ?? 1), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			!empty($flags->is_officer),
			!empty($flags->is_road_captain)
		);
	}

	public function isAuthorized(int $eventId, int $userId): bool
	{
		$event = $this->getEvent($eventId);
		$flags = $this->getUserFlags($userId);

		return $this->canManageMeeting($event, $flags, $userId);
	}

	public function isEventOpen(int $eventId): bool
	{
		$event = $this->getEvent($eventId);

		return $event && in_array((int) ($event->status ?? 0), [1, 2, 4, 5], true);
	}

	/**
	 * Whether the event row is in the trash (published = -2).
	 *
	 * Unpublished and archived stay distinct from trashed so callers can
	 * keep their existing messages for those states.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function isEventTrashed(int $eventId): bool
	{
		return AttendanceEventAccess::loadPublished($this->getDatabase(), $eventId)
			=== AttendanceEventAccess::TRASHED;
	}

	public function getAttendanceCount(int $eventId): int
	{
		if (!$eventId)
		{
			return 0;
		}

		$db = $this->getDatabase();

		return (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(DISTINCT ' . $db->quoteName('profile_id') . ')')
				->from($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
		)->loadResult();
	}

	public function getExpectedCount(int $eventId): int
	{
		if (!$eventId)
		{
			return 0;
		}

		$db = $this->getDatabase();

		return (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(DISTINCT ' . $db->quoteName('profile_id') . ')')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
		)->loadResult();
	}

	public function getRoster(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();

		return $db->setQuery(
			$db->createQuery()
				->select([
					'a.id AS attendance_id',
					'a.check_in_time',
					'a.check_in_method',
					'p.id AS profile_id',
					'p.member_number',
					'p.photo',
					'p.is_road_captain',
					'u.name AS member_name',
					'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
						. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
					'CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS has_rsvp',
					]
				)
				->from($db->quoteName('#__wendfold_attendance', 'a'))
				->innerJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = a.profile_id')
				->innerJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin($db->quoteName('#__wendfold_rsvps', 'r') . ' ON r.event_id = a.event_id AND r.profile_id = a.profile_id')
				->where($db->quoteName('a.event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('p.published') . ' = 1')
				->order('is_officer DESC, p.is_road_captain DESC, u.name ASC')
		)->loadObjectList() ?: [];
	}

	private function buildCandidateBaseQuery(int $eventId)
	{
		$db = $this->getDatabase();

		return $db->createQuery()
			->select([
				'DISTINCT p.id AS profile_id',
				'u.name AS member_name',
				'p.member_number',
				'p.photo',
				'p.is_road_captain',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
					. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
				'CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS has_rsvp',
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->innerJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_rsvps', 'r') . ' ON r.event_id = ' . (int) $eventId . ' AND r.profile_id = p.id')
			->where($db->quoteName('p.published') . ' = 1')
			->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_attendance', 'a')
				. ' WHERE a.event_id = ' . (int) $eventId . ' AND a.profile_id = p.id)'
			);
	}

	private function getCandidateRows(int $eventId, string $mode = 'all', array $excludeIds = []): array
	{
		$db = $this->getDatabase();
		$query = $this->buildCandidateBaseQuery($eventId);

		if (!empty($excludeIds))
		{
			$query->where($db->quoteName('p.id') . ' NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')');
		}

		switch ($mode)
		{
			case 'officers':
				$query->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o_seed')
					. ' WHERE o_seed.profile_id = p.id AND o_seed.is_current = 1 AND o_seed.published = 1)'
				);
				$query->order('has_rsvp DESC, u.name ASC');
				break;

			case 'road_captains':
				$query->where('(p.is_road_captain = 1 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'hrc')
					. ' INNER JOIN ' . $db->quoteName('#__wendfold_officer_roles', 'hrcr') . ' ON hrcr.id = hrc.role_id'
					. ' WHERE hrc.profile_id = p.id AND hrc.is_current = 1 AND hrc.published = 1 AND hrcr.system_key = '
					. $db->quote(\Wendfold\Component\Wendfold\Administrator\Helper\OfficerRoleKey::HEAD_ROAD_CAPTAIN) . '))'
				);
				$query->order('has_rsvp DESC, u.name ASC');
				break;

			case 'rsvp':
				$query->where('r.id IS NOT NULL');
				$query->order('u.name ASC');
				break;

			default:
				$query->order('has_rsvp DESC, is_officer DESC, p.is_road_captain DESC, u.name ASC');
				break;
		}

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	public function getMemberPools(int $eventId): array
	{
		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return [
				'mode' => 'standard',
				'recommended' => [],
				'others' => [],
				'limitation' => '',
			];
		}

		$mode = \Wendfold\Component\Wendfold\Administrator\Helper\MeetingAudience::poolMode((int) ($event->audience_scope ?? 1)); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

		$recommendedMode = match ($mode) {
			'officers' => 'officers',
			'road_captains' => 'road_captains',
			default => 'rsvp',
		};

		$recommended = $this->getCandidateRows($eventId, $recommendedMode);
		$recommendedIds = array_values(array_unique(array_filter(array_map(static fn($row): int => (int) ($row->profile_id ?? 0), $recommended))));
		$others = $this->getCandidateRows($eventId, 'all', $recommendedIds);

		return [
			'mode' => $mode,
			'recommended' => $recommended,
			'others' => $others,
			'limitation' => $mode === 'auxiliary' ? 'auxiliary' : '',
		];
	}

	public function addMember(int $eventId, int $profileId, int $checkedInBy)
	{
		$db = $this->getDatabase();
		$eventId = (int) $eventId;
		$profileId = (int) $profileId;

		$record = (object) [
			'event_id' => $eventId,
			'profile_id' => $profileId,
			'check_in_time' => Factory::getDate()->toSql(),
			'check_in_method' => 2,
			'checked_in_by' => (int) $checkedInBy,
			'notes' => '',
		];

		$metrics = MetricService::fromDatabase($db, (int) $checkedInBy);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'db_error';
			}

			if ((int) $locked->published === AttendanceEventAccess::TRASHED)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'trashed';
			}

			if ((int) $locked->status === 3 || !\in_array((int) $locked->status, [1, 2, 4, 5], true))
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'closed';
			}

			$exists = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('profile_id') . ' = :profileId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER)
			)->loadResult();

			if ($exists > 0)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'already';
			}

			$db->insertObject('#__wendfold_attendance', $record, 'id');
			$metrics->creditAttendance($eventId, (int) $record->id);
			$db->transactionCommit(true);
			$metrics->flushAwardRecompute();

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();

			if (DuplicateKey::is($e))
			{
				return 'already';
			}

			Log::add('Meeting attendance DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	public function removeMember(int $attendanceId, int $eventId)
	{
		$db = $this->getDatabase();
		$attendanceId = (int) $attendanceId;
		$eventId = (int) $eventId;

		$metrics = MetricService::fromDatabase(
			$db,
			(int) Factory::getApplication()->getIdentity()->id
		);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'not_found';
			}

			if ((int) $locked->status === 3 || !\in_array((int) $locked->status, [1, 2, 4, 5], true))
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'closed';
			}

			$owned = $db->setQuery(
				$db->createQuery()
					->select([
						$db->quoteName('id'),
						$db->quoteName('profile_id'),
						]
					)
					->from($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('id') . ' = :attendanceId')
					->where($db->quoteName('event_id') . ' = :eventId')
					->bind(':attendanceId', $attendanceId, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->loadObject();

			if (!$owned)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'not_found';
			}

			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column
			$profileId = (int) $owned->profile_id;

			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('id') . ' = :attendanceId')
					->where($db->quoteName('event_id') . ' = :eventId')
					->bind(':attendanceId', $attendanceId, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->execute();

			if ((int) $db->getAffectedRows() !== 1)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'not_found';
			}

			$metrics->trashAttendance($eventId, $attendanceId, $profileId);
			$db->transactionCommit(true);
			$metrics->flushAwardRecompute();

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			Log::add('Meeting attendance DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}
}
