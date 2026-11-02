<?php
/**
 * Wendfold - Chapter Management System
 * Site RC Attendance Model
 *
 * Powers the mobile-friendly Road Captain check-in interface.
 * Handles roster, manual attendance actions, and ride-group management.
 *
 * Ride groups are event-owned planning records for riding events only.
 * They supplement RSVP and attendance; they never replace them.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Helper\EventStatusTransition;
use Wendfold\Component\Wendfold\Administrator\Service\EventCancelNoticeService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Site\Helper\AttendanceEventAccess;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * RC Attendance Model
 *
 * @since  1.0.0
 */
class RcattendanceModel extends BaseDatabaseModel
{
	/**
	 * Load the event record with KSU location name joined.
	 *
	 * @param   int  $eventId  The event primary key.
	 *
	 * @return  object|null
	 */
	public function getEvent(int $eventId): ?object
	{
		if (!$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$trashed = AttendanceEventAccess::TRASHED;
		$query = $db->createQuery()
			->select([
				'e.id', 'e.title', 'e.event_date', 'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title', 'e.workflow_mode', // phpcs:ignore Generic.Files.LineLength.TooLong
				'e.ksu_time', 'e.ksd_time', 'e.status',
				'e.is_riding_event', 'e.road_captain_id', 'e.sweep_id',
				'e.trip_lead_id', 'e.trip_sweep_id', 'e.qr_token',
				'e.ksu_location_custom', 'e.ksu_city', 'e.ksu_state',
				'ksu_loc.name AS ksu_location_name',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin(
				$db->quoteName('#__wendfold_locations', 'ksu_loc')
				. ' ON ksu_loc.id = e.ksu_location_id'
			)
			->where($db->quoteName('e.id') . ' = :eventId')
			->where($db->quoteName('e.published') . ' <> :trashed')
			->bind(':eventId', $eventId, ParameterType::INTEGER)
			->bind(':trashed', $trashed, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Language key when this event may not open or save attendance, or null when allowed.
	 *
	 * Shared by the RC attendance view and the save controller tasks.
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

	/**
	 * Whether the event row is in the trash (published = -2).
	 *
	 * Unpublished and archived stay distinct from trashed so add/check-in
	 * can refuse trash without changing those other states.
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

	/**
	 * Check whether the given Joomla user is authorized to manage this event.
	 * Authorized = super admin, event RC/Sweep, or any group RC/Sweep.
	 *
	 * @param   int  $eventId  The event ID.
	 * @param   int  $userId   The Joomla user ID.
	 *
	 * @return  boolean
	 */
	public function isAuthorized(int $eventId, int $userId): bool
	{
		$user = Factory::getApplication()->getIdentity();

		if ($user->authorise('core.admin'))
		{
			return true;
		}

		$profileId = $this->getProfileId($userId);

		if (!$profileId)
		{
			return false;
		}

		return in_array($profileId, $this->getAssignedProfileIds($eventId), true);
	}

	/**
	 * Resolve a Joomla user ID to a published profile ID.
	 *
	 * @param   int  $userId  Joomla user ID.
	 *
	 * @return  integer|null
	 */
	public function getProfileId(int $userId): ?int
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = ' . (int) $userId)
			->where($db->quoteName('published') . ' = 1');

		$result = $db->setQuery($query)->loadResult();

		return $result ? (int) $result : null;
	}

	/**
	 * Return all assigned RC/Sweep profile IDs for the event.
	 * Includes event-level assignments and ride-group assignments.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  int[]
	 */
	private function getAssignedProfileIds(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$assigned = [];

		$event = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('road_captain_id'),
					$db->quoteName('sweep_id'),
					$db->quoteName('trip_lead_id'),
					$db->quoteName('trip_sweep_id'),
					$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'), // phpcs:ignore Generic.Files.LineLength.TooLong
					$db->quoteName('workflow_mode'),
					]
				)
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . (int) $eventId)
		)->loadObject();

		if ($event)
		{
			$assigned[] = (int) ($event->road_captain_id ?? 0);
			$assigned[] = (int) ($event->sweep_id ?? 0);

			if (\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0))) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			{
				$assigned[] = (int) ($event->trip_lead_id ?? 0);
				$assigned[] = (int) ($event->trip_sweep_id ?? 0);
			}
		}

		$groupRows = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('road_captain_id'),
					$db->quoteName('sweep_id'),
					]
				)
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
		)->loadObjectList() ?: [];

		foreach ($groupRows as $group)
		{
			$assigned[] = (int) ($group->road_captain_id ?? 0);
			$assigned[] = (int) ($group->sweep_id ?? 0);
		}

		return array_values(array_unique(array_filter(array_map('intval', $assigned))));
	}


	/**
	 * Return the group that a profile implicitly belongs to because they are
	 * assigned as that group's Road Captain or Sweep.
	 *
	 * Group leadership takes precedence over stale manual group-member rows so
	 * leader/sweep assignments stay consistent across RC Attendance, Event
	 * Details, and My RSVPs.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  array<int, int>
	 */
	private function getRoleAssignedGroupMap(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$rows = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('group_order'),
					$db->quoteName('road_captain_id'),
					$db->quoteName('sweep_id'),
					]
				)
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
				->order($db->quoteName('group_order') . ' ASC, ' . $db->quoteName('id') . ' ASC')
		)->loadObjectList() ?: [];

		$map = [];

		foreach ($rows as $row)
		{
			$groupId = (int) ($row->id ?? 0);

			foreach ([(int) ($row->road_captain_id ?? 0), (int) ($row->sweep_id ?? 0)] as $profileId)
			{
				if ($groupId <= 0 || $profileId <= 0 || isset($map[$profileId]))
				{
					continue;
				}

				$map[$profileId] = $groupId;
			}
		}

		return $map;
	}

	/**
	 * Next group_order for an event (MAX + 1), under the caller's event lock.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db       Database driver.
	 * @param   integer                             $eventId  Event id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function nextGroupOrder($db, int $eventId): int
	{
		$nextOrder = (int) $db->setQuery(
			$db->createQuery()
				->select('COALESCE(MAX(' . $db->quoteName('group_order') . '), 0) + 1')
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
		)->loadResult();

		return max(1, $nextOrder);
	}

	/**
	 * Next assignment_order for a group (MAX + 1), under the caller's event lock.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db       Database driver.
	 * @param   integer                             $groupId  Group id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function nextAssignmentOrder($db, int $groupId): int
	{
		$assignmentOrder = (int) $db->setQuery(
			$db->createQuery()
				->select('COALESCE(MAX(' . $db->quoteName('assignment_order') . '), 0) + 1')
				->from($db->quoteName('#__wendfold_event_group_members'))
				->where($db->quoteName('event_group_id') . ' = :groupId')
				->bind(':groupId', $groupId, ParameterType::INTEGER)
		)->loadResult();

		return max(1, $assignmentOrder);
	}

	/**
	 * Ensure a rider is in the requested group under the caller's event lock.
	 *
	 * The unique key is (event_id, profile_id), not assignment_order. Same-group
	 * is an idempotent no-op. A different group moves the row (main assign /
	 * sync semantics) with a fresh MAX+1 assignment_order. Insert races re-read
	 * and reconcile; duplicate-key is never treated as silent success.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db         Database driver.
	 * @param   integer                             $eventId    Event id.
	 * @param   integer                             $groupId    Target group id.
	 * @param   integer                             $profileId  Profile id.
	 * @param   integer                             $userId     Actor user id.
	 * @param   string                              $now        SQL datetime.
	 *
	 * @return  string  same, moved, or inserted.
	 *
	 * @since   1.0.0
	 */
	private function ensureGroupMembership($db, int $eventId, int $groupId, int $profileId, int $userId, string $now): string
	{
		$existing = $this->loadGroupMembershipRow($db, $eventId, $profileId);

		if ($existing !== null && (int) ($existing->event_group_id ?? 0) === $groupId)
		{
			return 'same';
		}

		if ($existing !== null)
		{
			$this->moveGroupMembership($db, (int) $existing->id, $groupId, $userId, $now);

			return 'moved';
		}

		try
		{
			$record = (object) [
				'event_group_id'   => $groupId,
				'event_id'         => $eventId,
				'profile_id'       => $profileId,
				'assignment_order' => $this->nextAssignmentOrder($db, $groupId),
				'assigned_at'      => $now,
				'assigned_by'      => $userId,
				'notes'            => '',
			];
			$db->insertObject('#__wendfold_event_group_members', $record);

			return 'inserted';
		}
		catch (\Throwable $error)
		{
			if (!DuplicateKey::is($error))
			{
				throw $error;
			}

			// Another writer won the (event_id, profile_id) race: re-read and reconcile.
			$existing = $this->loadGroupMembershipRow($db, $eventId, $profileId);

			if ($existing === null)
			{
				throw $error;
			}

			if ((int) ($existing->event_group_id ?? 0) === $groupId)
			{
				return 'same';
			}

			$this->moveGroupMembership($db, (int) $existing->id, $groupId, $userId, $now);

			return 'moved';
		}
	}

	/**
	 * Load the single group-membership row for an event and profile, if any.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db         Database driver.
	 * @param   integer                             $eventId    Event id.
	 * @param   integer                             $profileId  Profile id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function loadGroupMembershipRow($db, int $eventId, int $profileId): ?object
	{
		$limitQuery = $db->createQuery()
			->select([
					$db->quoteName('id'),
					$db->quoteName('event_group_id'),
					]
			)
			->from($db->quoteName('#__wendfold_event_group_members'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->where($db->quoteName('profile_id') . ' = :profileId')
			->bind(':eventId', $eventId, ParameterType::INTEGER)
			->bind(':profileId', $profileId, ParameterType::INTEGER);
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)->loadObject();

		return $row ?: null;
	}

	/**
	 * Move an existing membership row into another group with a fresh order.
	 *
	 * @param   \Joomla\Database\DatabaseInterface  $db         Database driver.
	 * @param   integer                             $memberId   Membership row id.
	 * @param   integer                             $groupId    Target group id.
	 * @param   integer                             $userId     Actor user id.
	 * @param   string                              $now        SQL datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function moveGroupMembership($db, int $memberId, int $groupId, int $userId, string $now): void
	{
		$assignmentOrder = $this->nextAssignmentOrder($db, $groupId);
		$db->setQuery(
			$db->createQuery()
				->update($db->quoteName('#__wendfold_event_group_members'))
				->set($db->quoteName('event_group_id') . ' = :groupId')
				->set($db->quoteName('assignment_order') . ' = :assignmentOrder')
				->set($db->quoteName('assigned_at') . ' = :assignedAt')
				->set($db->quoteName('assigned_by') . ' = :assignedBy')
				->where($db->quoteName('id') . ' = :memberId')
				->bind(':groupId', $groupId, ParameterType::INTEGER)
				->bind(':assignmentOrder', $assignmentOrder, ParameterType::INTEGER)
				->bind(':assignedAt', $now)
				->bind(':assignedBy', $userId, ParameterType::INTEGER)
				->bind(':memberId', $memberId, ParameterType::INTEGER)
		)->execute();
	}

	/**
	 * Keep group-leader assignments aligned with the ride-group membership
	 * table so group RC/Sweep roles do not drift into conflicting groups.
	 *
	 * Callers must hold EventRowLock on the event inside an open transaction.
	 *
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $userId   Actor user id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function syncGroupRoleMemberships(int $eventId, int $userId): void
	{
		$roleGroupMap = $this->getRoleAssignedGroupMap($eventId);

		if (empty($roleGroupMap))
		{
			return;
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();

		foreach ($roleGroupMap as $profileId => $groupId)
		{
			$profileId = (int) $profileId;
			$groupId = (int) $groupId;
			$this->ensureGroupMembership($db, $eventId, $groupId, $profileId, $userId, $now);

			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_event_group_members'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('profile_id') . ' = :profileId')
					->where($db->quoteName('event_group_id') . ' != :groupId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER)
					->bind(':groupId', $groupId, ParameterType::INTEGER)
			)->execute();
		}
	}

	/**
	 * Determine whether a profile has an explicit RSVP.
	 */
	private function hasExplicitRsvp(int $eventId, int $profileId): bool
	{
		$db = $this->getDatabase();

		return (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->loadResult() > 0;
	}

	/**
	 * Determine whether a profile is checked in.
	 */
	private function hasAttendance(int $eventId, int $profileId): bool
	{
		$db = $this->getDatabase();

		return (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->loadResult() > 0;
	}

	/**
	 * Remove a group assignment when a member no longer participates and is not
	 * an assigned event/group RC or Sweep.
	 */
	private function clearOptionalGroupAssignment(int $eventId, int $profileId): void
	{
		if (!$eventId || !$profileId)
		{
			return;
		}

		if (in_array($profileId, $this->getAssignedProfileIds($eventId), true))
		{
			return;
		}

		if ($this->hasExplicitRsvp($eventId, $profileId) || $this->hasAttendance($eventId, $profileId))
		{
			return;
		}

		$db = $this->getDatabase();
		$db->setQuery(
			$db->createQuery()
				->delete($db->quoteName('#__wendfold_event_group_members'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->execute();
	}

	/**
	 * Return the number of RSVPs for an event, treating assigned RC/Sweep as
	 * implicitly RSVP'd.
	 */
	public function getRsvpCount(int $eventId): int
	{
		$db = $this->getDatabase();

		$rsvpCount = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(DISTINCT ' . $db->quoteName('profile_id') . ')')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
		)->loadResult();

		$assignedProfileIds = $this->getAssignedProfileIds($eventId);

		if (empty($assignedProfileIds))
		{
			return $rsvpCount;
		}

		$existingAssigned = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(DISTINCT ' . $db->quoteName('profile_id') . ')')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' IN (' . implode(',', $assignedProfileIds) . ')')
		)->loadResult();

		return $rsvpCount + max(0, count($assignedProfileIds) - $existingAssigned);
	}

	/**
	 * Get the current check-in roster with RSVP flags, dues flags, and group
	 * assignment context.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  array
	 */
	public function getRoster(int $eventId): array
	{
		$db = $this->getDatabase();
		$year = (int) date('Y');
		$assignedProfileIds = $this->getAssignedProfileIds($eventId);
		$assignedSql = !empty($assignedProfileIds) ? implode(',', array_map('intval', $assignedProfileIds)) : '';
		$assignedSelect = $assignedSql !== ''
			? 'CASE WHEN p.id IN (' . $assignedSql . ') THEN 1 ELSE 0 END AS is_assigned_role'
			: '0 AS is_assigned_role';
		$roleGroupIdSubquery = '(SELECT rg.id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupTitleSubquery = '(SELECT rg.title FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderSubquery = '(SELECT rg.group_order FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupStagingSubquery = '(SELECT rg.staging_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderHintSubquery = '(SELECT rg.order_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupRoadCaptainSubquery = '(SELECT rg.road_captain_id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupSweepSubquery = '(SELECT rg.sweep_id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';

		$query = $db->createQuery()
			->select([
				'a.id AS attendance_id',
				'a.check_in_time',
				'a.check_in_method',
				'p.id AS profile_id',
				'p.member_number',
				'p.photo',
				'p.waiver_signed',
				'p.dues_paid',
				'u.name AS member_name',
				'CASE WHEN rv.id IS NULL THEN 0 ELSE 1 END AS is_rsvped',
				$assignedSelect,
				'CASE WHEN dp.id IS NOT NULL THEN 1 ELSE 0 END AS paid_this_year',
				'COALESCE(' . $roleGroupIdSubquery . ', gm.event_group_id) AS group_id',
				'COALESCE(' . $roleGroupTitleSubquery . ', g.title) AS group_title',
				'COALESCE(' . $roleGroupOrderSubquery . ', g.group_order) AS group_order',
				'COALESCE(' . $roleGroupStagingSubquery . ', g.staging_hint) AS group_staging_hint',
				'COALESCE(' . $roleGroupOrderHintSubquery . ', g.order_hint) AS group_order_hint',
				'COALESCE(' . $roleGroupRoadCaptainSubquery . ', g.road_captain_id) AS group_road_captain_id',
				'COALESCE(' . $roleGroupSweepSubquery . ', g.sweep_id) AS group_sweep_id',
				]
			)
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = a.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_rsvps', 'rv') . ' ON rv.event_id = a.event_id AND rv.profile_id = a.profile_id')
			->leftJoin($db->quoteName('#__wendfold_dues_payments', 'dp') . ' ON dp.profile_id = p.id AND dp.year = ' . $year)
			->leftJoin($db->quoteName('#__wendfold_event_group_members', 'gm') . ' ON gm.event_id = a.event_id AND gm.profile_id = a.profile_id')
			->leftJoin($db->quoteName('#__wendfold_event_groups', 'g') . ' ON g.id = gm.event_group_id AND g.published = 1')
			->where($db->quoteName('a.event_id') . ' = ' . (int) $eventId)
			->order('group_order ASC, u.name ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Get all active members not already checked in, with RSVP-priority split,
	 * and include their current group assignment if one exists.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  array{rsvp: array, others: array}
	 */
	public function getMemberList(int $eventId): array
	{
		$db = $this->getDatabase();
		$year = (int) date('Y');
		$assignedProfileIds = $this->getAssignedProfileIds($eventId);
		$assignedClause = !empty($assignedProfileIds)
			? ' OR p.id IN (' . implode(',', $assignedProfileIds) . ')'
			: '';
		$assignedSelect = !empty($assignedProfileIds)
			? 'CASE WHEN p.id IN (' . implode(',', $assignedProfileIds) . ') THEN 1 ELSE 0 END AS is_assigned_role'
			: '0 AS is_assigned_role';
		$roleGroupIdSubquery = '(SELECT rg.id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupTitleSubquery = '(SELECT rg.title FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderSubquery = '(SELECT rg.group_order FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupStagingSubquery = '(SELECT rg.staging_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderHintSubquery = '(SELECT rg.order_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . (int) $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';

		$baseSelect = [
			'p.id AS profile_id',
			'p.member_number',
			'p.photo',
			'p.waiver_signed',
			'p.dues_paid',
			'u.name AS member_name',
			'CASE WHEN dp.id IS NOT NULL THEN 1 ELSE 0 END AS paid_this_year',
			$assignedSelect,
			'COALESCE(' . $roleGroupIdSubquery . ', gm.event_group_id) AS group_id',
			'COALESCE(' . $roleGroupTitleSubquery . ', g.title) AS group_title',
			'COALESCE(' . $roleGroupOrderSubquery . ', g.group_order) AS group_order',
			'COALESCE(' . $roleGroupStagingSubquery . ', g.staging_hint) AS group_staging_hint',
			'COALESCE(' . $roleGroupOrderHintSubquery . ', g.order_hint) AS group_order_hint',
		];

		$rsvpQuery = $db->createQuery()
			->select($baseSelect)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_dues_payments', 'dp') . ' ON dp.profile_id = p.id AND dp.year = ' . $year)
			->leftJoin($db->quoteName('#__wendfold_event_group_members', 'gm') . ' ON gm.event_id = ' . (int) $eventId . ' AND gm.profile_id = p.id')
			->leftJoin($db->quoteName('#__wendfold_event_groups', 'g') . ' ON g.id = gm.event_group_id AND g.published = 1')
			->where($db->quoteName('p.published') . ' = 1')
			->where(
				'(' .
				'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_rsvps', 'rv')
				. ' WHERE rv.event_id = ' . (int) $eventId
				. ' AND rv.profile_id = p.id)'
				. $assignedClause .
				')'
			)
			->where(
				'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_attendance', 'a_chk')
				. ' WHERE a_chk.event_id = ' . (int) $eventId
				. ' AND a_chk.profile_id = p.id)'
			)
			->order('group_order ASC, u.name ASC');

		$rsvpMembers = $db->setQuery($rsvpQuery)->loadObjectList() ?: [];

		$othersQuery = $db->createQuery()
			->select($baseSelect)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_dues_payments', 'dp') . ' ON dp.profile_id = p.id AND dp.year = ' . $year)
			->leftJoin($db->quoteName('#__wendfold_event_group_members', 'gm') . ' ON gm.event_id = ' . (int) $eventId . ' AND gm.profile_id = p.id')
			->leftJoin($db->quoteName('#__wendfold_event_groups', 'g') . ' ON g.id = gm.event_group_id AND g.published = 1')
			->where($db->quoteName('p.published') . ' = 1')
			->where(
				'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_attendance', 'a_chk')
				. ' WHERE a_chk.event_id = ' . (int) $eventId
				. ' AND a_chk.profile_id = p.id)'
			)
			->where(
				'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_rsvps', 'rv_chk')
				. ' WHERE rv_chk.event_id = ' . (int) $eventId
				. ' AND rv_chk.profile_id = p.id)'
			)
			->order('u.name ASC');

		if (!empty($assignedProfileIds))
		{
			$othersQuery->where('p.id NOT IN (' . implode(',', $assignedProfileIds) . ')');
		}

		$otherMembers = $db->setQuery($othersQuery)->loadObjectList() ?: [];

		return [
			'rsvp'   => $rsvpMembers,
			'others' => $otherMembers,
		];
	}

	/**
	 * Return eligible role options for ride-group Road Captains and Sweeps.
	 * Head Road Captain officers remain eligible without overloading the office
	 * system itself as the primary data model.
	 *
	 * @return  array{road_captains: array, sweeps: array}
	 */
	public function getGroupRoleOptions(): array
	{
		$db = $this->getDatabase();

		$roadCaptains = $db->setQuery(
			$db->createQuery()
				->select([
					'DISTINCT p.id AS value',
					'u.name AS text',
					]
				)
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin(
					$db->quoteName('#__wendfold_officers', 'o')
					. ' ON o.profile_id = p.id'
					. ' AND o.published = 1'
					. ' AND o.is_current = 1'
				)
				->leftJoin(
					$db->quoteName('#__wendfold_officer_roles', 'hrcr')
					. ' ON hrcr.id = o.role_id AND hrcr.system_key = '
					. $db->quote(\Wendfold\Component\Wendfold\Administrator\Helper\OfficerRoleKey::HEAD_ROAD_CAPTAIN)
				)
				->where($db->quoteName('p.published') . ' = 1')
				->where('(' . $db->quoteName('p.is_road_captain') . ' = 1 OR hrcr.id IS NOT NULL)')
				->order('u.name ASC')
		)->loadObjectList() ?: [];

		$sweeps = $db->setQuery(
			$db->createQuery()
				->select([
					'DISTINCT p.id AS value',
					'u.name AS text',
					]
				)
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin(
					$db->quoteName('#__wendfold_officers', 'o')
					. ' ON o.profile_id = p.id'
					. ' AND o.published = 1'
					. ' AND o.is_current = 1'
				)
				->leftJoin(
					$db->quoteName('#__wendfold_officer_roles', 'hrcr')
					. ' ON hrcr.id = o.role_id AND hrcr.system_key = '
					. $db->quote(\Wendfold\Component\Wendfold\Administrator\Helper\OfficerRoleKey::HEAD_ROAD_CAPTAIN)
				)
				->where($db->quoteName('p.published') . ' = 1')
				->where(
					'('
					. $db->quoteName('p.is_road_crew') . ' = 1 OR '
					. $db->quoteName('p.is_road_captain') . ' = 1 OR '
					. 'hrcr.id IS NOT NULL)'
				)
				->order('u.name ASC')
		)->loadObjectList() ?: [];

		return [
			'road_captains' => $roadCaptains,
			'sweeps'        => $sweeps,
		];
	}

	/**
	 * Return ride groups and their current members for an event.
	 * Only current participants appear in member lists.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  array
	 */
	public function getGroups(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$assignedProfileIds = $this->getAssignedProfileIds($eventId);
		$assignedSql = !empty($assignedProfileIds) ? implode(',', array_map('intval', $assignedProfileIds)) : '0';

		$groups = $db->setQuery(
			$db->createQuery()
				->select([
					'g.*',
					'rc_u.name AS road_captain_name',
					'sw_u.name AS sweep_name',
					]
				)
				->from($db->quoteName('#__wendfold_event_groups', 'g'))
				->leftJoin($db->quoteName('#__wendfold_profiles', 'rc_p') . ' ON rc_p.id = g.road_captain_id')
				->leftJoin($db->quoteName('#__users', 'rc_u') . ' ON rc_u.id = rc_p.user_id')
				->leftJoin($db->quoteName('#__wendfold_profiles', 'sw_p') . ' ON sw_p.id = g.sweep_id')
				->leftJoin($db->quoteName('#__users', 'sw_u') . ' ON sw_u.id = sw_p.user_id')
				->where($db->quoteName('g.event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('g.published') . ' = 1')
				->order($db->quoteName('g.group_order') . ' ASC')
		)->loadObjectList('id') ?: [];

		if (empty($groups))
		{
			return [];
		}

		foreach ($groups as $group)
		{
			$group->members = [];
			$group->member_count = 0;
		}

		$memberRows = $db->setQuery(
			$db->createQuery()
				->select([
					'gm.event_group_id',
					'gm.assignment_order',
					'p.id AS profile_id',
					'u.name AS member_name',
					'p.photo',
					'CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS checked_in',
					]
				)
				->from($db->quoteName('#__wendfold_event_group_members', 'gm'))
				->join('INNER', $db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = gm.profile_id')
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin($db->quoteName('#__wendfold_rsvps', 'r') . ' ON r.event_id = gm.event_id AND r.profile_id = gm.profile_id')
				->leftJoin($db->quoteName('#__wendfold_attendance', 'a') . ' ON a.event_id = gm.event_id AND a.profile_id = gm.profile_id')
				->where($db->quoteName('gm.event_id') . ' = ' . (int) $eventId)
				->where('(r.id IS NOT NULL OR a.id IS NOT NULL OR p.id IN (' . $assignedSql . '))')
				->order($db->quoteName('gm.event_group_id') . ' ASC, ' . $db->quoteName('gm.assignment_order') . ' ASC, u.name ASC')
		)->loadObjectList() ?: [];

		$memberProfileMap = [];
		$roleGroupMap = $this->getRoleAssignedGroupMap($eventId);

		foreach ($memberRows as $row)
		{
			$groupId = (int) ($row->event_group_id ?? 0);
			$profileId = (int) ($row->profile_id ?? 0);
			$roleGroupId = (int) ($roleGroupMap[$profileId] ?? 0);

			if ($roleGroupId > 0 && $groupId !== $roleGroupId)
			{
				continue;
			}

			if (!isset($groups[$groupId]))
			{
				continue;
			}

			$groups[$groupId]->members[] = $row;
			$groups[$groupId]->member_count++;
			$memberProfileMap[$groupId][$profileId] = true;
		}

		$missingProfileIds = [];
		$missingGroupRoles = [];

		foreach ($groups as $groupId => $group)
		{
			foreach ([(int) ($group->road_captain_id ?? 0), (int) ($group->sweep_id ?? 0)] as $roleProfileId)
			{
				if ($roleProfileId <= 0 || isset($memberProfileMap[$groupId][$roleProfileId]))
				{
					continue;
				}

				$missingProfileIds[$roleProfileId] = $roleProfileId;
				$missingGroupRoles[$groupId][$roleProfileId] = $roleProfileId;
			}
		}

		if (!empty($missingProfileIds))
		{
			$missingRows = $db->setQuery(
				$db->createQuery()
					->select([
						'p.id AS profile_id',
						'u.name AS member_name',
						'p.photo',
						'CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS checked_in',
						]
					)
					->from($db->quoteName('#__wendfold_profiles', 'p'))
					->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
					->leftJoin($db->quoteName('#__wendfold_attendance', 'a') . ' ON a.event_id = ' . (int) $eventId . ' AND a.profile_id = p.id')
					->where($db->quoteName('p.id') . ' IN (' . implode(',', $missingProfileIds) . ')')
					->where($db->quoteName('p.published') . ' = 1')
			)->loadObjectList('profile_id') ?: [];

			foreach ($missingGroupRoles as $groupId => $profileIds)
			{
				foreach ($profileIds as $profileId)
				{
					if (!isset($missingRows[$profileId]) || !isset($groups[$groupId]))
					{
						continue;
					}

					$row = clone $missingRows[$profileId];
					$groups[$groupId]->members[] = $row;
					$groups[$groupId]->member_count++;
				}
			}
		}

		return array_values($groups);
	}

	/**
	 * Create the initial ride groups for an event.
	 *
	 * @param   int  $eventId   Event ID.
	 * @param   int  $count     Number of groups to seed.
	 * @param   int  $userId    Joomla user performing the change.
	 *
	 * @return  true|string
	 */
	public function createGroups(int $eventId, int $count, int $userId)
	{
		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return 'invalid_event';
		}

		if (empty($event->is_riding_event))
		{
			return 'not_riding_event';
		}

		if (!in_array((int) ($event->status ?? 0), [1, 4, 5], true))
		{
			return 'event_closed';
		}

		$count = max(2, $count);
		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);

				return 'invalid_event';
			}

			if (!\in_array((int) ($locked->status ?? 0), [1, 4, 5], true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			$existing = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_event_groups'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('published') . ' = 1')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->loadResult();

			if ($existing > 0)
			{
				$db->transactionRollback(true);

				return 'already_exists';
			}

			for ($i = 1; $i <= $count; $i++)
			{
				$record = (object) [
					'event_id'         => (int) $eventId,
					'title'            => 'Group ' . $i,
					'group_order'      => $i,
					'road_captain_id'  => $i === 1 ? (int) ($event->road_captain_id ?? 0) : 0,
					'sweep_id'         => $i === 1 ? (int) ($event->sweep_id ?? 0) : 0,
					'staging_hint'     => '',
					'order_hint'       => '',
					'notes'            => '',
					'published'        => 1,
					'created'          => $now,
					'created_by'       => (int) $userId,
					'modified'         => $now,
					'modified_by'      => (int) $userId,
				];

				$db->insertObject('#__wendfold_event_groups', $record);
			}

			$this->syncGroupRoleMemberships($eventId, $userId);
			$db->transactionCommit(true);

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			Log::add('RC group create DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Append one additional ride group to an event.
	 */
	public function addGroup(int $eventId, int $userId)
	{
		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return 'invalid_event';
		}

		if (empty($event->is_riding_event))
		{
			return 'not_riding_event';
		}

		if (!in_array((int) ($event->status ?? 0), [1, 4, 5], true))
		{
			return 'event_closed';
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);

				return 'invalid_event';
			}

			if (!\in_array((int) ($locked->status ?? 0), [1, 4, 5], true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			$attempt = 0;

			while (true)
			{
				$nextOrder = $this->nextGroupOrder($db, $eventId);
				$record = (object) [
					'event_id'         => (int) $eventId,
					'title'            => 'Group ' . $nextOrder,
					'group_order'      => $nextOrder,
					'road_captain_id'  => 0,
					'sweep_id'         => 0,
					'staging_hint'     => '',
					'order_hint'       => '',
					'notes'            => '',
					'published'        => 1,
					'created'          => $now,
					'created_by'       => (int) $userId,
					'modified'         => $now,
					'modified_by'      => (int) $userId,
				];

				try
				{
					$db->insertObject('#__wendfold_event_groups', $record);
					break;
				}
				catch (\Throwable $error)
				{
					if (DuplicateKey::is($error) && $attempt < 1)
					{
						$attempt++;

						continue;
					}

					throw $error;
				}
			}

			$db->transactionCommit(true);

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			Log::add('RC group add DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Remove one ride group and clear its rider assignments.
	 * At least one group must remain once groups have been created.
	 */
	public function removeGroup(int $eventId, int $groupId, int $userId)
	{
		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return 'invalid_event';
		}

		if (empty($event->is_riding_event))
		{
			return 'not_riding_event';
		}

		if (!in_array((int) ($event->status ?? 0), [1, 4, 5], true))
		{
			return 'event_closed';
		}

		if ($groupId <= 0)
		{
			return 'invalid_group';
		}

		$db = $this->getDatabase();
		$group = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('group_order'),
					$db->quoteName('title'),
					]
				)
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('id') . ' = ' . (int) $groupId)
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
		)->loadObject();

		if (!$group)
		{
			return 'invalid_group';
		}

		$groupCount = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
		)->loadResult();

		if ($groupCount <= 1)
		{
			return 'group_required';
		}

		try
		{
			$db->transactionStart();

			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_event_group_members'))
					->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
					->where($db->quoteName('event_group_id') . ' = ' . (int) $groupId)
			)->execute();

			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_event_groups'))
					->where($db->quoteName('id') . ' = ' . (int) $groupId)
					->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
			)->execute();

			$this->resequenceGroups($eventId, $userId);

			$db->transactionCommit();

			return true;
		}
		catch (\Exception $e)
		{
			$db->transactionRollback();
			Log::add('RC group remove DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Normalize group_order after add/remove operations.
	 * Generic labels such as "Group 3" are kept in sync with the new order.
	 */
	private function resequenceGroups(int $eventId, int $userId): void
	{
		$db = $this->getDatabase();
		$rows = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('group_order'),
					$db->quoteName('title'),
					]
				)
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
				->order($db->quoteName('group_order') . ' ASC, ' . $db->quoteName('id') . ' ASC')
		)->loadObjectList() ?: [];

		if (empty($rows))
		{
			return;
		}

		$now = Factory::getDate()->toSql();
		$position = 1;

		foreach ($rows as $row)
		{
			$title = trim((string) ($row->title ?? ''));
			$isGeneric = preg_match('/^Group\s+\d+$/', $title) === 1;
			$nextTitle = $isGeneric ? 'Group ' . $position : $title;

			$query = $db->createQuery()
				->update($db->quoteName('#__wendfold_event_groups'))
				->set($db->quoteName('group_order') . ' = ' . $position)
				->set($db->quoteName('title') . ' = ' . $db->quote($nextTitle))
				->set($db->quoteName('modified') . ' = ' . $db->quote($now))
				->set($db->quoteName('modified_by') . ' = ' . (int) $userId)
				->where($db->quoteName('id') . ' = ' . (int) $row->id);

			$db->setQuery($query)->execute();
			$position++;
		}
	}

	/**
	 * Save ride-group labels and role assignments.
	 *
	 * @param   int    $eventId     Event ID.
	 * @param   array  $groupsData  Submitted group data keyed by group ID.
	 * @param   int    $userId      Joomla user performing the change.
	 *
	 * @return  true|string
	 */
	public function saveGroups(int $eventId, array $groupsData, int $userId)
	{
		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return 'invalid_event';
		}

		if (empty($event->is_riding_event))
		{
			return 'not_riding_event';
		}

		if (!in_array((int) ($event->status ?? 0), [1, 4, 5], true))
		{
			return 'event_closed';
		}

		if (empty($groupsData))
		{
			return 'invalid_request';
		}

		$roleOptions = $this->getGroupRoleOptions();
		$validRoadCaptainIds = array_map(static fn($row) => (int) $row->value, $roleOptions['road_captains']);
		$validSweepIds = array_map(static fn($row) => (int) $row->value, $roleOptions['sweeps']);
		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);

				return 'invalid_event';
			}

			if (!\in_array((int) ($locked->status ?? 0), [1, 4, 5], true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			foreach ($groupsData as $groupId => $groupData)
			{
				$groupId = (int) $groupId;

				if ($groupId <= 0 || !is_array($groupData))
				{
					continue;
				}

				$exists = (int) $db->setQuery(
					$db->createQuery()
						->select('COUNT(*)')
						->from($db->quoteName('#__wendfold_event_groups'))
						->where($db->quoteName('id') . ' = :groupId')
						->where($db->quoteName('event_id') . ' = :eventId')
						->bind(':groupId', $groupId, ParameterType::INTEGER)
						->bind(':eventId', $eventId, ParameterType::INTEGER)
				)->loadResult();

				if ($exists <= 0)
				{
					continue;
				}

				$groupOrder = max(1, (int) ($groupData['group_order'] ?? $groupData['order'] ?? 0));
				$title = trim((string) ($groupData['title'] ?? ''));

				if ($title === '')
				{
					$title = 'Group ' . $groupOrder;
				}

				$roadCaptainId = (int) ($groupData['road_captain_id'] ?? 0);
				$sweepId = (int) ($groupData['sweep_id'] ?? 0);
				$stagingHint = trim((string) ($groupData['staging_hint'] ?? ''));
				$orderHint = trim((string) ($groupData['order_hint'] ?? ''));

				if ($roadCaptainId > 0 && !in_array($roadCaptainId, $validRoadCaptainIds, true))
				{
					$roadCaptainId = 0;
				}

				if ($sweepId > 0 && !in_array($sweepId, $validSweepIds, true))
				{
					$sweepId = 0;
				}

				$query = $db->createQuery()
					->update($db->quoteName('#__wendfold_event_groups'))
					->set($db->quoteName('title') . ' = :title')
					->set($db->quoteName('staging_hint') . ' = :stagingHint')
					->set($db->quoteName('order_hint') . ' = :orderHint')
					->set($db->quoteName('modified') . ' = :modified')
					->set($db->quoteName('modified_by') . ' = :modifiedBy')
					->where($db->quoteName('id') . ' = :groupId')
					->where($db->quoteName('event_id') . ' = :eventId')
					->bind(':title', $title)
					->bind(':stagingHint', $stagingHint)
					->bind(':orderHint', $orderHint)
					->bind(':modified', $now)
					->bind(':modifiedBy', $userId, ParameterType::INTEGER)
					->bind(':groupId', $groupId, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER);

				if ($roadCaptainId > 0)
				{
					$query->set($db->quoteName('road_captain_id') . ' = :roadCaptainId')
						->bind(':roadCaptainId', $roadCaptainId, ParameterType::INTEGER);
				}
				else
				{
					$query->set($db->quoteName('road_captain_id') . ' = NULL');
				}

				if ($sweepId > 0)
				{
					$query->set($db->quoteName('sweep_id') . ' = :sweepId')
						->bind(':sweepId', $sweepId, ParameterType::INTEGER);
				}
				else
				{
					$query->set($db->quoteName('sweep_id') . ' = NULL');
				}

				$db->setQuery($query)->execute();
			}

			$this->syncGroupRoleMemberships($eventId, $userId);
			$db->transactionCommit(true);

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			Log::add('RC group save DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Assign or clear a rider's ride-group assignment.
	 *
	 * @param   int  $eventId    Event ID.
	 * @param   int  $profileId  Rider profile ID.
	 * @param   int  $groupId    Target group ID, or 0 to clear.
	 * @param   int  $userId     Joomla user performing the change.
	 *
	 * @return  true|string
	 */
	public function assignGroup(int $eventId, int $profileId, int $groupId, int $userId)
	{
		if ($eventId <= 0 || $profileId <= 0)
		{
			return 'invalid_request';
		}

		$event = $this->getEvent($eventId);

		if (!$event)
		{
			return 'invalid_event';
		}

		if (empty($event->is_riding_event))
		{
			return 'not_riding_event';
		}

		if (!in_array((int) ($event->status ?? 0), [1, 4, 5], true))
		{
			return 'event_closed';
		}

		$profileExists = (int) $this->getDatabase()->setQuery(
			$this->getDatabase()->createQuery()
				->select('COUNT(*)')
				->from($this->getDatabase()->quoteName('#__wendfold_profiles'))
				->where($this->getDatabase()->quoteName('id') . ' = ' . (int) $profileId)
				->where($this->getDatabase()->quoteName('published') . ' = 1')
		)->loadResult();

		if ($profileExists <= 0)
		{
			return 'invalid_member';
		}

		$isParticipant = $this->hasExplicitRsvp($eventId, $profileId)
			|| $this->hasAttendance($eventId, $profileId)
			|| in_array($profileId, $this->getAssignedProfileIds($eventId), true);

		if (!$isParticipant)
		{
			return 'invalid_member';
		}

		$roleGroupMap = $this->getRoleAssignedGroupMap($eventId);
		$lockedGroupId = (int) ($roleGroupMap[$profileId] ?? 0);

		if ($lockedGroupId > 0 && $groupId !== $lockedGroupId)
		{
			return 'role_locked';
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);

				return 'invalid_event';
			}

			if (!\in_array((int) ($locked->status ?? 0), [1, 4, 5], true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			if ($groupId <= 0)
			{
				$db->setQuery(
					$db->createQuery()
						->delete($db->quoteName('#__wendfold_event_group_members'))
						->where($db->quoteName('event_id') . ' = :eventId')
						->where($db->quoteName('profile_id') . ' = :profileId')
						->bind(':eventId', $eventId, ParameterType::INTEGER)
						->bind(':profileId', $profileId, ParameterType::INTEGER)
				)->execute();
				$db->transactionCommit(true);

				return true;
			}

			$groupExists = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_event_groups'))
					->where($db->quoteName('id') . ' = :groupId')
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('published') . ' = 1')
					->bind(':groupId', $groupId, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->loadResult();

			if ($groupExists <= 0)
			{
				$db->transactionRollback(true);

				return 'invalid_group';
			}

			$this->ensureGroupMembership($db, $eventId, $groupId, $profileId, $userId, $now);
			$db->transactionCommit(true);

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			Log::add('RC group assign DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Manually check a member in (method=2 / RC manual).
	 *
	 * @return  true|string
	 */
	public function addMember(int $eventId, int $profileId, int $checkedInBy)
	{
		$db = $this->getDatabase();
		$eventId = (int) $eventId;
		$profileId = (int) $profileId;
		$year = (int) Factory::getDate()->format('Y');

		$eligibilityQuery = $db->createQuery()
			->select([
				'p.waiver_signed',
				'p.dues_paid',
				'CASE WHEN dp.id IS NOT NULL THEN 1 ELSE 0 END AS paid_this_year',
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__wendfold_dues_payments', 'dp')
				. ' ON dp.profile_id = p.id AND dp.year = ' . $year
			)
			->where($db->quoteName('p.id') . ' = ' . $profileId);

		$profile = $db->setQuery($eligibilityQuery)->loadObject();

		if (!$profile)
		{
			return 'invalid_profile';
		}

		if (empty($profile->waiver_signed))
		{
			return 'waiver_required';
		}

		$record = new \stdClass;
		$record->event_id = $eventId;
		$record->profile_id = $profileId;
		$record->check_in_time = Factory::getDate()->toSql();
		$record->check_in_method = 2;
		$record->checked_in_by = (int) $checkedInBy;
		$record->notes = '';

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

			if ((int) $locked->status === 3)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'event_closed';
			}

			$dupQuery = $db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('event_id') . ' = :eventId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER);

			if ((int) $db->setQuery($dupQuery)->loadResult() > 0)
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
				/*
				 * Only treat as already checked in when the attendance row is
				 * still present after rollback. A duplicate from another insert
				 * in the same transaction (metrics/log) must surface as db_error.
				 */
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
					return 'already';
				}

				return 'db_error';
			}

			Log::add('RC attendance DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Remove a member from the roster by attendance ID.
	 *
	 * @return  true|string
	 */
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

				return 'invalid_request';
			}

			if ((int) $locked->status === 3)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'event_closed';
			}

			$attendance = $db->setQuery(
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

			if (!$attendance)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'invalid_request';
			}

			$query = $db->createQuery()
				->delete($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('id') . ' = :attendanceId')
				->where($db->quoteName('event_id') . ' = :eventId')
				->bind(':attendanceId', $attendanceId, ParameterType::INTEGER)
				->bind(':eventId', $eventId, ParameterType::INTEGER);
			$db->setQuery($query)->execute();

			if ((int) $db->getAffectedRows() !== 1)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'invalid_request';
			}

			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column
			$profileId = (int) $attendance->profile_id;
			$metrics->trashAttendance($eventId, $attendanceId, $profileId);
			$this->clearOptionalGroupAssignment($eventId, $profileId);
			$db->transactionCommit(true);
			$metrics->flushAwardRecompute();

			return true;
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			Log::add('RC attendance DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Update an active event to Scheduled, Confirmed, or Weather Watch.
	 *
	 * @return  true|string
	 */
	public function setEventStatus(int $eventId, int $status)
	{
		if (!\in_array($status, [1, 4, 5], true))
		{
			return 'invalid_status';
		}

		$db = $this->getDatabase();
		$eventId = (int) $eventId;
		$allowed = [1, 4, 5];

		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null || !\in_array((int) $locked->status, $allowed, true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			$db->setQuery(
				$db->createQuery()
					->update($db->quoteName('#__wendfold_events'))
					->set($db->quoteName('status') . ' = :status')
					->where($db->quoteName('id') . ' = :eventId')
					->where($db->quoteName('status') . ' IN (1,4,5)')
					->bind(':status', $status, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->execute();

			$outcome = EventStatusTransition::resolve(
				$db,
				$eventId,
				(int) $db->getAffectedRows(),
				$status,
				$allowed
			);

			if ($outcome !== EventStatusTransition::OK)
			{
				$db->transactionRollback(true);

				if ($outcome === EventStatusTransition::DB_ERROR)
				{
					Log::add(
						'RC attendance status update affected 0 rows while event stayed open',
						Log::ERROR,
						'com_wendfold'
					);

					return 'db_error';
				}

				return 'event_closed';
			}

			$db->transactionCommit(true);

			return true;
		}
		catch (\Exception $e)
		{
			$db->transactionRollback(true);
			Log::add('RC attendance status update DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}

	/**
	 * Mark an event as Completed (status=2) and trigger ride generation.
	 *
	 * @return  true|string
	 */
	public function completeEvent(int $eventId, int $completedByUserId)
	{
		$db = $this->getDatabase();
		$eventId = (int) $eventId;
		$completedByUserId = (int) $completedByUserId;
		$now = Factory::getDate()->toSql();
		$allowed = [1, 4];

		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null || !\in_array((int) $locked->status, $allowed, true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			$db->setQuery(
				$db->createQuery()
					->update($db->quoteName('#__wendfold_events'))
					->set($db->quoteName('status') . ' = 2')
					->set($db->quoteName('completed_date') . ' = :completedDate')
					->set($db->quoteName('completed_by') . ' = :completedBy')
					->where($db->quoteName('id') . ' = :eventId')
					->where($db->quoteName('status') . ' IN (1,4)')
					->bind(':completedDate', $now)
					->bind(':completedBy', $completedByUserId, ParameterType::INTEGER)
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->execute();

			$outcome = EventStatusTransition::resolve(
				$db,
				$eventId,
				(int) $db->getAffectedRows(),
				2,
				$allowed
			);

			if ($outcome !== EventStatusTransition::OK)
			{
				$db->transactionRollback(true);

				if ($outcome === EventStatusTransition::DB_ERROR)
				{
					Log::add(
						'RC attendance complete affected 0 rows while event stayed open',
						Log::ERROR,
						'com_wendfold'
					);

					return 'db_error';
				}

				return 'event_closed';
			}

			$db->transactionCommit(true);
		}
		catch (\Exception $e)
		{
			$db->transactionRollback(true);
			Log::add('RC attendance complete event DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}

		return true;
	}

	/**
	 * Mark an event as Cancelled (status=3).
	 *
	 * Locks the row, refuses cancel when check-ins exist, and uses a
	 * conditional UPDATE plus EventStatusTransition. Cancel notices run
	 * only after commit when the UPDATE actually changed the status.
	 *
	 * @return  true|string
	 */
	public function cancelEvent(int $eventId)
	{
		$db = $this->getDatabase();
		$eventId = (int) $eventId;
		$allowed = [1, 4, 5];

		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null || !\in_array((int) $locked->status, $allowed, true))
			{
				$db->transactionRollback(true);

				return 'event_closed';
			}

			$attendanceCount = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->loadResult();

			if ($attendanceCount > 0)
			{
				$db->transactionRollback(true);

				return 'has_checkins';
			}

			$db->setQuery(
				$db->createQuery()
					->update($db->quoteName('#__wendfold_events'))
					->set($db->quoteName('status') . ' = 3')
					->where($db->quoteName('id') . ' = :eventId')
					->where($db->quoteName('status') . ' IN (1,4,5)')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
			)->execute();

			$affected = (int) $db->getAffectedRows();
			$outcome  = EventStatusTransition::resolve(
				$db,
				$eventId,
				$affected,
				3,
				$allowed
			);

			if ($outcome !== EventStatusTransition::OK)
			{
				$db->transactionRollback(true);

				if ($outcome === EventStatusTransition::DB_ERROR)
				{
					Log::add(
						'RC attendance cancel affected 0 rows while event stayed open',
						Log::ERROR,
						'com_wendfold'
					);

					return 'db_error';
				}

				return 'event_closed';
			}

			$db->transactionCommit(true);
			EventCancelNoticeService::notifyAfterTransition(
				$db,
				$eventId,
				EventCancelNoticeService::shouldNotify($affected, $outcome)
			);

			return true;
		}
		catch (\Exception $e)
		{
			$db->transactionRollback(true);
			Log::add('RC attendance DB error: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}
}
