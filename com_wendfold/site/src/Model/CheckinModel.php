<?php
/**
 * Wendfold - Chapter Management System
 * Site QR Check-In Model
 *
 * Powers the self-service QR check-in page. Handles:
 *   - Loading an event by its QR token
 *   - Resolving a Joomla user to their chapter member profile
 *   - Checking whether a member is already checked in
 *   - Returning the current attendance list for display
 *   - Performing the check-in write operation (with waiver guard)
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
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\ParameterType;

/**
 * Check-In Model
 *
 * All read operations are keyed on the event's qr_token column so that
 * no event ID needs to appear in the public QR URL. The token acts as an
 * opaque, unguessable reference to the event.
 *
 * @since  1.0.0
 */
class CheckinModel extends BaseDatabaseModel
{
	/**
	 * Load a published event record by its QR token.
	 *
	 * Returns event fields needed by the check-in view, including
	 * a joined KSU location name. Returns null for invalid tokens.
	 *
	 * @param   string  $token  The event's unique QR code token.
	 *
	 * @return  object|null  Event object, or null if not found.
	 *
	 * @since   1.0.0
	 */
	public function getEventByToken(string $token): ?object
	{
		if (empty($token))
		{
			return null;
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'e.id', 'e.title', 'e.event_date', 'e.ksu_time', 'e.ksd_time',
				'e.event_type_id', 'et.behavior AS event_behavior', 'et.title AS event_type_title', 'e.is_riding_event', 'e.qr_token', 'e.published', 'e.status', // phpcs:ignore Generic.Files.LineLength.TooLong
				'e.audience_scope',
				'e.ksu_location_custom', 'e.ksu_city', 'e.ksu_state',
				'ksu_loc.name AS ksu_location_name',
				]
			)
			->from($db->quoteName('#__wendfold_events', 'e'))
			->join('LEFT', $db->quoteName('#__wendfold_event_types', 'et') . ' ON ' . $db->quoteName('et.id') . ' = ' . $db->quoteName('e.event_type_id')) // phpcs:ignore Generic.Files.LineLength.TooLong
			->leftJoin($db->quoteName('#__wendfold_locations', 'ksu_loc') . ' ON ksu_loc.id = e.ksu_location_id')
			->where($db->quoteName('e.qr_token') . ' = ' . $db->quote($token))
			->where($db->quoteName('e.published') . ' = 1');

		$event = $db->setQuery($query)->loadObject() ?: null;

		if (!$event)
		{
			return null;
		}

		$stored = get_object_vars($event);
		$event->showDetails = AudienceHelper::canAccess(
			(int) ($stored['audience_scope'] ?? 1),
			$this->getAudienceFlags()
		);

		if (empty($event->showDetails))
		{
			$hidden = [
				'title' => '',
				'ksu_time' => null,
				'ksd_time' => null,
				'ksu_location_custom' => '',
				'ksu_city' => '',
				'ksu_state' => '',
				'ksu_location_name' => '',
			];

			foreach ($hidden as $name => $value)
			{
				$event->$name = $value;
			}
		}

