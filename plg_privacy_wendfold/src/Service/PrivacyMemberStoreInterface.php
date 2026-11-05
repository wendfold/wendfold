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

/**
 * Persistence used by the Wendfold privacy handler.
 *
 * @since  1.0.0
 */
interface PrivacyMemberStoreInterface
{
	/**
	 * Load the Wendfold profile linked to a Joomla user id.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  array<string, mixed>|null
	 *
	 * @since   1.0.0
	 */
	public function findProfileByUserId(int $userId): ?array;

	/**
	 * Whether the profile currently holds an officer role.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function isCurrentOfficer(int $profileId): bool;

	/**
	 * Read the renewal reminder opt-out flag for a Joomla user.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function isReminderOptedOut(int $userId): bool;

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
	public function loadExportDomains(int $profileId, int $userId, string $userName): array;

	/**
	 * Run the callable inside a database transaction.
	 *
	 * @param   callable  $work  Work to run.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transaction(callable $work): void;

	/**
	 * Anonymize the profile row and clear personal fields.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeProfile(int $profileId): void;

	/**
	 * Clear personal fields on motorcycle rows for the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMotorcycles(int $profileId): void;

	/**
	 * Clear personal fields on officer history rows and mark them not current.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeOfficers(int $profileId): void;

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
	public function anonymizeAttendance(int $profileId, int $userId): void;

	/**
	 * Delete RSVP rows for the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function deleteRsvps(int $profileId): void;

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
	public function anonymizeEventGroupMembers(int $profileId, int $userId): void;

	/**
	 * Clear personal notes on volunteer assignment rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeEventVolunteers(int $profileId): void;

	/**
	 * Clear lodging and travel notes on trip participant rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeTripParticipants(int $profileId): void;

	/**
	 * Clear personal text on activity rows and drop motorcycle links.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeActivities(int $profileId): void;

	/**
	 * Clear personal notes on metric entry rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMetricEntries(int $profileId): void;

	/**
	 * Clear personal notes on member award rows.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeMemberAwards(int $profileId): void;

	/**
	 * Clear personal fields on dues payment rows while keeping amounts.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function anonymizeDuesPayments(int $profileId): void;

	/**
	 * Null event role columns that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearEventRoleAssignments(int $profileId): void;

	/**
	 * Null event-group role columns that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearEventGroupRoleAssignments(int $profileId): void;

	/**
	 * Clear sponsor links that point at the profile.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearSponsorLinks(int $profileId): void;

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
	): void;

	/**
	 * Clear the Wendfold renewal reminder opt-out on the Joomla user.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function clearReminderOptOut(int $userId): void;

	/**
	 * Return the photo path stored on the profile before anonymization.
	 *
	 * @param   integer  $profileId  Wendfold profile id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getProfilePhotoPath(int $profileId): string;

}
