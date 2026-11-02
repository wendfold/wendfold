<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Attendance List Model
 *
 * Powers the attendance management view for a single event.
 * Provides the check-in roster, event detail, and RSVP count
 * needed to build the officer attendance management page.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Wendfold\Component\Wendfold\Administrator\Helper\AttendanceMemberLabel;

/**
 * Attendance Model
 *
 * Scoped to a single event (event_id read from input).
 * Returns attendance rows joined with profile and user data.
 *
 * @since  1.0.0
 */
class AttendanceModel extends ListModel
{
	/**
	 * Build the database query for the attendance list.
	 *
	 * Returns all check-in records for the current event, joined
	 * with profile and user account tables for display purposes.
	 * Results are ordered by check_in_time ascending.
	 *
	 * @return  \Joomla\Database\DatabaseQuery
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db      = $this->getDatabase();
		$eventId = (int) Factory::getApplication()->getInput()->getInt('event_id', 0);

		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('a.id'),
					$db->quoteName('a.check_in_time'),
					$db->quoteName('a.check_in_method'),
					$db->quoteName('a.notes'),
					$db->quoteName('u.name', 'member_name'),
					$db->quoteName('p.member_number'),
					$db->quoteName('mt.title', 'membership_type_title'),
					$db->quoteName('p.photo'),
					$db->quoteName('p.waiver_signed'),
					$db->quoteName('p.dues_paid'),
				]
			)
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->leftJoin(
				$db->quoteName('#__wendfold_profiles', 'p')
					. ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.profile_id')
			)
			->leftJoin(
				$db->quoteName('#__users', 'u')
					. ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->leftJoin(
				$db->quoteName('#__wendfold_membership_types', 'mt')
					. ' ON ' . $db->quoteName('mt.id') . ' = ' . $db->quoteName('p.membership_type_id')
			)
			->where($db->quoteName('a.event_id') . ' = ' . $eventId)
			->order($db->quoteName('a.check_in_time') . ' ASC');

		return $query;
	}

	/**
	 * Load the event record for the current event_id input parameter.
	 *
	 * Returns lightweight event data (id, title, event_date, ksu_time, qr_token)
	 * used by the attendance view header and QR code display.
	 *
	 * @return  object|null  Event object, or null if not found or event_id is 0.
	 *
	 * @since   1.0.0
	 */
	public function getEvent(): ?object
	{
		$eventId = (int) Factory::getApplication()->getInput()->getInt('event_id', 0);

		if (!$eventId)
		{
			return null;
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(['id', 'title', 'event_date', 'ksu_time', 'qr_token'])
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = ' . $eventId);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Count the number of RSVP records for the current event.
	 *
	 * Used by the attendance view to show how many members
	 * indicated they were coming vs. how many actually checked in.
	 *
	 * @return  integer  Total RSVP count, or 0 if event_id is not set.
	 *
	 * @since   1.0.0
	 */
	public function getRsvpCount(): int
	{
		$eventId = (int) Factory::getApplication()->getInput()->getInt('event_id', 0);

		if (!$eventId)
		{
			return 0;
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_rsvps'))
			->where($db->quoteName('event_id') . ' = ' . $eventId);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Published profiles for the attendance "Add Member" modal.
	 *
	 * Profiles without a linked Joomla user still appear. The display
	 * name falls back through preferred name, member number, then
	 * Member #&lt;id&gt;, and is always a string so htmlspecialchars()
	 * never receives null (deprecated in PHP 8.1+).
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getAvailableMembers(): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('p.id'),
					$db->quoteName('u.name'),
					$db->quoteName('p.preferred_name'),
					$db->quoteName('p.member_number'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id')
			)
			->where($db->quoteName('p.published') . ' = 1')
			->order($db->quoteName('u.name') . ' ASC');

		$members = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($members as $member)
		{
			// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column names.
			$member->name = AttendanceMemberLabel::resolve(
				isset($member->name) ? (string) $member->name : null,
				isset($member->preferred_name) ? (string) $member->preferred_name : null,
				isset($member->member_number) ? (string) $member->member_number : null,
				(int) $member->id
			);
			// phpcs:enable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		}

		return $members;
	}
}
