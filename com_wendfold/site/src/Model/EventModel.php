<?php
/**
 * Wendfold - Site Single Event Model
 *
 * Loads the event detail page, RSVP state, rider roster, and ride-group data.
 * Ride groups supplement RSVP/attendance; they do not replace either workflow.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Site\Model;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Helper\OfficerRoleKey;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Site\Helper\GpxFile;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

class EventModel extends ItemModel
{
	protected $table = '#__wendfold_events';

	protected $tableKey = 'id';

	protected function populateState(): void
	{
		$id = Factory::getApplication()->getInput()->getInt('id', 0);
		$this->setState('event.id', $id);
	}

	public function getItem($pk = null): ?object
	{
		$pk = (int) ($pk ?: StateValue::get($this->getState(), 'event.id'));

		if (!$pk)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'e.*',
				EventMetricCredits::distanceExpression($db, 'e') . ' AS ' . $db->quoteName('distance_credit'),
				'et.behavior AS event_behavior',
				'et.title AS event_type_title',
				'ksu_loc.name AS ksu_location_name',
				'ksu_loc.address AS ksu_loc_address',
				'ksu_loc.city AS ksu_loc_city',
				'ksu_loc.state AS ksu_loc_state',
				'ksu_loc.zip AS ksu_loc_zip',
				'ksu_loc.lat AS ksu_loc_lat',
				'ksu_loc.lng AS ksu_loc_lng',
				'ksd_loc.name AS ksd_location_name',
				'ksd_loc.address AS ksd_loc_address',
				'ksd_loc.city AS ksd_loc_city',
				'ksd_loc.state AS ksd_loc_state',
				'ksd_loc.zip AS ksd_loc_zip',
				'ksd_loc.lat AS ksd_loc_lat',
				'ksd_loc.lng AS ksd_loc_lng',
				'evt_loc.name AS event_location_name',
				'evt_loc.address AS evt_loc_address',
				'evt_loc.city AS evt_loc_city',
				'evt_loc.state AS evt_loc_state',
				'evt_loc.zip AS evt_loc_zip',
				'evt_loc.lat AS evt_loc_lat',
				'evt_loc.lng AS evt_loc_lng',
				'rc_u.name AS road_captain_name',
				'rc_p.photo AS road_captain_photo',
				'sw_u.name AS sweep_name',
				'sw_p.photo AS sweep_photo',
				'trip_rc_u.name AS trip_lead_name',
				'trip_sw_u.name AS trip_sweep_name',
			])
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin($db->quoteName('#__wendfold_locations', 'ksu_loc') . ' ON ksu_loc.id = e.ksu_location_id')
			->leftJoin($db->quoteName('#__wendfold_locations', 'ksd_loc') . ' ON ksd_loc.id = e.ksd_location_id')
			->leftJoin($db->quoteName('#__wendfold_locations', 'evt_loc') . ' ON evt_loc.id = e.event_location_id')
			->leftJoin($db->quoteName('#__wendfold_profiles', 'rc_p') . ' ON rc_p.id = e.road_captain_id')
			->leftJoin($db->quoteName('#__users', 'rc_u') . ' ON rc_u.id = rc_p.user_id')
			->leftJoin($db->quoteName('#__wendfold_profiles', 'sw_p') . ' ON sw_p.id = e.sweep_id')
			->leftJoin($db->quoteName('#__users', 'sw_u') . ' ON sw_u.id = sw_p.user_id')
			->leftJoin($db->quoteName('#__wendfold_profiles', 'trip_rc_p') . ' ON trip_rc_p.id = e.trip_lead_id')
			->leftJoin($db->quoteName('#__users', 'trip_rc_u') . ' ON trip_rc_u.id = trip_rc_p.user_id')
			->leftJoin($db->quoteName('#__wendfold_profiles', 'trip_sw_p') . ' ON trip_sw_p.id = e.trip_sweep_id')
			->leftJoin($db->quoteName('#__users', 'trip_sw_u') . ' ON trip_sw_u.id = trip_sw_p.user_id')
			->where($db->quoteName('e.id') . ' = ' . $pk)
			->where($db->quoteName('e.published') . ' = 1');

		$item = $db->setQuery($query)->loadObject();

		if (!$item)
		{
			return null;
		}

		$flags = $this->getCurrentUserFlags();
		$currentUserId = (int) Factory::getApplication()->getIdentity()->id;

		if (!$this->canAccessEventDetail($item, $flags, $currentUserId))
		{
			return null;
		}

		$storedScope = get_object_vars($item);
		$item->canRsvpByScope = $this->canAccessAudience((int) ($storedScope['audience_scope'] ?? 1), $flags);

		foreach (['ksu_time', 'ksd_time'] as $field)
		{
			if (!empty($item->$field))
			{
				$ts = strtotime((string) $item->$field);
				$item->$field = $ts ? date('g:i A', $ts) : $item->$field;
			}
		}

		$item->ksu_display = $item->ksu_location_name ?: $item->ksu_location_custom;
		$item->ksd_display = $item->ksd_location_name ?: $item->ksd_location_custom;
		$item->evt_display = $item->event_location_name ?: $item->event_location_custom;

		$item->ksu_addr = trim((string) ($item->ksu_loc_address ?: ($item->ksu_address ?? '')));
		$item->ksu_city = trim((string) ($item->ksu_loc_city ?: ($item->ksu_city ?? '')));
		$item->ksu_state = trim((string) ($item->ksu_loc_state ?: ($item->ksu_state ?? '')));
		$item->ksu_zip = trim((string) ($item->ksu_loc_zip ?: ($item->ksu_zip ?? '')));

		$item->ksd_addr = trim((string) ($item->ksd_loc_address ?: ($item->ksd_address ?? '')));
		$item->ksd_city = trim((string) ($item->ksd_loc_city ?: ($item->ksd_city ?? '')));
		$item->ksd_state = trim((string) ($item->ksd_loc_state ?: ($item->ksd_state ?? '')));
		$item->ksd_zip = trim((string) ($item->ksd_loc_zip ?: ($item->ksd_zip ?? '')));

		$item->evt_addr = trim((string) ($item->evt_loc_address ?: ($item->event_address ?? '')));
		$item->evt_city = trim((string) ($item->evt_loc_city ?: ($item->event_city ?? '')));
		$item->evt_state = trim((string) ($item->evt_loc_state ?: ($item->event_state ?? '')));
		$item->evt_zip = trim((string) ($item->evt_loc_zip ?: ($item->event_zip ?? '')));

		if ((int) ($item->workflow_mode ?? 0) <= 0)
		{
			$item->workflow_mode = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::workflow((int) ($item->event_behavior ?? 0)); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		}

		if (\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($item->event_behavior ?? 0), (int) ($item->workflow_mode ?? 0))) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			if (empty($item->start_date))
			{
				$item->start_date = $item->event_date;
			}

			if (empty($item->end_date))
			{
				$item->end_date = $item->start_date ?: $item->event_date;
			}
		}

		return $item;
	}

	/**
	 * Id of the visitor who is looking at the page.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function currentUserId(): int
	{
		return (int) Factory::getApplication()->getIdentity()->id;
	}

	private function getCurrentUserFlags(): ?object
	{
		return $this->getProfileFlagsByUserId($this->currentUserId());
	}

	private function getProfileFlagsByUserId(int $userId): ?object
	{
		if (!$userId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'p.id AS profile_id',
				'p.user_id',
				'p.is_road_captain',
				'p.is_road_crew',
				'CASE WHEN o.id IS NULL THEN 0 ELSE 1 END AS is_officer',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'vo')
					. ' INNER JOIN ' . $db->quoteName('#__wendfold_officer_roles', 'vor') . ' ON vor.id = vo.role_id'
					. ' WHERE vo.profile_id = p.id AND vo.is_current = 1 AND vo.published = 1'
					. ' AND vor.system_key = ' . $db->quote(OfficerRoleKey::VOLUNTEER_OFFICER) . ') THEN 1 ELSE 0 END AS is_volunteer_officer',
			])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin($db->quoteName('#__wendfold_officers', 'o') . ' ON o.profile_id = p.id AND o.is_current = 1 AND o.published = 1')
			->where($db->quoteName('p.user_id') . ' = ' . $userId)
			->where($db->quoteName('p.published') . ' = 1');

		$query->setLimit(1, 0);
		$row = $db->setQuery($query)->loadObject();

		if (!$row)
		{
			return null;
		}

		$row->profile_id = (int) ($row->profile_id ?? 0);
		$row->is_road_captain = !empty($row->is_road_captain);
		$row->is_road_crew = !empty($row->is_road_crew);
		$row->is_officer = !empty($row->is_officer);
		$row->is_volunteer_officer = !empty($row->is_volunteer_officer);

		return $row;
	}

	/**
	 * Whether a profile row may see this audience value.
	 *
	 * Null means a guest or an account with no published member profile.
	 *
	 * @param   integer      $audienceScope  Event audience_scope value.
	 * @param   object|null  $flags          Published profile flags, or null.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function canAccessAudience(int $audienceScope, ?object $flags): bool
	{
		return AudienceHelper::canAccess($audienceScope, AudienceHelper::flagsFromProfile($flags));
	}

	/**
	 * Whether the current visitor may see this audience value.
	 *
	 * @param   integer  $audienceScope  Event audience_scope value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function currentUserCanAccessAudience(int $audienceScope): bool
	{
		return $this->canAccessAudience($audienceScope, $this->getCurrentUserFlags());
	}

	/**
	 * Whether the current visitor may open this event, including its GPX file.
	 *
	 * @param   object  $event  Event row with audience and volunteer fields.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function currentUserCanAccessEventDetail(object $event): bool
	{
		$userId = $this->currentUserId();
		$flags = $userId > 0 ? $this->getProfileFlagsByUserId($userId) : null;

		return $this->canAccessEventDetail($event, $flags, $userId);
	}

	/**
	 * Absolute path of a published event GPX file the current user may see.
	 *
	 * Uses the same decision as the event detail page. Returns null when the
	 * event is missing, the visitor cannot open it, or the stored path is not
	 * a regular .gpx file under media/com_wendfold/gpx.
	 *
	 * @param   integer  $eventId  Event primary key.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function getAccessibleGpxPath(int $eventId): ?string
	{
		if ($eventId <= 0)
		{
			return null;
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select(
				[
					$db->quoteName('audience_scope'),
					$db->quoteName('gpx_file'),
					$db->quoteName('published'),
					$db->quoteName('volunteer_manager_id'),
					$db->quoteName('volunteer_backup_id'),
				]
			)
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = ' . $eventId);
		$limitQuery->setLimit(1, 0);
		$event = $db->setQuery($limitQuery)
			->loadObject();

		if (!$event || (int) (get_object_vars($event)['published'] ?? 0) !== 1)
		{
			return null;
		}

		if (!$this->currentUserCanAccessEventDetail($event))
		{
			return null;
		}

		$stored = get_object_vars($event);

		return self::resolveGpxFile((string) ($stored['gpx_file'] ?? ''));
	}

	/**
	 * Resolve a stored GPX path and refuse anything that is not a local .gpx file.
	 *
	 * @param   string  $relative  Value stored on the event.
	 *
	 * @return  string|null  Absolute path, or null when it is not a local GPX file.
	 *
	 * @since   1.0.0
	 */
	private static function resolveGpxFile(string $relative): ?string
	{
		return GpxFile::resolveStoredPath($relative, (string) \constant('JPATH_ROOT'));
	}

	private function isMeetingEventRecord(?object $event): bool
	{
		if (!$event)
		{
			return false;
		}

		return \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isMeeting((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0)); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
	}

	private function getMeetingAttendanceEvent(int $eventId): ?object
	{
		if (!$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select([
				$db->quoteName('e.id'),
				$db->quoteName('e.published'),
				$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'), // phpcs:ignore Generic.Files.LineLength.TooLong
				$db->quoteName('workflow_mode'),

				$db->quoteName('audience_scope'),
			])
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->where($db->quoteName('e.id') . ' = ' . (int) $eventId);
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	public function canManageMeetingAttendance(int $eventId, int $userId): bool
	{
		if (!$eventId || !$userId)
		{
			return false;
		}

		$identity = Factory::getApplication()->getIdentity();

		if ($identity->authorise('core.admin', 'com_wendfold') || $identity->authorise('core.manage', 'com_wendfold'))
		{
			return true;
		}

		$event = $this->getMeetingAttendanceEvent($eventId);
		$flags = $this->getProfileFlagsByUserId($userId);

		if (!$event || (int) ($event->published ?? 0) !== 1 || !$flags)
		{
			return false;
		}

		if (!$this->isMeetingEventRecord($event) || !$this->canAccessAudience((int) ($event->audience_scope ?? 1), $flags)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return false;
		}

		return \Wendfold\Component\Wendfold\Administrator\Helper\MeetingAudience::canTakeRoll(
			(int) ($event->audience_scope ?? 1), // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
			!empty($flags->is_officer),
			!empty($flags->is_road_captain)
		);
	}

	private function isVolunteerAdminOverride(int $userId): bool
	{
		$identity = Factory::getApplication()->getIdentity();

		if ((int) ($identity->id ?? 0) !== $userId || $userId <= 0)
		{
			return false;
		}

		return $identity->authorise('core.admin', 'com_wendfold')
			|| $identity->authorise('core.manage', 'com_wendfold');
	}

	private function isVolunteerManagerForEvent(?object $event, ?object $flags): bool
	{
		if (!$event || !$flags || empty($flags->profile_id))
		{
			return false;
		}

		$profileId = (int) $flags->profile_id;

		return in_array(
			$profileId,
			array_values(array_unique(array_filter([
				(int) ($event->volunteer_manager_id ?? 0),
				(int) ($event->volunteer_backup_id ?? 0),
			]))),
			true
		);
	}

	private function canAccessEventDetail(object $event, ?object $flags, int $userId): bool
	{
		$stored = get_object_vars($event);

		return AudienceHelper::canSeeEvent(
			(int) ($stored['audience_scope'] ?? 1),
			AudienceHelper::flagsFromProfile($flags),
			$this->isVolunteerManagerForEvent($event, $flags),
			$this->isVolunteerAdminOverride($userId)
		);
	}

	private function getProfileIdByUserId(int $userId): int
	{
		$flags = $this->getProfileFlagsByUserId($userId);

		return (int) ($flags->profile_id ?? 0);
	}

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
				])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . $eventId)
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
				->select([$db->quoteName('road_captain_id'), $db->quoteName('sweep_id')])
				->from($db->quoteName('#__wendfold_event_groups'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
				->where($db->quoteName('published') . ' = 1')
		)->loadObjectList() ?: [];

		foreach ($groupRows as $group)
		{
			$assigned[] = (int) ($group->road_captain_id ?? 0);
			$assigned[] = (int) ($group->sweep_id ?? 0);
		}

		$assigned = array_values(array_unique(array_filter(array_map('intval', $assigned))));

		return $assigned;
	}


	/**
	 * Return group-leadership driven group assignments keyed by profile ID.
	 *
	 * Group RC/Sweep assignments should win over stale manual group rows.
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
				])
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

		$db = $this->getDatabase();

		$hasAttendance = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_attendance'))
				->where($db->quoteName('event_id') . ' = :eventId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER)
		)->loadResult() > 0;

		if ($hasAttendance)
		{
			return;
		}

		$db->setQuery(
			$db->createQuery()
				->delete($db->quoteName('#__wendfold_event_group_members'))
				->where($db->quoteName('event_id') . ' = :eventId')
				->where($db->quoteName('profile_id') . ' = :profileId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
				->bind(':profileId', $profileId, ParameterType::INTEGER)
		)->execute();
	}

	public function getRoster(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$assignedProfileIds = $this->getAssignedProfileIds($eventId);
		$assignedSql = !empty($assignedProfileIds) ? implode(',', array_map('intval', $assignedProfileIds)) : '';
		$assignedWhere = $assignedSql !== '' ? ' OR p.id IN (' . $assignedSql . ')' : '';
		$assignedSelect = $assignedSql !== ''
			? 'CASE WHEN p.id IN (' . $assignedSql . ') THEN 1 ELSE 0 END AS is_assigned_role'
			: '0 AS is_assigned_role';
		$roleGroupIdSubquery = '(SELECT rg.id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupTitleSubquery = '(SELECT rg.title FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderSubquery = '(SELECT rg.group_order FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupStagingSubquery = '(SELECT rg.staging_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupOrderHintSubquery = '(SELECT rg.order_hint FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupRoadCaptainSubquery = '(SELECT rg.road_captain_id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';
		$roleGroupSweepSubquery = '(SELECT rg.sweep_id FROM ' . $db->quoteName('#__wendfold_event_groups', 'rg')
			. ' WHERE rg.event_id = ' . $eventId
			. ' AND rg.published = 1'
			. ' AND (rg.road_captain_id = p.id OR rg.sweep_id = p.id)'
			. ' ORDER BY rg.group_order ASC, rg.id ASC LIMIT 1)';

		$query = $db->createQuery()
			->select([
				'DISTINCT p.id AS profile_id',
				'u.name AS member_name',
				'p.member_number',
				'p.photo',
				'p.is_road_captain',
				'a.check_in_time',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o_cur') . ' WHERE o_cur.profile_id = p.id AND o_cur.is_current = 1 AND o_cur.published = 1) THEN 1 ELSE 0 END AS is_officer',
				'CASE WHEN a.id IS NULL THEN 0 ELSE 1 END AS checked_in',
				'CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS is_rsvped',
				$assignedSelect,
				'COALESCE(' . $roleGroupIdSubquery . ', gm.event_group_id) AS group_id',
				'COALESCE(' . $roleGroupTitleSubquery . ', g.title) AS group_title',
				'COALESCE(' . $roleGroupOrderSubquery . ', g.group_order) AS group_order',
				'COALESCE(' . $roleGroupStagingSubquery . ', g.staging_hint) AS group_staging_hint',
				'COALESCE(' . $roleGroupOrderHintSubquery . ', g.order_hint) AS group_order_hint',
				'COALESCE(' . $roleGroupRoadCaptainSubquery . ', g.road_captain_id) AS group_road_captain_id',
				'COALESCE(' . $roleGroupSweepSubquery . ', g.sweep_id) AS group_sweep_id',
			])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->leftJoin($db->quoteName('#__wendfold_rsvps', 'r') . ' ON r.profile_id = p.id AND r.event_id = ' . $eventId)
			->leftJoin($db->quoteName('#__wendfold_attendance', 'a') . ' ON a.profile_id = p.id AND a.event_id = ' . $eventId)
			->leftJoin($db->quoteName('#__wendfold_event_group_members', 'gm') . ' ON gm.profile_id = p.id AND gm.event_id = ' . $eventId)
			->leftJoin($db->quoteName('#__wendfold_event_groups', 'g') . ' ON g.id = gm.event_group_id AND g.event_id = ' . $eventId . ' AND g.published = 1') // phpcs:ignore Generic.Files.LineLength.TooLong
			->where($db->quoteName('p.published') . ' = 1')
			->where('(r.id IS NOT NULL OR a.id IS NOT NULL' . $assignedWhere . ')')
			->order('checked_in DESC, is_assigned_role DESC, group_order ASC, u.name ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	public function getRideGroups(int $eventId): array
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
				])
				->from($db->quoteName('#__wendfold_event_groups', 'g'))
				->leftJoin($db->quoteName('#__wendfold_profiles', 'rc_p') . ' ON rc_p.id = g.road_captain_id')
				->leftJoin($db->quoteName('#__users', 'rc_u') . ' ON rc_u.id = rc_p.user_id')
				->leftJoin($db->quoteName('#__wendfold_profiles', 'sw_p') . ' ON sw_p.id = g.sweep_id')
				->leftJoin($db->quoteName('#__users', 'sw_u') . ' ON sw_u.id = sw_p.user_id')
				->where($db->quoteName('g.event_id') . ' = ' . $eventId)
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
				])
				->from($db->quoteName('#__wendfold_event_group_members', 'gm'))
				->join('INNER', $db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = gm.profile_id')
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin($db->quoteName('#__wendfold_rsvps', 'r') . ' ON r.event_id = gm.event_id AND r.profile_id = gm.profile_id')
				->leftJoin($db->quoteName('#__wendfold_attendance', 'a') . ' ON a.event_id = gm.event_id AND a.profile_id = gm.profile_id')
				->where($db->quoteName('gm.event_id') . ' = ' . $eventId)
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
					])
					->from($db->quoteName('#__wendfold_profiles', 'p'))
					->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
					->leftJoin($db->quoteName('#__wendfold_attendance', 'a') . ' ON a.event_id = ' . $eventId . ' AND a.profile_id = p.id')
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

	public function getUserRideGroup(int $eventId, int $userId): ?object
	{
		$profileId = $this->getProfileIdByUserId($userId);

		if (!$eventId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$memberExists = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_event_group_members', 'gm')
			. ' WHERE gm.event_group_id = g.id'
			. ' AND gm.event_id = ' . $eventId
			. ' AND gm.profile_id = ' . $profileId . ')';
		$query = $db->createQuery()
			->select([
				'g.*',
				'rc_u.name AS road_captain_name',
				'sw_u.name AS sweep_name',
			])
			->from($db->quoteName('#__wendfold_event_groups', 'g'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'rc_p') . ' ON rc_p.id = g.road_captain_id')
			->leftJoin($db->quoteName('#__users', 'rc_u') . ' ON rc_u.id = rc_p.user_id')
			->leftJoin($db->quoteName('#__wendfold_profiles', 'sw_p') . ' ON sw_p.id = g.sweep_id')
			->leftJoin($db->quoteName('#__users', 'sw_u') . ' ON sw_u.id = sw_p.user_id')
			->where($db->quoteName('g.event_id') . ' = ' . $eventId)
			->where($db->quoteName('g.published') . ' = 1')
			->where('(' . $memberExists . ' OR g.road_captain_id = ' . $profileId . ' OR g.sweep_id = ' . $profileId . ')')
			->order('CASE WHEN g.road_captain_id = ' . $profileId . ' OR g.sweep_id = ' . $profileId . ' THEN 0 WHEN ' . $memberExists . ' THEN 1 ELSE 2 END ASC, g.group_order ASC, g.id ASC');

		$query->setLimit(1, 0);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	public function isRcOrSweep(int $eventId, int $userId): bool
	{
		$profileId = $this->getProfileIdByUserId($userId);

		if (!$eventId || !$profileId)
		{
			return false;
		}

		return in_array($profileId, $this->getAssignedProfileIds($eventId), true);
	}

	public function getRsvpCount(int $eventId): int
	{
		if (!$eventId)
		{
			return 0;
		}

		$db = $this->getDatabase();
		$rsvpCount = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(DISTINCT ' . $db->quoteName('profile_id') . ')')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
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
				->where($db->quoteName('event_id') . ' = ' . $eventId)
				->where($db->quoteName('profile_id') . ' IN (' . implode(',', $assignedProfileIds) . ')')
		)->loadResult();

		return $rsvpCount + max(0, count($assignedProfileIds) - $existingAssigned);
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

	public function isRsvped(int $eventId, int $userId): bool
	{
		if (!$eventId || !$userId)
		{
			return false;
		}

		$profileId = $this->getProfileIdByUserId($userId);
		$flags = $this->getProfileFlagsByUserId($userId);

		if (!$profileId)
		{
			return false;
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select([$db->quoteName('audience_scope')])
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = ' . (int) $eventId);
		$limitQuery->setLimit(1, 0);
		$event = $db->setQuery($limitQuery)->loadObject();

		if (!$event || !$this->canAccessAudience((int) ($event->audience_scope ?? 1), $flags)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return false;
		}

		if (in_array($profileId, $this->getAssignedProfileIds($eventId), true))
		{
			return true;
		}

		$count = (int) $db->setQuery(
			$db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_rsvps'))
				->where($db->quoteName('event_id') . ' = ' . (int) $eventId)
				->where($db->quoteName('profile_id') . ' = ' . (int) $profileId)
		)->loadResult();

		return $count > 0;
	}

	public function getParentEvent(int $eventId): ?object
	{
		if (!$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$parentId = (int) $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('parent_event_id'))
				->from($db->quoteName('#__wendfold_events'))
				->where($db->quoteName('id') . ' = ' . $eventId)
		)->loadResult();

		if ($parentId <= 0)
		{
			return null;
		}

		$parent = $db->setQuery(
			$db->createQuery()
				->select([
					'e.id',
					'e.title',
					'event_date',
					'start_date',
					'end_date',
					'e.published',
					'audience_scope',
					'volunteer_manager_id',
					'volunteer_backup_id',
					'event_type_id',
					'et.behavior AS event_behavior',
					'et.title AS event_type_title',
					'workflow_mode',
				])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . $parentId)
				->where($db->quoteName('e.published') . ' = 1')
		)->loadObject();

		if (!$parent || !$this->canAccessEventDetail($parent, $this->getCurrentUserFlags(), $this->currentUserId()))
		{
			return null;
		}

		return $parent;
	}

	public function getChildEvents(int $parentEventId): array
	{
		if (!$parentEventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$rows = $db->setQuery(
			$db->createQuery()
				->select([
					'e.id',
					'e.title',
					'e.description',
					'e.event_date',
					'e.ksu_time',
					'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title',
					'e.status',
					'e.audience_scope',
					'e.volunteer_manager_id',
					'e.volunteer_backup_id',
					'e.show_in_event_list',
					'loc.name AS location_name',
					'e.event_location_custom',
					'e.event_city',
					'e.event_state',
					'ksu_loc.name AS ksu_location_name',
					'e.ksu_location_custom',
					'e.ksu_city',
					'e.ksu_state',
				])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->leftJoin($db->quoteName('#__wendfold_locations', 'loc') . ' ON loc.id = e.event_location_id')
				->leftJoin($db->quoteName('#__wendfold_locations', 'ksu_loc') . ' ON ksu_loc.id = e.ksu_location_id')
				->where($db->quoteName('e.parent_event_id') . ' = ' . $parentEventId)
				->where($db->quoteName('e.published') . ' = 1')
				->order($db->quoteName('e.event_date') . ' ASC, ' . $db->quoteName('e.ksu_time') . ' ASC, ' . $db->quoteName('e.id') . ' ASC')
		)->loadObjectList() ?: [];

		$flags = $this->getCurrentUserFlags();
		$userId = $this->currentUserId();
		$rows = array_values(array_filter($rows, fn($row) => $this->canAccessEventDetail($row, $flags, $userId)));

		return $rows;
	}


	public function getTripParticipants(int $eventId): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$event = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('e.id'),
					$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'), // phpcs:ignore Generic.Files.LineLength.TooLong
					$db->quoteName('workflow_mode'),
					$db->quoteName('trip_lead_id'),
					$db->quoteName('trip_sweep_id'),
				])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . $eventId)
		)->loadObject();

		if (!$event || (!\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0)))) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return [];
		}

		$assignedIds = array_values(array_unique(array_filter([
			(int) ($event->trip_lead_id ?? 0),
			(int) ($event->trip_sweep_id ?? 0),
		])));

		$whereParts = [$db->quoteName('r.event_id') . ' IS NOT NULL'];

		if (!empty($assignedIds))
		{
			$whereParts[] = $db->quoteName('p.id') . ' IN (' . implode(',', array_map('intval', $assignedIds)) . ')';
		}

		$rows = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('p.id', 'profile_id'),
					$db->quoteName('u.name', 'member_name'),
					$db->quoteName('p.photo'),
					'COALESCE(' . $db->quoteName('tp.lodging_status') . ', 0) AS ' . $db->quoteName('lodging_status'),
					$db->quoteName('tp.booked_at'),
					'CASE WHEN ' . $db->quoteName('r.id') . ' IS NULL THEN 0 ELSE 1 END AS ' . $db->quoteName('is_rsvped'),
					'CASE WHEN ' . $db->quoteName('p.id') . ' = ' . (int) ($event->trip_lead_id ?? 0) . ' THEN 1 ELSE 0 END AS ' . $db->quoteName('is_trip_lead'),
					'CASE WHEN ' . $db->quoteName('p.id') . ' = ' . (int) ($event->trip_sweep_id ?? 0) . ' THEN 1 ELSE 0 END AS ' . $db->quoteName('is_trip_sweep'), // phpcs:ignore Generic.Files.LineLength.TooLong
				])
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->innerJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->leftJoin($db->quoteName('#__wendfold_rsvps', 'r')
					. ' ON r.profile_id = p.id'
				. ' AND r.event_id = ' . $eventId)
				->leftJoin($db->quoteName('#__wendfold_event_trip_participants', 'tp')
					. ' ON tp.profile_id = p.id'
				. ' AND tp.event_id = ' . $eventId)
				->where($db->quoteName('p.published') . ' = 1')
				->where('(' . implode(' OR ', $whereParts) . ')')
				->order('CASE COALESCE(' . $db->quoteName('tp.lodging_status') . ', 0)'
					. ' WHEN 3 THEN 0'
					. ' WHEN 4 THEN 1'
					. ' WHEN 5 THEN 2'
					. ' WHEN 2 THEN 3'
					. ' WHEN 1 THEN 4'
					. ' ELSE 5 END ASC, '
				. $db->quoteName('u.name') . ' ASC')
		)->loadObjectList() ?: [];

		return $rows;
	}

	public function getVolunteerRoles(int $eventId, int $userId = 0): array
	{
		if (!$eventId)
		{
			return [];
		}

		$db = $this->getDatabase();
		$profileFlags = $this->getProfileFlagsByUserId($userId);
		$profileId = (int) ($profileFlags->profile_id ?? 0);
		$event = $this->getVolunteerEventContext($eventId);
		$audienceScope = (int) ($event->audience_scope ?? 1); // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		$countExpr = 'SUM(CASE WHEN v.assignment_status IN (1,2) THEN 1 ELSE 0 END)';
		$userAssignedExpr = $profileId > 0
			? 'MAX(CASE WHEN v.profile_id = ' . $profileId . ' AND v.assignment_status IN (1,2,3) THEN 1 ELSE 0 END) AS user_is_assigned'
			: '0 AS user_is_assigned';
		$userStatusExpr = $profileId > 0
			? 'MAX(CASE WHEN v.profile_id = ' . $profileId . ' AND v.assignment_status IN (1,2,3) THEN v.assignment_status ELSE 0 END) AS user_assignment_status'
			: '0 AS user_assignment_status';

		$rows = $db->setQuery(
			$db->createQuery()
				->select([
					'r.*',
					'COALESCE(' . $countExpr . ', 0) AS filled_slots',
					$userAssignedExpr,
					$userStatusExpr,
				])
				->from($db->quoteName('#__wendfold_event_volunteer_roles', 'r'))
				->leftJoin($db->quoteName('#__wendfold_event_volunteers', 'v') . ' ON v.volunteer_role_id = r.id')
				->where($db->quoteName('r.event_id') . ' = ' . $eventId)
				->where($db->quoteName('r.published') . ' = 1')
				->group($db->quoteName('r.id'))
				->order($db->quoteName('r.sort_order') . ' ASC, ' . $db->quoteName('r.id') . ' ASC')
		)->loadObjectList() ?: [];

		foreach ($rows as $row)
		{
			foreach (['start_time', 'end_time'] as $field)
			{
				if (!empty($row->$field))
				{
					$ts = strtotime((string) $row->$field);
					$row->$field = $ts ? date('g:i A', $ts) : $row->$field;
				}
			}

			$eligibility = $this->getVolunteerSignupEligibility($audienceScope, $row, $profileFlags);
			$row->user_can_self_signup = !empty($eligibility['eligible']);
			$row->user_signup_reason = (string) ($eligibility['reason'] ?? '');
		}

		return $rows;
	}

	public function getVolunteerAssignment(int $eventId, int $userId): ?object
	{
		$profileId = $this->getProfileIdByUserId($userId);

		if (!$eventId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select([
				'v.*',
				'r.title AS role_title',
				'r.shift_label AS role_shift_label',
			])
			->from($db->quoteName('#__wendfold_event_volunteers', 'v'))
			->join('INNER', $db->quoteName('#__wendfold_event_volunteer_roles', 'r') . ' ON r.id = v.volunteer_role_id')
			->where($db->quoteName('v.event_id') . ' = ' . $eventId)
			->where($db->quoteName('v.profile_id') . ' = ' . $profileId)
			->where($db->quoteName('v.assignment_status') . ' IN (1,2,3)')
			->order('CASE v.assignment_status WHEN 2 THEN 0 WHEN 1 THEN 1 WHEN 3 THEN 2 ELSE 3 END ASC, v.modified DESC, v.id DESC');
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	public function canManageVolunteerAssignments(int $eventId, int $userId): bool
	{
		if (!$eventId || !$userId)
		{
			return false;
		}

		if ($this->isVolunteerAdminOverride($userId))
		{
			return true;
		}

		$profileFlags = $this->getProfileFlagsByUserId($userId);

		if (!$profileFlags || empty($profileFlags->profile_id))
		{
			return false;
		}

		if (!empty($profileFlags->is_volunteer_officer))
		{
			return true;
		}

		$event = $this->getVolunteerEventContext($eventId);

		if (!$event)
		{
			return false;
		}

		return $this->isVolunteerManagerForEvent($event, $profileFlags);
	}

	public function getVolunteerBoard(int $eventId, int $userId): array
	{
		if (!$this->canManageVolunteerAssignments($eventId, $userId))
		{
			return [];
		}

		$event = $this->getVolunteerEventContext($eventId);
		$roles = $this->getVolunteerRoles($eventId, $userId);

		if (!$event || empty($roles))
		{
			return [
				'roles' => [],
				'active_assignment_count' => 0,
				'waitlist_count' => 0,
			];
		}

		$db = $this->getDatabase();
		$roleMap = [];

		foreach ($roles as $role)
		{
			$role->active_assignments = [];
			$role->waitlist_assignments = [];
			$role->available_members = [];
			$roleMap[(int) $role->id] = $role;
		}

		$assignments = $db->setQuery(
			$db->createQuery()
				->select([
					'v.id',
					'v.event_id',
					'v.volunteer_role_id',
					'v.profile_id',
					'v.assignment_status',
					'v.notes',
					'u.name AS member_name',
					'p.photo',
					'p.is_road_captain',
					'p.is_road_crew',
					'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
						. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
				])
				->from($db->quoteName('#__wendfold_event_volunteers', 'v'))
				->join('INNER', $db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = v.profile_id')
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->where($db->quoteName('v.event_id') . ' = ' . $eventId)
				->where($db->quoteName('v.assignment_status') . ' IN (1,2,3)')
				->order('CASE v.assignment_status WHEN 2 THEN 0 WHEN 1 THEN 1 WHEN 3 THEN 2 ELSE 3 END ASC, u.name ASC')
		)->loadObjectList() ?: [];

		$assignedProfileIds = [];
		$activeAssignmentCount = 0;
		$waitlistCount = 0;

		foreach ($assignments as $assignment)
		{
			$assignment->profile_id = (int) ($assignment->profile_id ?? 0);
			$assignment->is_road_captain = !empty($assignment->is_road_captain);
			$assignment->is_road_crew = !empty($assignment->is_road_crew);
			$assignment->is_officer = !empty($assignment->is_officer);
			$assignedProfileIds[$assignment->profile_id] = $assignment->profile_id;

			$roleId = (int) ($assignment->volunteer_role_id ?? 0);

			if (!isset($roleMap[$roleId]))
			{
				continue;
			}

			if ((int) ($assignment->assignment_status ?? 0) === 3)
			{
				$roleMap[$roleId]->waitlist_assignments[] = $assignment;
				$waitlistCount++;
			}
			else
			{
				$roleMap[$roleId]->active_assignments[] = $assignment;
				$activeAssignmentCount++;
			}
		}

		$profiles = $db->setQuery(
			$db->createQuery()
				->select([
					'p.id AS value',
					'u.name AS text',
					'p.is_road_captain',
					'p.is_road_crew',
					'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
						. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
				])
				->from($db->quoteName('#__wendfold_profiles', 'p'))
				->join('INNER', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
				->where($db->quoteName('p.published') . ' = 1')
				->order('u.name ASC')
		)->loadObjectList() ?: [];

		foreach ($profiles as $profile)
		{
			$profile->value = (int) ($profile->value ?? 0);
			$profile->is_road_captain = !empty($profile->is_road_captain);
			$profile->is_road_crew = !empty($profile->is_road_crew);
			$profile->is_officer = !empty($profile->is_officer);
		}

		foreach ($roleMap as $roleId => $role)
		{
			foreach ($profiles as $profile)
			{
				if (isset($assignedProfileIds[$profile->value]))
				{
					continue;
				}

				if (!$this->isVolunteerProfileEligibleForEvent((int) ($event->audience_scope ?? 1), $profile)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
				{
					continue;
				}

				if (!$this->isVolunteerProfileEligibleForRole($role, $profile))
				{
					continue;
				}

				$role->available_members[] = $profile;
			}
		}

		return [
			'roles' => array_values($roleMap),
			'active_assignment_count' => $activeAssignmentCount,
			'waitlist_count' => $waitlistCount,
		];
	}

	private function getVolunteerEventContext(int $eventId): ?object
	{
		if (!$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select([
				'id',
				'published',
				'status',
				'event_date',
				'workflow_mode',
				'volunteer_signup_open',
				'audience_scope',
				'volunteer_manager_id',
				'volunteer_backup_id',
				'road_captain_id',
				'sweep_id',
				'trip_lead_id',
				'trip_sweep_id',
			])
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = ' . $eventId);
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	private function isVolunteerEventOpen(?object $event): bool
	{
		if (!$event)
		{
			return false;
		}

		return (int) ($event->published ?? 0) === 1
			&& in_array((int) ($event->status ?? 0), [1, 4, 5], true)
			&& !SiteTime::fromApplication()->rsvpClosed((string) ($event->event_date ?? ''));
	}

	/**
	 * Lock one volunteer role row for the rest of the open transaction.
	 *
	 * Must be called after EventRowLock::lock on the parent event.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 * @param   integer            $roleId   Role id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function lockVolunteerRole(DatabaseInterface $db, int $eventId, int $roleId): ?object
	{
		if ($eventId < 1 || $roleId < 1)
		{
			return null;
		}

		$query = $db->createQuery()
			->select('*')
			->from($db->quoteName('#__wendfold_event_volunteer_roles'))
			->where($db->quoteName('id') . ' = :roleId')
			->where($db->quoteName('event_id') . ' = :eventId')
			->where($db->quoteName('published') . ' = 1')
			->bind(':roleId', $roleId, ParameterType::INTEGER)
			->bind(':eventId', $eventId, ParameterType::INTEGER);

		$query->setQuery((string) $query . ' FOR UPDATE');

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Count active (signed-up or confirmed) volunteers for a role.
	 *
	 * @param   DatabaseInterface  $db              Database driver.
	 * @param   integer            $roleId          Role id.
	 * @param   integer            $excludeProfile  Profile id to exclude from the count.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function countActiveVolunteerSlots(DatabaseInterface $db, int $roleId, int $excludeProfile = 0): int
	{
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_event_volunteers'))
			->where($db->quoteName('volunteer_role_id') . ' = :roleId')
			->where($db->quoteName('assignment_status') . ' IN (1,2)')
			->bind(':roleId', $roleId, ParameterType::INTEGER);

		if ($excludeProfile > 0)
		{
			$query->where($db->quoteName('profile_id') . ' != :excludeProfile')
				->bind(':excludeProfile', $excludeProfile, ParameterType::INTEGER);
		}

		return (int) $db->setQuery($query)->loadResult();
	}

	private function getVolunteerProfileRow(int $profileId): ?object
	{
		if (!$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'p.id AS profile_id',
				'p.user_id',
				'p.is_road_captain',
				'p.is_road_crew',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'o')
					. ' WHERE o.profile_id = p.id AND o.is_current = 1 AND o.published = 1) THEN 1 ELSE 0 END AS is_officer',
				'CASE WHEN EXISTS (SELECT 1 FROM ' . $db->quoteName('#__wendfold_officers', 'vo')
					. ' INNER JOIN ' . $db->quoteName('#__wendfold_officer_roles', 'vor') . ' ON vor.id = vo.role_id'
					. ' WHERE vo.profile_id = p.id AND vo.is_current = 1 AND vo.published = 1'
					. ' AND vor.system_key = ' . $db->quote(OfficerRoleKey::VOLUNTEER_OFFICER) . ') THEN 1 ELSE 0 END AS is_volunteer_officer',
			])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->where($db->quoteName('p.id') . ' = ' . $profileId)
			->where($db->quoteName('p.published') . ' = 1');

		$query->setLimit(1, 0);
		$row = $db->setQuery($query)->loadObject();

		if (!$row)
		{
			return null;
		}

		$row->profile_id = (int) ($row->profile_id ?? 0);
		$row->is_road_captain = !empty($row->is_road_captain);
		$row->is_road_crew = !empty($row->is_road_crew);
		$row->is_officer = !empty($row->is_officer);
		$row->is_volunteer_officer = !empty($row->is_volunteer_officer);

		return $row;
	}

	private function getVolunteerActiveRow(int $eventId, int $profileId): ?object
	{
		if (!$eventId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select(['id', 'event_id', 'volunteer_role_id', 'profile_id', 'assignment_status'])
			->from($db->quoteName('#__wendfold_event_volunteers'))
			->where($db->quoteName('event_id') . ' = ' . $eventId)
			->where($db->quoteName('profile_id') . ' = ' . $profileId)
			->where($db->quoteName('assignment_status') . ' IN (1,2,3)')
			->order('CASE assignment_status WHEN 2 THEN 0 WHEN 1 THEN 1 WHEN 3 THEN 2 ELSE 3 END ASC, modified DESC, id DESC');
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	private function getVolunteerLatestRowByRoleProfile(int $roleId, int $profileId): ?object
	{
		if (!$roleId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select(['id', 'event_id', 'volunteer_role_id', 'profile_id', 'assignment_status', 'notes'])
			->from($db->quoteName('#__wendfold_event_volunteers'))
			->where($db->quoteName('volunteer_role_id') . ' = ' . $roleId)
			->where($db->quoteName('profile_id') . ' = ' . $profileId)
			->order('modified DESC, id DESC');
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	private function getVolunteerLatestRowByEventProfile(int $eventId, int $profileId): ?object
	{
		if (!$eventId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();

		$limitQuery = $db->createQuery()
			->select(['id', 'event_id', 'volunteer_role_id', 'profile_id', 'assignment_status', 'notes'])
			->from($db->quoteName('#__wendfold_event_volunteers'))
			->where($db->quoteName('event_id') . ' = ' . $eventId)
			->where($db->quoteName('profile_id') . ' = ' . $profileId)
			->order('modified DESC, id DESC');
		$limitQuery->setLimit(1, 0);

		return $db->setQuery($limitQuery)->loadObject() ?: null;
	}

	private function isVolunteerProfileEligibleForEvent(int $audienceScope, object $profileFlags): bool
	{
		return $this->canAccessAudience($audienceScope, $profileFlags);
	}

	private function isVolunteerProfileEligibleForRole(object $role, object $profileFlags): bool
	{
		if (!empty($role->requires_road_captain) && empty($profileFlags->is_road_captain))
		{
			return false;
		}

		if (!empty($role->requires_road_crew) && empty($profileFlags->is_road_crew))
		{
			return false;
		}

		return true;
	}

	private function getVolunteerSignupEligibility(int $audienceScope, object $role, ?object $profileFlags): array
	{
		if (!$profileFlags || empty($profileFlags->profile_id))
		{
			return ['eligible' => false, 'reason' => 'profile'];
		}

		if (!$this->isVolunteerProfileEligibleForEvent($audienceScope, $profileFlags))
		{
			return ['eligible' => false, 'reason' => 'audience'];
		}

		if (!empty($role->requires_road_captain) && empty($profileFlags->is_road_captain))
		{
			return ['eligible' => false, 'reason' => 'road_captain'];
		}

		if (!empty($role->requires_road_crew) && empty($profileFlags->is_road_crew))
		{
			return ['eligible' => false, 'reason' => 'road_crew'];
		}

		return ['eligible' => true, 'reason' => ''];
	}

	public function saveVolunteerAssignment(int $eventId, int $roleId, int $profileId, int $assignmentStatus, int $userId): string
	{
		if (!$eventId || !$roleId || !$profileId || !$userId)
		{
			return 'error';
		}

		if (!$this->canManageVolunteerAssignments($eventId, $userId))
		{
			return 'forbidden';
		}

		$event = $this->getVolunteerEventContext($eventId);

		if (!$this->isVolunteerEventOpen($event))
		{
			return 'closed';
		}

		$profileFlags = $this->getVolunteerProfileRow($profileId);

		if (!$profileFlags)
		{
			return 'notfound';
		}

		if (!$this->isVolunteerProfileEligibleForEvent((int) ($event->audience_scope ?? 1), $profileFlags)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return 'ineligible';
		}

		$assignmentStatus = in_array($assignmentStatus, [1, 2, 3], true) ? $assignmentStatus : 2;
		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			if (EventRowLock::lock($db, $eventId) === null)
			{
				$db->transactionRollback(true);

				return 'notfound';
			}

			$role = $this->lockVolunteerRole($db, $eventId, $roleId);

			if (!$role)
			{
				$db->transactionRollback(true);

				return 'notfound';
			}

			if (!$this->isVolunteerProfileEligibleForRole($role, $profileFlags))
			{
				$db->transactionRollback(true);

				return 'ineligible';
			}

			$activeRow = $this->getVolunteerActiveRow($eventId, $profileId);
			$targetRow = $this->getVolunteerLatestRowByRoleProfile($roleId, $profileId);
			$latestRow = $this->getVolunteerLatestRowByEventProfile($eventId, $profileId);

			if (\in_array($assignmentStatus, [1, 2], true))
			{
				$filled = $this->countActiveVolunteerSlots($db, $roleId, $profileId);
				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name.
				$activeRoleId = $activeRow ? (int) ($activeRow->volunteer_role_id ?? 0) : 0;
				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name.
				$activeStatus = $activeRow ? (int) ($activeRow->assignment_status ?? 0) : 0;
				$alreadyFillsSlot = $activeRow
					&& $activeRoleId === $roleId
					&& \in_array($activeStatus, [1, 2], true);
				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name.
				$slotCount = (int) ($role->slot_count ?? 1);

				if (!$alreadyFillsSlot && $filled >= $slotCount)
				{
					$db->transactionRollback(true);

					return 'full';
				}
			}

			if ($targetRow && $activeRow && (int) $targetRow->id !== (int) $activeRow->id)
			{
				$cancel = (object) [
					'id' => (int) $activeRow->id,
					'assignment_status' => 4,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $cancel, 'id', true);
				$activeRow = null;
			}

			if ($targetRow)
			{
				$record = (object) [
					'id' => (int) $targetRow->id,
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);
			}
			elseif ($activeRow)
			{
				$record = (object) [
					'id' => (int) $activeRow->id,
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);
			}
			elseif ($latestRow)
			{
				$record = (object) [
					'id' => (int) $latestRow->id,
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);
			}
			else
			{
				$record = (object) [
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'created' => $now,
					'created_by' => $userId,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->insertObject('#__wendfold_event_volunteers', $record);
			}

			$db->transactionCommit(true);

			return 'saved';
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);

			return 'error';
		}
	}

	public function removeVolunteerAssignment(int $eventId, int $assignmentId, int $userId): string
	{
		if (!$eventId || !$assignmentId || !$userId)
		{
			return 'error';
		}

		if (!$this->canManageVolunteerAssignments($eventId, $userId))
		{
			return 'forbidden';
		}

		$event = $this->getVolunteerEventContext($eventId);

		if (!$this->isVolunteerEventOpen($event))
		{
			return 'closed';
		}

		$db = $this->getDatabase();
		$limitQuery = $db->createQuery()
			->select(['id', 'event_id', 'assignment_status'])
			->from($db->quoteName('#__wendfold_event_volunteers'))
			->where($db->quoteName('id') . ' = ' . $assignmentId)
			->where($db->quoteName('event_id') . ' = ' . $eventId);
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)->loadObject();

		if (!$row || !in_array((int) ($row->assignment_status ?? 0), [1, 2, 3], true))
		{
			return 'notfound';
		}

		try
		{
			$record = (object) [
				'id' => (int) $row->id,
				'assignment_status' => 4,
				'modified' => Factory::getDate()->toSql(),
				'modified_by' => $userId,
			];
			$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);

			return 'removed';
		}
		catch (\Throwable $e)
		{
			return 'error';
		}
	}

	public function toggleVolunteer(int $eventId, int $roleId, int $userId)
	{
		if (!$eventId || !$roleId || !$userId)
		{
			return false;
		}

		$event = $this->getVolunteerEventContext($eventId);

		if (!$this->isVolunteerEventOpen($event) || empty($event->volunteer_signup_open))
		{
			return 'closed';
		}

		$flags = $this->getProfileFlagsByUserId($userId);
		$profileId = (int) ($flags->profile_id ?? 0);

		if ($profileId <= 0 || !$this->isVolunteerProfileEligibleForEvent((int) ($event->audience_scope ?? 1), $flags)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return false;
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$db->transactionStart(true);

		try
		{
			if (EventRowLock::lock($db, $eventId) === null)
			{
				$db->transactionRollback(true);

				return false;
			}

			$role = $this->lockVolunteerRole($db, $eventId, $roleId);

			if (!$role)
			{
				$db->transactionRollback(true);

				return false;
			}

			if (!$this->isVolunteerProfileEligibleForRole($role, $flags))
			{
				$db->transactionRollback(true);

				return 'ineligible';
			}

			$activeRow = $this->getVolunteerActiveRow($eventId, $profileId);

			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name.
			if ($activeRow && (int) ($activeRow->volunteer_role_id ?? 0) === $roleId)
			{
				$update = (object) [
					'id' => (int) $activeRow->id,
					'assignment_status' => 4,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $update, 'id', true);
				$db->transactionCommit(true);

				return 'removed';
			}

			if ($activeRow)
			{
				$db->transactionRollback(true);

				return 'alreadyassigned';
			}

			$filled = $this->countActiveVolunteerSlots($db, $roleId);
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column name.
			$assignmentStatus = $filled >= (int) ($role->slot_count ?? 1) ? 3 : 1;
			$targetRow = $this->getVolunteerLatestRowByRoleProfile($roleId, $profileId);
			$latestRow = $this->getVolunteerLatestRowByEventProfile($eventId, $profileId);

			if ($targetRow)
			{
				$record = (object) [
					'id' => (int) $targetRow->id,
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);
			}
			elseif ($latestRow)
			{
				$record = (object) [
					'id' => (int) $latestRow->id,
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_volunteers', $record, 'id', true);
			}
			else
			{
				$record = (object) [
					'event_id' => $eventId,
					'volunteer_role_id' => $roleId,
					'profile_id' => $profileId,
					'assignment_status' => $assignmentStatus,
					'created' => $now,
					'created_by' => $userId,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->insertObject('#__wendfold_event_volunteers', $record);
			}

			$db->transactionCommit(true);

			return $assignmentStatus === 3 ? 'waitlisted' : 'added';
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);

			return false;
		}
	}

	public function getTripParticipantStatus(int $eventId, int $userId): ?object
	{
		$profileId = $this->getProfileIdByUserId($userId);

		if (!$eventId || !$profileId)
		{
			return null;
		}

		$db = $this->getDatabase();

		return $db->setQuery(
			$db->createQuery()
				->select('*')
				->from($db->quoteName('#__wendfold_event_trip_participants'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
				->where($db->quoteName('profile_id') . ' = ' . $profileId)
		)->loadObject() ?: null;
	}

	public function saveTripStatus(int $eventId, int $userId, int $lodgingStatus, string $notes = ''): string
	{
		$profileId = $this->getProfileIdByUserId($userId);

		if (!$eventId || !$profileId)
		{
			return 'error';
		}

		$db = $this->getDatabase();
		$event = $db->setQuery(
			$db->createQuery()
				->select(['e.id', 'et.behavior AS event_behavior', 'workflow_mode'])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = ' . $eventId)
		)->loadObject();

		if (!$event || (!\Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0)))) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		{
			return 'invalid';
		}

		if (!$this->isRsvped($eventId, $userId))
		{
			return 'notrsvped';
		}

		$lodgingStatus = max(0, min(5, $lodgingStatus));
		$now = Factory::getDate()->toSql();
		$existing = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_event_trip_participants'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
				->where($db->quoteName('profile_id') . ' = ' . $profileId)
		)->loadResult();

		try
		{
			if ($existing)
			{
				$record = (object) [
					'id' => (int) $existing,
					'lodging_status' => $lodgingStatus,
					'lodging_notes' => trim($notes),
					'booked_at' => $lodgingStatus === 3 ? $now : null,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->updateObject('#__wendfold_event_trip_participants', $record, 'id', true);
			}
			else
			{
				$record = (object) [
					'event_id' => $eventId,
					'profile_id' => $profileId,
					'lodging_status' => $lodgingStatus,
					'lodging_notes' => trim($notes),
					'booked_at' => $lodgingStatus === 3 ? $now : null,
					'created' => $now,
					'created_by' => $userId,
					'modified' => $now,
					'modified_by' => $userId,
				];
				$db->insertObject('#__wendfold_event_trip_participants', $record);
			}

			return 'saved';
		}
		catch (\Throwable $e)
		{
			return 'error';
		}
	}

	/**
	 * Set RSVP to going or not going under the event row lock.
	 *
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $userId   Joomla user id.
	 * @param   boolean  $going    Desired state: true = going, false = not going.
	 *
	 * @return  string|false  added, removed, full, closed, disabled, or false on error.
	 *
	 * @since   1.0.0
	 */
	public function toggleRsvp(int $eventId, int $userId, bool $going)
	{
		if (!$eventId || !$userId)
		{
			return false;
		}

		$db = $this->getDatabase();
		$siteTime = SiteTime::fromApplication();

		$event = $db->setQuery(
			$db->createQuery()
				->select([
					$db->quoteName('e.id'),
					$db->quoteName('e.published'),
					$db->quoteName('status'),
					$db->quoteName('event_date'),
					$db->quoteName('capacity'),
					$db->quoteName('allow_rsvp'),
					$db->quoteName('e.event_type_id'), $db->quoteName('et.behavior', 'event_behavior'), $db->quoteName('et.title', 'event_type_title'), // phpcs:ignore Generic.Files.LineLength.TooLong
					$db->quoteName('workflow_mode'),
					$db->quoteName('audience_scope'),
				])
				->from($db->quoteName('#__wendfold_events', 'e'))
				->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
				->where($db->quoteName('e.id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
		)->loadObject();

		$canSeeAudience = !$event
			|| $this->canAccessAudience(
				(int) (get_object_vars($event)['audience_scope'] ?? 1),
				$this->getProfileFlagsByUserId($userId)
			);

		if (!$event
			|| (int) ($event->published ?? 0) !== 1
			|| !in_array((int) ($event->status ?? 0), [1, 4, 5], true)
			|| $siteTime->rsvpClosed((string) ($event->event_date ?? ''))
			|| !$canSeeAudience)
		{
			return 'closed';
		}

		if (empty($event->allow_rsvp))
		{
			return 'disabled';
		}

		$profileId = $this->getProfileIdByUserId($userId);

		if (!$profileId)
		{
			return false;
		}

		$assignedProfileIds = $this->getAssignedProfileIds($eventId);

		if (in_array($profileId, $assignedProfileIds, true))
		{
			return 'added';
		}

		// phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column names.
		$isOvernight = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($event->event_behavior ?? 0), (int) ($event->workflow_mode ?? 0));
		$capacity = (int) ($event->capacity ?? 0);
		$db->transactionStart(true);

		try
		{
			if (EventRowLock::lock($db, $eventId) === null)
			{
				$db->transactionRollback(true);

				return 'closed';
			}

			$existing = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_rsvps'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('profile_id') . ' = :profileId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER)
			)->loadResult();

			if (!$going)
			{
				if ($existing < 1)
				{
					$db->transactionRollback(true);

					return 'removed';
				}

				$db->setQuery(
					$db->createQuery()
						->delete($db->quoteName('#__wendfold_rsvps'))
						->where($db->quoteName('event_id') . ' = :eventId')
						->where($db->quoteName('profile_id') . ' = :profileId')
						->bind(':eventId', $eventId, ParameterType::INTEGER)
						->bind(':profileId', $profileId, ParameterType::INTEGER)
				)->execute();

				$this->clearOptionalGroupAssignment($eventId, $profileId);

				if ($isOvernight)
				{
					$db->setQuery(
						$db->createQuery()
							->delete($db->quoteName('#__wendfold_event_trip_participants'))
							->where($db->quoteName('event_id') . ' = :eventId')
							->where($db->quoteName('profile_id') . ' = :profileId')
							->bind(':eventId', $eventId, ParameterType::INTEGER)
							->bind(':profileId', $profileId, ParameterType::INTEGER)
					)->execute();
				}

				$db->transactionCommit(true);

				return 'removed';
			}

			if ($existing > 0)
			{
				$db->transactionRollback(true);

				return 'added';
			}

			if ($capacity > 0)
			{
				$rsvpCount = $this->getRsvpCount($eventId);

				if ($rsvpCount >= $capacity)
				{
					$db->transactionRollback(true);

					return 'full';
				}
			}

			$now = Factory::getDate()->toSql();
			$db->setQuery(
				$db->createQuery()
					->insert($db->quoteName('#__wendfold_rsvps'))
					->columns(
						[
							$db->quoteName('event_id'),
							$db->quoteName('profile_id'),
							$db->quoteName('created'),
							$db->quoteName('modified'),
						]
					)
					->values(':eventId, :profileId, :created, :modified')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER)
					->bind(':created', $now)
					->bind(':modified', $now)
			)->execute();

			$db->transactionCommit(true);

			return 'added';
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);

			if ($going && DuplicateKey::is($e))
			{
				return 'added';
			}

			return false;
		}
	}

}
