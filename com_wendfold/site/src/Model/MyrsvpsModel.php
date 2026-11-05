<?php
/**
 * Wendfold - Chapter Management System
 * Site My RSVPs Model
 *
 * Returns upcoming and past event participation for the logged-in member,
 * including implicit participation when assigned as an event or ride-group
 * Road Captain / Sweep, plus Overnight Trip Lead / Trip Sweep roles.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class MyrsvpsModel extends BaseDatabaseModel
{
	/**
	 * Resolve the chapter member profile row for a given Joomla user ID.
	 */
	public function getProfileByUserId(int $userId): ?object
	{
		$db = $this->getDatabase();
		$q = $db->createQuery()
			->select($db->quoteName(['id', 'user_id', 'member_number']))
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('user_id') . ' = ' . (int) $userId)
			->where($db->quoteName('published') . ' = 1');

		return $db->setQuery($q)->loadObject() ?: null;
	}

	/**
	 * Shared query builder for upcoming and past lists.
	 */
	private function buildListQuery(int $profileId, bool $upcoming)
	{
		$db = $this->getDatabase();
		$siteTime = SiteTime::fromApplication();
		$today = $siteTime->today();
		$dateWhere = $db->quoteName('e.event_date') . ' ' . $siteTime->eventsListOperator($upcoming ? 'upcoming' : 'past') . ' ' . $db->quote($today);

		$orderBy = $upcoming
			? $db->quoteName('e.event_date') . ' ASC'
			: $db->quoteName('e.event_date') . ' DESC';

		$allowed = implode(',', AudienceHelper::allowedScopes($this->audienceFlagsForProfile($profileId)));

		$roleGroupIdSubquery = '(SELECT rg.id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupTitleSubquery = '(SELECT rg.title FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderSubquery = '(SELECT rg.group_order FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupStagingSubquery = '(SELECT rg.staging_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderHintSubquery = '(SELECT rg.order_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupRoadCaptainNameSubquery = '(SELECT rc_u.name FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' LEFT JOIN ' . $db->quoteName('#__wendfold_profiles', 'rc_p') . ' ON rc_p.id = rg.road_captain_id'
			. ' LEFT JOIN ' . $db->quoteName('#__users', 'rc_u') . ' ON rc_u.id = rc_p.user_id'
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupSweepNameSubquery = '(SELECT sw_u.name FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' LEFT JOIN ' . $db->quoteName('#__wendfold_profiles', 'sw_p') . ' ON sw_p.id = rg.sweep_id'
			. ' LEFT JOIN ' . $db->quoteName('#__users', 'sw_u') . ' ON sw_u.id = sw_p.user_id'
			. ' WHERE rg.event_id = e.id'
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = ' . (int) $profileId . ' OR rg.sweep_id = ' . (int) $profileId . ')'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$volunteerRoleTitleSubquery = '(SELECT vr.title FROM ' . $db->quoteName('#__wendfold_event_volunteers', 'vv')
			. ' INNER JOIN ' . $db->quoteName('#__wendfold_event_volunteer_roles', 'vr') . ' ON vr.id = vv.volunteer_role_id'
			. ' WHERE vv.event_id = e.id'
			. ' AND vv.profile_id = ' . (int) $profileId
			. ' AND vv.assignment_status IN (1,2,3)'
			. ' ORDER BY CASE vv.assignment_status WHEN 2 THEN 0 WHEN 1 THEN 1 WHEN 3 THEN 2 ELSE 3 END ASC, vv.modified DESC, vv.id DESC LIMIT 1)';
		$volunteerStatusSubquery = '(SELECT vv.assignment_status FROM ' . $db->quoteName('#__wendfold_event_volunteers', 'vv')
			. ' WHERE vv.event_id = e.id'
			. ' AND vv.profile_id = ' . (int) $profileId
			. ' AND vv.assignment_status IN (1,2,3)'
			. ' ORDER BY CASE vv.assignment_status WHEN 2 THEN 0 WHEN 1 THEN 1 WHEN 3 THEN 2 ELSE 3 END ASC, vv.modified DESC, vv.id DESC LIMIT 1)';

		return $db->createQuery()
			->select([
				'r.id AS rsvp_id',
				'e.id AS event_id',
				'r.created AS rsvp_date',
				'e.title',
				'e.event_date',
				'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title',
				'e.workflow_mode',
				'e.parent_event_id',

				'a.id AS attendance_id',
				'a.check_in_time AS attendance_time',
				'CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS has_attendance',
				'e.event_location_custom AS location_name',
				'e.event_city AS location_city',
				'e.event_state AS location_state',
				'l.name AS saved_location_name',
				'CASE WHEN e.road_captain_id = ' . (int) $profileId . ' THEN 1 ELSE 0 END AS is_road_captain',
				'CASE WHEN e.sweep_id = ' . (int) $profileId . ' THEN 1 ELSE 0 END AS is_sweep',
				'CASE WHEN e.trip_lead_id = ' . (int) $profileId . ' THEN 1 ELSE 0 END AS is_trip_lead',
				'CASE WHEN e.trip_sweep_id = ' . (int) $profileId . ' THEN 1 ELSE 0 END AS is_trip_sweep',
				'COALESCE(tp.lodging_status, 0) AS lodging_status',
				'tp.booked_at AS trip_booked_at',
				'tp.lodging_notes AS trip_lodging_notes',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_rc')
					. ' WHERE rg_rc.event_id = e.id AND rg_rc.published = 1 AND rg_rc.road_captain_id = ' . (int) $profileId
					. ') THEN 1 ELSE 0 END AS is_group_road_captain',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_sw')
					. ' WHERE rg_sw.event_id = e.id AND rg_sw.published = 1 AND rg_sw.sweep_id = ' . (int) $profileId
					. ') THEN 1 ELSE 0 END AS is_group_sweep',
				'CASE WHEN e.road_captain_id = ' . (int) $profileId
					. ' OR e.sweep_id = ' . (int) $profileId
					. ' OR e.trip_lead_id = ' . (int) $profileId
					. ' OR e.trip_sweep_id = ' . (int) $profileId
					. ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_rcx')
					. ' WHERE rg_rcx.event_id = e.id AND rg_rcx.published = 1 AND rg_rcx.road_captain_id = ' . (int) $profileId . ')'
					. ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_swx')
					. ' WHERE rg_swx.event_id = e.id AND rg_swx.published = 1 AND rg_swx.sweep_id = ' . (int) $profileId . ')'
					. ' THEN 0 WHEN r.id IS NOT NULL THEN 1 ELSE 0 END AS can_cancel',
				'COALESCE(' . $roleGroupIdSubquery . ', gm.event_group_id) AS group_id',
				'COALESCE(' . $roleGroupTitleSubquery . ', g.title) AS group_title',
				'COALESCE(' . $roleGroupOrderSubquery . ', g.group_order) AS group_order',
				'COALESCE(' . $roleGroupStagingSubquery . ', g.staging_hint) AS group_staging_hint',
				'COALESCE(' . $roleGroupOrderHintSubquery . ', g.order_hint) AS group_order_hint',
				'COALESCE(' . $roleGroupRoadCaptainNameSubquery . ', grc_u.name) AS group_road_captain_name',
				'COALESCE(' . $roleGroupSweepNameSubquery . ', gsw_u.name) AS group_sweep_name',
				$volunteerRoleTitleSubquery . ' AS volunteer_role_title',
				'COALESCE(' . $volunteerStatusSubquery . ', 0) AS volunteer_assignment_status',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->join('LEFT', $db->quoteName('#__wendfold_rsvps', 'r')
				. ' ON ' . $db->quoteName('r.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('r.profile_id') . ' = ' . (int) $profileId
			)
			->join('LEFT', $db->quoteName('#__wendfold_attendance', 'a')
				. ' ON ' . $db->quoteName('a.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('a.profile_id') . ' = ' . (int) $profileId
			)
			->join('LEFT', $db->quoteName('#__wendfold_event_trip_participants', 'tp')
				. ' ON ' . $db->quoteName('tp.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('tp.profile_id') . ' = ' . (int) $profileId
			)
			->join('LEFT', $db->quoteName('#__wendfold_locations', 'l')
				. ' ON ' . $db->quoteName('l.id') . ' = ' . $db->quoteName('e.event_location_id')
			)
			->join('LEFT', $db->quoteName('#__wendfold_event_group_members', 'gm')
				. ' ON ' . $db->quoteName('gm.event_id') . ' = ' . $db->quoteName('e.id')
				. ' AND ' . $db->quoteName('gm.profile_id') . ' = ' . (int) $profileId
			)
			->join('LEFT', $db->quoteName('#__wendfold_event_groups', 'g')
				. ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('gm.event_group_id')
				. ' AND ' . $db->quoteName('g.published') . ' = 1'
			)
			->join('LEFT', $db->quoteName('#__wendfold_profiles', 'grc_p')
				. ' ON ' . $db->quoteName('grc_p.id') . ' = ' . $db->quoteName('g.road_captain_id')
			)
			->join('LEFT', $db->quoteName('#__users', 'grc_u')
				. ' ON ' . $db->quoteName('grc_u.id') . ' = ' . $db->quoteName('grc_p.user_id')
			)
			->join('LEFT', $db->quoteName('#__wendfold_profiles', 'gsw_p')
				. ' ON ' . $db->quoteName('gsw_p.id') . ' = ' . $db->quoteName('g.sweep_id')
			)
			->join('LEFT', $db->quoteName('#__users', 'gsw_u')
				. ' ON ' . $db->quoteName('gsw_u.id') . ' = ' . $db->quoteName('gsw_p.user_id')
			)
			->where('(' . $db->quoteName('r.id') . ' IS NOT NULL'
				. ' OR ' . $db->quoteName('a.id') . ' IS NOT NULL'
				. ' OR ' . $db->quoteName('e.road_captain_id') . ' = ' . (int) $profileId
				. ' OR ' . $db->quoteName('e.sweep_id') . ' = ' . (int) $profileId
				. ' OR ' . $db->quoteName('e.trip_lead_id') . ' = ' . (int) $profileId
				. ' OR ' . $db->quoteName('e.trip_sweep_id') . ' = ' . (int) $profileId
				. ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_rcy')
				. ' WHERE rg_rcy.event_id = e.id AND rg_rcy.published = 1 AND rg_rcy.road_captain_id = ' . (int) $profileId . ')'
				. ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg_swy')
				. ' WHERE rg_swy.event_id = e.id AND rg_swy.published = 1 AND rg_swy.sweep_id = ' . (int) $profileId . ')'
				. ' OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_volunteers', 'vvx')
				. ' WHERE vvx.event_id = e.id AND vvx.profile_id = ' . (int) $profileId . ' AND vvx.assignment_status IN (1,2,3)))'
			)
			->where($dateWhere)
			->where($db->quoteName('e.published') . ' = 1')
			->where($upcoming ? $db->quoteName('e.status') . ' != 3' : '1=1')
			->where($db->quoteName('e.audience_scope') . ' IN (' . $allowed . ')')
			->order($orderBy);
	}

	/**
	 * Published-profile audience flags for a profile id, or null when it is not published.
	 *
	 * @param   integer  $profileId  Member profile id.
	 *
	 * @return  array|null
	 *
	 * @since   1.0.0
	 */
	private function audienceFlagsForProfile(int $profileId): ?array
	{
		if ($profileId <= 0)
		{
			return null;
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select(
				[
						'p.is_road_captain',
						'CASE WHEN o.id IS NULL THEN 0 ELSE 1 END AS is_officer',
					]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__wendfold_officers', 'o')
					. ' ON o.profile_id = p.id AND o.is_current = 1 AND o.published = 1'
			)
			->where($db->quoteName('p.id') . ' = ' . (int) $profileId)
			->where($db->quoteName('p.published') . ' = 1');
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)
			->loadObject();

		return AudienceHelper::flagsFromProfile($row ?: null);
	}

	/**
	 * Return upcoming events tied to the given profile.
	 */
	public function getUpcoming(int $profileId): array
	{
		return $this->getDatabase()->setQuery($this->buildListQuery($profileId, true))->loadObjectList() ?: [];
	}

	/**
	 * Return past events tied to the given profile.
	 */
	public function getPast(int $profileId): array
	{
		$query = $this->buildListQuery($profileId, false);
		$query->setLimit(20, 0);

		return $this->getDatabase()->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Remove a group assignment when it should not survive RSVP cancellation.
	 */
	private function clearOptionalGroupAssignment(int $eventId, int $profileId): void
	{
		$db = $this->getDatabase();

		$assignedIds = [];

		$event = $db->setQuery(
			$db->createQuery()
				->select([$db->quoteName('road_captain_id'), $db->quoteName('sweep_id'), $db->quoteName('trip_lead_id'), $db->quoteName('trip_sweep_id')]) // phpcs:ignore Generic.Files.LineLength.TooLong
				->from($db->quoteName('#__wendfold_events'))
				->where($db->quoteName('id') . ' = ' . (int) $eventId)
		)->loadObject();

		if ($event)
		{
			$assignedIds[] = (int) ($event->road_captain_id ?? 0);
			$assignedIds[] = (int) ($event->sweep_id ?? 0);
			$assignedIds[] = (int) ($event->trip_lead_id ?? 0);
			$assignedIds[] = (int) ($event->trip_sweep_id ?? 0);
		}

		$groupRoles = $db->setQuery(
			$db->createQuery()
				->select([$db->quoteName('road_captain_id'), $db->quoteName('sweep_id')])
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('published') . ' = 1')
		)->loadObjectList() ?: [];

		foreach ($groupRoles as $groupRole)
		{
			$assignedIds[] = (int) ($groupRole->road_captain_id ?? 0);
			$assignedIds[] = (int) ($groupRole->sweep_id ?? 0);
		}

		$assignedIds = array_values(array_unique(array_filter(array_map('intval', $assignedIds))));

		if (in_array($profileId, $assignedIds, true))
		{
			return;
		}

		$hasAttendance = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->loadResult() > 0;

		if ($hasAttendance)
		{
			return;
		}

		$db->setQuery(
			$db->createQuery()
				->delete($db->quoteName('#__wendfold_event_group_members'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->execute();
	}

	/**
	 * Cancel a single RSVP, verifying ownership before deleting.
	 */
	public function cancelRsvp(int $eventId, int $profileId): bool
	{
		if ($eventId <= 0 || $profileId <= 0)
		{
			return false;
		}

		$db = $this->getDatabase();

		$check = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_rsvps'))
			->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
			->where($db->quoteName('profile_id') . ' = ' . (int) $profileId);

		$rsvpId = $db->setQuery($check)->loadResult();

		if (!$rsvpId)
		{
			return false;
		}

		$del = $db->createQuery()
			->delete($db->quoteName('#__wendfold_rsvps'))
			->where($db->quoteName('id') . ' = ' . (int) $rsvpId);

		$result = (bool) $db->setQuery($del)->execute();

		if ($result)
		{
			$this->clearOptionalGroupAssignment($eventId, $profileId);

			$limitQuery = $db->createQuery()
				->select([$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'), $db->quoteName('workflow_mode')]) // phpcs:ignore Generic.Files.LineLength.TooLong
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . (int) $eventId);
			$limitQuery->setLimit(1, 0);
			$event = $db->setQuery($limitQuery)->loadObject();

			if ($event && (\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0)))) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			{
				$db->setQuery(
					$db->createQuery()
						->delete($db->quoteName('#__wendfold_event_trip_participants'))
						->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
						->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
				)->execute();
			}
		}

		return $result;
	}
}
