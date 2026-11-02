<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  Privacy.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Plugin\Privacy\Wendfold\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;

/**
 * MySQL-backed store for Wendfold privacy export and anonymization.
 *
 * @since  1.0.0
 */
final class DatabasePrivacyMemberStore implements PrivacyMemberStoreInterface
{
	/**
	 * Database driver.
	 *
	 * @var    DatabaseInterface
	 * @since  1.0.0
	 */
	private DatabaseInterface $db;

	/**
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @since   1.0.0
	 */
	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	/**
	 * Load the Wendfold profile linked to a Joomla user id.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  array<string, mixed>|null
	 *
	 * @since   1.0.0
	 */
	public function findProfileByUserId(int $userId): ?array
	{
		$query = $this->db->createQuery()
			->select('*')
			->from($this->db->quoteName('#__wendfold_profiles'))
			->where($this->db->quoteName('user_id') . ' = :userId')
			->bind(':userId', $userId, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadAssoc();

		return $row === null ? null : $row;
	}

	/**
	 * Whether the profile currently holds an officer role.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function isCurrentOfficer(int $profileId): bool
	{
		$query = $this->db->createQuery()
			->select('COUNT(*)')
			->from($this->db->quoteName('#__wendfold_officers'))
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->where($this->db->quoteName('is_current') . ' = 1')
			->where($this->db->quoteName('published') . ' = 1')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		return (int) $this->db->setQuery($query)->loadResult() > 0;
	}

	/**
	 * Read the renewal reminder opt-out flag for a Joomla user.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function isReminderOptedOut(int $userId): bool
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('params'))
			->from($this->db->quoteName('#__users'))
			->where($this->db->quoteName('id') . ' = :userId')
			->bind(':userId', $userId, ParameterType::INTEGER);

		$params = $this->db->setQuery($query)->loadResult();

		return RenewalReminderOptOut::isOptedOutFromParams($params);
	}

	/**
	 * Export personal rows keyed by domain name.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 * @param   integer  $userId     Joomla user id.
	 * @param   string   $userName   Joomla user display name (not used for attribution).
	 *
	 * @return  array<string, list<array<string, mixed>>>
	 *
	 * @since   1.0.0
	 */
	public function loadExportDomains(int $profileId, int $userId, string $userName): array
	{
		$domains = [];

		$domains['wendfold_profiles'] = $this->loadByProfileId('#__wendfold_profiles', 'id', $profileId);
		$domains['wendfold_motorcycles'] = $this->loadByProfileId('#__wendfold_motorcycles', 'profile_id', $profileId);
		$domains['wendfold_officers'] = $this->loadByProfileId('#__wendfold_officers', 'profile_id', $profileId);
		$domains['wendfold_attendance'] = $this->loadByProfileId('#__wendfold_attendance', 'profile_id', $profileId);
		$domains['wendfold_rsvps'] = $this->loadByProfileId('#__wendfold_rsvps', 'profile_id', $profileId);
		$domains['wendfold_event_group_members'] = $this->loadByProfileId('#__wendfold_event_group_members', 'profile_id', $profileId);
		$domains['wendfold_event_volunteers'] = $this->loadByProfileId('#__wendfold_event_volunteers', 'profile_id', $profileId);
		$domains['wendfold_event_trip_participants'] = $this->loadByProfileId('#__wendfold_event_trip_participants', 'profile_id', $profileId);
		$domains['wendfold_activities'] = $this->loadByProfileId('#__wendfold_activities', 'profile_id', $profileId);
		$domains['wendfold_metric_entries'] = $this->loadByProfileId('#__wendfold_metric_entries', 'profile_id', $profileId);
		$domains['wendfold_member_awards'] = $this->loadByProfileId('#__wendfold_member_awards', 'profile_id', $profileId);
		$domains['wendfold_dues_payments'] = $this->loadByProfileId('#__wendfold_dues_payments', 'profile_id', $profileId);
		$domains['wendfold_reminder_log'] = $this->loadByProfileId('#__wendfold_reminder_log', 'profile_id', $profileId);
		$domains['wendfold_event_roles'] = $this->loadEventRoleRows($profileId);
		$domains['wendfold_event_group_roles'] = $this->loadEventGroupRoleRows($profileId);
		$domains['wendfold_sync_log'] = $this->loadSyncLogRows($userId, $profileId);

		return $domains;
	}

	/**
	 * Run the callable inside a database transaction.
	 *
	 * @param   callable  $work  Work to run.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transaction(callable $work): void
	{
		$this->db->transactionStart(true);

		try
		{
			$work();
			$this->db->transactionCommit(true);
		}
		catch (\Throwable $exception)
		{
			$this->db->transactionRollback(true);

			throw $exception;
		}
	}

	/**
	 * Anonymize the profile row and clear personal fields.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeProfile(int $profileId): void
	{
		$empty = '';
		$null  = null;
		$zero  = 0;
		$left  = 3;
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_profiles'))
			->set([
				$this->db->quoteName('user_id') . ' = :userId',
				$this->db->quoteName('member_number') . ' = :memberNumber',
				$this->db->quoteName('preferred_name') . ' = :preferredName',
				$this->db->quoteName('emergency_name') . ' = :emergencyName',
				$this->db->quoteName('emergency_phone') . ' = :emergencyPhone',
				$this->db->quoteName('emergency_relation') . ' = :emergencyRelation',
				$this->db->quoteName('shirt_size') . ' = :shirtSize',
				$this->db->quoteName('photo') . ' = :photo',
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('chapter_on_file') . ' = :chapterOnFile',
				$this->db->quoteName('waiver_version') . ' = :waiverVersion',
				$this->db->quoteName('sponsor_id') . ' = :sponsorId',
				$this->db->quoteName('is_road_captain') . ' = :isRoadCaptain',
				$this->db->quoteName('is_road_crew') . ' = :isRoadCrew',
				$this->db->quoteName('published') . ' = :published',
				$this->db->quoteName('member_status') . ' = :memberStatus',
				$this->db->quoteName('reminder_60_sent') . ' = :reminder60',
				$this->db->quoteName('reminder_30_sent') . ' = :reminder30',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('id') . ' = :profileId')
			->bind(':userId', $null, ParameterType::NULL)
			->bind(':memberNumber', $null, ParameterType::NULL)
			->bind(':preferredName', $empty)
			->bind(':emergencyName', $empty)
			->bind(':emergencyPhone', $empty)
			->bind(':emergencyRelation', $empty)
			->bind(':shirtSize', $empty)
			->bind(':photo', $empty)
			->bind(':notes', $null, ParameterType::NULL)
			->bind(':chapterOnFile', $empty)
			->bind(':waiverVersion', $empty)
			->bind(':sponsorId', $null, ParameterType::NULL)
			->bind(':isRoadCaptain', $zero, ParameterType::INTEGER)
			->bind(':isRoadCrew', $zero, ParameterType::INTEGER)
			->bind(':published', $zero, ParameterType::INTEGER)
			->bind(':memberStatus', $left, ParameterType::INTEGER)
			->bind(':reminder60', $null, ParameterType::NULL)
			->bind(':reminder30', $null, ParameterType::NULL)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal fields on motorcycle rows for the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMotorcycles(int $profileId): void
	{
		$empty = '';
		$null  = null;
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_motorcycles'))
			->set([
				$this->db->quoteName('make') . ' = :make',
				$this->db->quoteName('model') . ' = :model',
				$this->db->quoteName('color') . ' = :color',
				$this->db->quoteName('nickname') . ' = :nickname',
				$this->db->quoteName('vin') . ' = :vin',
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('model_year') . ' = :modelYear',
				$this->db->quoteName('year') . ' = :year',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':make', $empty)
			->bind(':model', $empty)
			->bind(':color', $empty)
			->bind(':nickname', $empty)
			->bind(':vin', $empty)
			->bind(':notes', $null, ParameterType::NULL)
			->bind(':modelYear', $null, ParameterType::NULL)
			->bind(':year', $null, ParameterType::NULL)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal fields on officer history rows and mark them not current.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeOfficers(int $profileId): void
	{
		$empty = '';
		$zero  = 0;
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_officers'))
			->set([
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('is_current') . ' = :isCurrent',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':isCurrent', $zero, ParameterType::INTEGER)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear notes on attendance rows for the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 * @param   integer  $userId     Joomla user id for actor-column clearing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeAttendance(int $profileId, int $userId): void
	{
		$empty = '';
		$zero  = 0;

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_attendance'))
			->set($this->db->quoteName('notes') . ' = :notes')
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_attendance'))
			->set($this->db->quoteName('checked_in_by') . ' = :zero')
			->where($this->db->quoteName('checked_in_by') . ' = :userId')
			->bind(':zero', $zero, ParameterType::INTEGER)
			->bind(':userId', $userId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Delete RSVP rows for the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteRsvps(int $profileId): void
	{
		$query = $this->db->createQuery()
			->delete($this->db->quoteName('#__wendfold_rsvps'))
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal notes on group membership rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 * @param   integer  $userId     Joomla user id for actor-column clearing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeEventGroupMembers(int $profileId, int $userId): void
	{
		$empty = '';
		$zero  = 0;

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_event_group_members'))
			->set($this->db->quoteName('notes') . ' = :notes')
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_event_group_members'))
			->set($this->db->quoteName('assigned_by') . ' = :zero')
			->where($this->db->quoteName('assigned_by') . ' = :userId')
			->bind(':zero', $zero, ParameterType::INTEGER)
			->bind(':userId', $userId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal notes on volunteer assignment rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeEventVolunteers(int $profileId): void
	{
		$empty = '';
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_event_volunteers'))
			->set([
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear lodging and travel notes on trip participant rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeTripParticipants(int $profileId): void
	{
		$empty = '';
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_event_trip_participants'))
			->set([
				$this->db->quoteName('lodging_notes') . ' = :lodgingNotes',
				$this->db->quoteName('arrival_notes') . ' = :arrivalNotes',
				$this->db->quoteName('departure_notes') . ' = :departureNotes',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':lodgingNotes', $empty)
			->bind(':arrivalNotes', $empty)
			->bind(':departureNotes', $empty)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal text on activity rows and drop motorcycle links.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeActivities(int $profileId): void
	{
		$empty = '';
		$null  = null;
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_activities'))
			->set([
				$this->db->quoteName('title') . ' = :title',
				$this->db->quoteName('description') . ' = :description',
				$this->db->quoteName('start_location') . ' = :startLocation',
				$this->db->quoteName('end_location') . ' = :endLocation',
				$this->db->quoteName('motorcycle_id') . ' = :motorcycleId',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':title', $empty)
			->bind(':description', $empty)
			->bind(':startLocation', $empty)
			->bind(':endLocation', $empty)
			->bind(':motorcycleId', $null, ParameterType::NULL)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal notes on metric entry rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMetricEntries(int $profileId): void
	{
		$empty = '';
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_metric_entries'))
			->set([
				$this->db->quoteName('note') . ' = :note',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':note', $empty)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal notes on member award rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMemberAwards(int $profileId): void
	{
		$empty = '';
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_member_awards'))
			->set([
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Clear personal fields on dues payment rows while keeping amounts.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeDuesPayments(int $profileId): void
	{
		$empty = '';
		$now   = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_dues_payments'))
			->set([
				$this->db->quoteName('notes') . ' = :notes',
				$this->db->quoteName('receipt_number') . ' = :receiptNumber',
				$this->db->quoteName('payment_method') . ' = :paymentMethod',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('profile_id') . ' = :profileId')
			->bind(':notes', $empty)
			->bind(':receiptNumber', $empty)
			->bind(':paymentMethod', $empty)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Null event role columns that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearEventRoleAssignments(int $profileId): void
	{
		$null = null;
		$now  = gmdate('Y-m-d H:i:s');
		$cols = [
			'road_captain_id',
			'sweep_id',
			'volunteer_manager_id',
			'volunteer_backup_id',
			'trip_lead_id',
			'trip_sweep_id',
		];

		foreach ($cols as $column)
		{
			$query = $this->db->createQuery()
				->update($this->db->quoteName('#__wendfold_events'))
				->set([
					$this->db->quoteName($column) . ' = :roleId',
					$this->db->quoteName('modified') . ' = :modified',
					]
				)
				->where($this->db->quoteName($column) . ' = :profileId')
				->bind(':roleId', $null, ParameterType::NULL)
				->bind(':modified', $now)
				->bind(':profileId', $profileId, ParameterType::INTEGER);

			$this->db->setQuery($query)->execute();
		}
	}

	/**
	 * Null event-group role columns that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearEventGroupRoleAssignments(int $profileId): void
	{
		$null = null;
		$now  = gmdate('Y-m-d H:i:s');

		foreach (['road_captain_id', 'sweep_id'] as $column)
		{
			$query = $this->db->createQuery()
				->update($this->db->quoteName('#__wendfold_event_groups'))
				->set([
					$this->db->quoteName($column) . ' = :roleId',
					$this->db->quoteName('modified') . ' = :modified',
					]
				)
				->where($this->db->quoteName($column) . ' = :profileId')
				->bind(':roleId', $null, ParameterType::NULL)
				->bind(':modified', $now)
				->bind(':profileId', $profileId, ParameterType::INTEGER);

			$this->db->setQuery($query)->execute();
		}
	}

	/**
	 * Clear sponsor links that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearSponsorLinks(int $profileId): void
	{
		$null = null;
		$now  = gmdate('Y-m-d H:i:s');

		$query = $this->db->createQuery()
			->update($this->db->quoteName('#__wendfold_profiles'))
			->set([
				$this->db->quoteName('sponsor_id') . ' = :sponsorId',
				$this->db->quoteName('modified') . ' = :modified',
				]
			)
			->where($this->db->quoteName('sponsor_id') . ' = :profileId')
			->bind(':sponsorId', $null, ParameterType::NULL)
			->bind(':modified', $now)
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Redact identifiers inside sync-log rows attributed to the member.
	 *
	 * Attribution uses performed_by or an exact whole-token match of the unique
	 * member_number. Name tokens never attribute a row; they are only rewritten
	 * inside rows already attributed to this member.
	 *
	 * @param   integer       $userId           Joomla user id.
	 * @param   string        $memberNumber     Unique member id, or empty when unknown.
	 * @param   list<string>  $redactionTokens  Tokens to replace inside attributed rows.
	 * @param   string        $replacement      Replacement text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function redactSyncLog(
		int $userId,
		string $memberNumber,
		array $redactionTokens,
		string $replacement
	): void
	{
		$query = $this->db->createQuery()
			->select([
				$this->db->quoteName('id'),
				$this->db->quoteName('log_detail'),
				$this->db->quoteName('performed_by'),
				]
			)
			->from($this->db->quoteName('#__wendfold_sync_log'));

		$rows = $this->db->setQuery($query)->loadAssocList() ?: [];

		foreach ($rows as $row)
		{
			$detail = (string) ($row['log_detail'] ?? '');
			$performedBy = (int) ($row['performed_by'] ?? 0);

			if (!SyncLogText::isAttributedToMember($performedBy, $userId, $detail, $memberNumber))
			{
				continue;
			}

			$updated = SyncLogText::replaceExactTokens($detail, $redactionTokens, $replacement);
			$newPerformedBy = $performedBy === $userId ? 0 : $performedBy;

			if ($updated === $detail && $newPerformedBy === $performedBy)
			{
				continue;
			}

			$id = (int) $row['id'];
			$update = $this->db->createQuery()
				->update($this->db->quoteName('#__wendfold_sync_log'))
				->set([
					$this->db->quoteName('log_detail') . ' = :detail',
					$this->db->quoteName('performed_by') . ' = :performedBy',
					]
				)
				->where($this->db->quoteName('id') . ' = :id')
				->bind(':detail', $updated)
				->bind(':performedBy', $newPerformedBy, ParameterType::INTEGER)
				->bind(':id', $id, ParameterType::INTEGER);

			$this->db->setQuery($update)->execute();
		}
	}

	/**
	 * Clear the Wendfold renewal reminder opt-out on the Joomla user.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearReminderOptOut(int $userId): void
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('params'))
			->from($this->db->quoteName('#__users'))
			->where($this->db->quoteName('id') . ' = :userId')
			->bind(':userId', $userId, ParameterType::INTEGER);

		$raw = $this->db->setQuery($query)->loadResult();

		if ($raw === null)
		{
			return;
		}

		$registry = new Registry($raw);

		if ((string) $registry->get(RenewalReminderOptOut::PARAM_KEY, '') === '')
		{
			return;
		}

		$registry->set(RenewalReminderOptOut::PARAM_KEY, 0);
		$params = $registry->toString('JSON');

		$update = $this->db->createQuery()
			->update($this->db->quoteName('#__users'))
			->set($this->db->quoteName('params') . ' = :params')
			->where($this->db->quoteName('id') . ' = :userId')
			->bind(':params', $params)
			->bind(':userId', $userId, ParameterType::INTEGER);

		$this->db->setQuery($update)->execute();
	}

	/**
	 * Return the photo path stored on the profile before anonymization.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getProfilePhotoPath(int $profileId): string
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('photo'))
			->from($this->db->quoteName('#__wendfold_profiles'))
			->where($this->db->quoteName('id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		return (string) $this->db->setQuery($query)->loadResult();
	}

	/**
	 * Load all rows for a profile-linked table.
	 *
	 * @param   string   $table      Table name with #__ prefix.
	 * @param   string   $column     Profile id column.
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function loadByProfileId(string $table, string $column, int $profileId): array
	{
		$query = $this->db->createQuery()
			->select('*')
			->from($this->db->quoteName($table))
			->where($this->db->quoteName($column) . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		return $this->db->setQuery($query)->loadAssocList() ?: [];
	}

	/**
	 * Load event rows where the profile holds a role.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function loadEventRoleRows(int $profileId): array
	{
		$query = $this->db->createQuery()
			->select([
				$this->db->quoteName('id'),
				$this->db->quoteName('title'),
				$this->db->quoteName('event_date'),
				$this->db->quoteName('road_captain_id'),
				$this->db->quoteName('sweep_id'),
				$this->db->quoteName('volunteer_manager_id'),
				$this->db->quoteName('volunteer_backup_id'),
				$this->db->quoteName('trip_lead_id'),
				$this->db->quoteName('trip_sweep_id'),
				]
			)
			->from($this->db->quoteName('#__wendfold_events'))
			->where(
				'('
				. $this->db->quoteName('road_captain_id') . ' = :p1 OR '
				. $this->db->quoteName('sweep_id') . ' = :p2 OR '
				. $this->db->quoteName('volunteer_manager_id') . ' = :p3 OR '
				. $this->db->quoteName('volunteer_backup_id') . ' = :p4 OR '
				. $this->db->quoteName('trip_lead_id') . ' = :p5 OR '
				. $this->db->quoteName('trip_sweep_id') . ' = :p6'
				. ')'
			)
			->bind(':p1', $profileId, ParameterType::INTEGER)
			->bind(':p2', $profileId, ParameterType::INTEGER)
			->bind(':p3', $profileId, ParameterType::INTEGER)
			->bind(':p4', $profileId, ParameterType::INTEGER)
			->bind(':p5', $profileId, ParameterType::INTEGER)
			->bind(':p6', $profileId, ParameterType::INTEGER);

		return $this->db->setQuery($query)->loadAssocList() ?: [];
	}

	/**
	 * Load event-group rows where the profile holds a role.
	 *
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function loadEventGroupRoleRows(int $profileId): array
	{
		$query = $this->db->createQuery()
			->select([
				$this->db->quoteName('id'),
				$this->db->quoteName('event_id'),
				$this->db->quoteName('title'),
				$this->db->quoteName('road_captain_id'),
				$this->db->quoteName('sweep_id'),
				]
			)
			->from($this->db->quoteName('#__wendfold_event_groups'))
			->where(
				'('
				. $this->db->quoteName('road_captain_id') . ' = :p1 OR '
				. $this->db->quoteName('sweep_id') . ' = :p2'
				. ')'
			)
			->bind(':p1', $profileId, ParameterType::INTEGER)
			->bind(':p2', $profileId, ParameterType::INTEGER);

		return $this->db->setQuery($query)->loadAssocList() ?: [];
	}

	/**
	 * Load sync-log rows attributed to the member.
	 *
	 * Attribution uses performed_by or an exact whole-token match of the unique
	 * member_number. Names are ignored.
	 *
	 * @param   integer  $userId     Joomla user id.
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function loadSyncLogRows(int $userId, int $profileId): array
	{
		$profileRows = $this->loadByProfileId('#__wendfold_profiles', 'id', $profileId);
		$memberNumber = trim((string) ($profileRows[0]['member_number'] ?? ''));

		$all = $this->db->setQuery(
			$this->db->createQuery()
				->select('*')
				->from($this->db->quoteName('#__wendfold_sync_log'))
		)->loadAssocList() ?: [];

		$matched = [];

		foreach ($all as $row)
		{
			$performedBy = (int) ($row['performed_by'] ?? 0);
			$detail = (string) ($row['log_detail'] ?? '');

			if (SyncLogText::isAttributedToMember($performedBy, $userId, $detail, $memberNumber))
			{
				$matched[(int) $row['id']] = $row;
			}
		}

		return array_values($matched);
	}

}