		return $event;
	}

	/**
	 * Published state for an event matched by QR token and id.
	 *
	 * Null when the token and id do not match a row. Callers use -2 for
	 * trash and treat every other non-1 state like an invalid token so
	 * unpublished and archived behaviour stays unchanged.
	 *
	 * @param   string   $token    QR token.
	 * @param   integer  $eventId  Event id from the form.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private function publishedForToken(string $token, int $eventId): ?int
	{
		if ($token === '' || !$eventId)
		{
			return null;
		}

		$db = $this->getDatabase();
		$published = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('published'))
				->from($db->quoteName('#__wendfold_events'))
				->where($db->quoteName('qr_token') . ' = :token')
				->where($db->quoteName('id') . ' = :eventId')
				->bind(':token', $token)
				->bind(':eventId', $eventId, \Joomla\Database\ParameterType::INTEGER)
		)->loadResult();

		if ($published === null || $published === false)
		{
			return null;
		}

		return (int) $published;
	}

	/**
	 * Audience flags for the current user.
	 *
	 * Null for a guest or an account with no published member profile.
	 *
	 * @return  array|null
	 *
	 * @since   1.0.0
	 */
	private function getAudienceFlags(): ?array
	{
		$user = Factory::getApplication()->getIdentity();
		$userId = (int) ($user->id ?? 0);

		if ($userId <= 0)
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
			->where($db->quoteName('p.user_id') . ' = ' . $userId)
			->where($db->quoteName('p.published') . ' = 1');
		$limitQuery->setLimit(1, 0);
		$row = $db->setQuery($limitQuery)
			->loadObject();

		return AudienceHelper::flagsFromProfile($row ?: null);
	}

	/**
	 * Resolve a chapter member profile for a given Joomla user ID.
	 *
	 * Returns lightweight profile data needed by the check-in view
	 * (member number, waiver status, dues status, photo).
	 *
	 * @param   int  $userId  Joomla user ID.
	 *
	 * @return  object|null  Profile object, or null if no profile exists.
	 *
	 * @since   1.0.0
	 */
	public function getProfileByUserId(int $userId): ?object
	{
		$db    = $this->getDatabase();
		$year  = SiteTime::fromApplication()->year();
		$query = $db->createQuery()
			->select([
				'p.id',
				'p.member_number',
				'mt.title AS membership_type_title',
				'p.photo',
				'p.waiver_signed',
				'p.dues_paid',
				'p.renewal_date',
				'CASE WHEN dp.id IS NOT NULL THEN 1 ELSE 0 END AS paid_this_year',
				]
			)
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->leftJoin(
				$db->quoteName('#__wendfold_membership_types', 'mt')
				. ' ON mt.id = p.membership_type_id'
			)
			->leftJoin(
				$db->quoteName('#__wendfold_dues_payments', 'dp')
				. ' ON dp.profile_id = p.id AND dp.year = ' . $year
			)
			->where($db->quoteName('p.user_id') . ' = ' . $userId)
			->where($db->quoteName('p.published') . ' = 1');

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Check whether a member (by profile ID) is already checked in to an event.
	 *
	 * @param   int  $eventId    Event ID.
	 * @param   int  $profileId  member profile ID.
	 *
	 * @return  boolean  True if an attendance record already exists.
	 *
	 * @since   1.0.0
	 */
	public function isCheckedIn(int $eventId, int $profileId): bool
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from($db->quoteName('#__wendfold_attendance'))
			->where($db->quoteName('event_id') . ' = ' . $eventId)
			->where($db->quoteName('profile_id') . ' = ' . $profileId);

		return (int) $db->setQuery($query)->loadResult() > 0;
	}

	/**
	 * Return the current attendance list for an event.
	 *
	 * Used to display the "Who's checked in" panel on the check-in page.
	 * Results are ordered by check_in_time ascending.
	 *
	 * @param   int  $eventId  Event ID.
	 *
	 * @return  object[]  Array of attendance rows with member_name, member_number, photo.
	 *
	 * @since   1.0.0
	 */
	public function getAttendance(int $eventId): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select(['a.id', 'a.check_in_time', 'a.check_in_method',
					  'u.name AS member_name', 'p.member_number', 'p.photo']
			)
			->from($db->quoteName('#__wendfold_attendance', 'a'))
			->leftJoin($db->quoteName('#__wendfold_profiles', 'p') . ' ON p.id = a.profile_id')
			->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->where($db->quoteName('a.event_id') . ' = ' . $eventId)
			->order('a.check_in_time ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Perform a member self check-in.
	 *
	 * Validates that the QR token matches the event, resolves the user's
	 * profile, enforces the waiver requirement, checks for duplicates,
	 * then inserts an attendance record.
	 *
	 * @param   int     $eventId  Event ID from the form (must match the token's event).
	 * @param   int     $userId   Joomla user ID of the member checking in.
	 * @param   string  $token    QR code token from the URL.
	 * @param   int     $method   Check-in method code (1 = self check-in via QR).
	 *
	 * @return  true|string  True on success, 'already' if already checked in,
	 *                       or an error message string on failure.
	 *
	 * @since   1.0.0
	 */
	public function checkIn(int $eventId, int $userId, string $token, int $method = 1)
	{
		$db = $this->getDatabase();

		// Token + event id must match. Trashed is refused before published=1 loads.
		$published = $this->publishedForToken($token, $eventId);

		if ($published === null)
		{
			return 'invalid_token';
		}

		if ($published === -2)
		{
			return 'event_trashed';
		}

		// The QR token authorises check-in. Audience scope only hides event details.
		$event = $this->getEventByToken($token);

		if (!$event || $event->id !== $eventId)
		{
			return 'invalid_token';
		}

		// Event must be open (Scheduled) and happen today
		if (!in_array((int) ($event->status ?? 0), [1, 4], true))
		{
			return 'event_closed';
		}

		$today = SiteTime::fromApplication()->today();

		if (!empty($event->event_date) && (string) $event->event_date !== $today)
		{
			return 'event_not_today';
		}

		// Resolve the member profile
		$profile = $this->getProfileByUserId($userId);

		if (!$profile)
		{
			return 'no_profile';
		}

		// Waiver is required before any check-in
		if (!$profile->waiver_signed)
		{
			return 'waiver_required';
		}

		// Insert the attendance record
		$record                  = new \stdClass;
		$record->event_id        = $eventId;
		$record->profile_id      = $profile->id;
		$record->check_in_time   = Factory::getDate()->toSql();
		$record->check_in_method = $method;
		$record->checked_in_by   = $userId;
		$record->notes           = '';

		$metrics = MetricService::fromDatabase($db, $userId);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'invalid_token';
			}

			if ((int) $locked->published === -2)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'event_trashed';
			}

			if (!\in_array((int) $locked->status, [1, 4], true))
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();

				return 'event_closed';
			}

			$profileId = (int) $profile->id;
			$already = (int) $db->setQuery(
				$db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('profile_id') . ' = :profileId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER)
			)->loadResult();

			if ($already > 0)
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

			Log::add('Check-in insert failed: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			return 'db_error';
		}
	}
}
