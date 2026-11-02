<?php

/**
 * Wendfold - in-memory privacy member store for unit tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Plugin\Privacy\Wendfold\Service\PrivacyMemberStoreInterface;
use Wendfold\Plugin\Privacy\Wendfold\Service\SyncLogText;

/**
 * Array-backed PrivacyMemberStoreInterface.
 *
 * @since  1.0.0
 */
final class MemoryPrivacyMemberStore implements PrivacyMemberStoreInterface
{
	/** @var list<array<string, mixed>> */
	public array $profiles = [];

	/** @var list<array<string, mixed>> */
	public array $motorcycles = [];

	/** @var list<array<string, mixed>> */
	public array $officers = [];

	/** @var list<array<string, mixed>> */
	public array $attendance = [];

	/** @var list<array<string, mixed>> */
	public array $rsvps = [];

	/** @var list<array<string, mixed>> */
	public array $eventGroupMembers = [];

	/** @var list<array<string, mixed>> */
	public array $eventVolunteers = [];

	/** @var list<array<string, mixed>> */
	public array $tripParticipants = [];

	/** @var list<array<string, mixed>> */
	public array $activities = [];

	/** @var list<array<string, mixed>> */
	public array $metricEntries = [];

	/** @var list<array<string, mixed>> */
	public array $memberAwards = [];

	/** @var list<array<string, mixed>> */
	public array $duesPayments = [];

	/** @var list<array<string, mixed>> */
	public array $reminderLog = [];

	/** @var list<array<string, mixed>> */
	public array $events = [];

	/** @var list<array<string, mixed>> */
	public array $eventGroups = [];

	/** @var list<array<string, mixed>> */
	public array $syncLog = [];

	/** @var array<int, array<string, mixed>> */
	public array $users = [];

	/**
	 * Number of transactions started.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $transactions = 0;


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
		foreach ($this->profiles as $profile)
		{
			if ((int) ($profile['user_id'] ?? 0) === $userId)
			{
				return $profile;
			}
		}

		return null;
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
		foreach ($this->officers as $officer)
		{
			if ((int) $officer['profile_id'] === $profileId
				&& (int) ($officer['is_current'] ?? 0) === 1
				&& (int) ($officer['published'] ?? 1) === 1)
			{
				return true;
			}
		}

		return false;
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
		$params = $this->users[$userId]['params'] ?? '';

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
		$profile = null;

		foreach ($this->profiles as $row)
		{
			if ((int) $row['id'] === $profileId)
			{
				$profile = $row;
				break;
			}
		}

		$domains = [
			'wendfold_profiles' => $profile !== null ? [$profile] : [],
			'wendfold_motorcycles' => $this->rowsFor($this->motorcycles, $profileId),
			'wendfold_officers' => $this->rowsFor($this->officers, $profileId),
			'wendfold_attendance' => $this->rowsFor($this->attendance, $profileId),
			'wendfold_rsvps' => $this->rowsFor($this->rsvps, $profileId),
			'wendfold_event_group_members' => $this->rowsFor($this->eventGroupMembers, $profileId),
			'wendfold_event_volunteers' => $this->rowsFor($this->eventVolunteers, $profileId),
			'wendfold_event_trip_participants' => $this->rowsFor($this->tripParticipants, $profileId),
			'wendfold_activities' => $this->rowsFor($this->activities, $profileId),
			'wendfold_metric_entries' => $this->rowsFor($this->metricEntries, $profileId),
			'wendfold_member_awards' => $this->rowsFor($this->memberAwards, $profileId),
			'wendfold_dues_payments' => $this->rowsFor($this->duesPayments, $profileId),
			'wendfold_reminder_log' => $this->rowsFor($this->reminderLog, $profileId),
			'wendfold_event_roles' => [],
			'wendfold_event_group_roles' => [],
			'wendfold_sync_log' => [],
		];

		foreach ($this->events as $event)
		{
			foreach (['road_captain_id', 'sweep_id', 'volunteer_manager_id', 'volunteer_backup_id', 'trip_lead_id', 'trip_sweep_id'] as $column)
			{
				if ((int) ($event[$column] ?? 0) === $profileId)
				{
					$domains['wendfold_event_roles'][] = $event;
					break;
				}
			}
		}

		foreach ($this->eventGroups as $group)
		{
			if ((int) ($group['road_captain_id'] ?? 0) === $profileId
				|| (int) ($group['sweep_id'] ?? 0) === $profileId)
			{
				$domains['wendfold_event_group_roles'][] = $group;
			}
		}

		$memberNumber = trim((string) ($profile['member_number'] ?? ''));

		foreach ($this->syncLog as $row)
		{
			$detail = (string) ($row['log_detail'] ?? '');
			$performedBy = (int) ($row['performed_by'] ?? 0);

			if (SyncLogText::isAttributedToMember($performedBy, $userId, $detail, $memberNumber))
			{
				$domains['wendfold_sync_log'][] = $row;
			}
		}

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
		$this->transactions++;
		$work();
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
		foreach ($this->profiles as &$profile)
		{
			if ((int) $profile['id'] !== $profileId)
			{
				continue;
			}

			$profile['user_id'] = null;
			$profile['member_number'] = null;
			$profile['preferred_name'] = '';
			$profile['emergency_name'] = '';
			$profile['emergency_phone'] = '';
			$profile['emergency_relation'] = '';
			$profile['shirt_size'] = '';
			$profile['photo'] = '';
			$profile['notes'] = null;
			$profile['chapter_on_file'] = '';
			$profile['waiver_version'] = '';
			$profile['sponsor_id'] = null;
			$profile['is_road_captain'] = 0;
			$profile['is_road_crew'] = 0;
			$profile['published'] = 0;
			$profile['member_status'] = 3;
			$profile['reminder_60_sent'] = null;
			$profile['reminder_30_sent'] = null;
		}

		unset($profile);
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
		foreach ($this->motorcycles as &$row)
		{
			if ((int) $row['profile_id'] !== $profileId)
			{
				continue;
			}

			$row['make'] = '';
			$row['model'] = '';
			$row['color'] = '';
			$row['nickname'] = '';
			$row['vin'] = '';
			$row['notes'] = null;
			$row['model_year'] = null;
			$row['year'] = null;
		}

		unset($row);
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
		foreach ($this->officers as &$row)
		{
			if ((int) $row['profile_id'] !== $profileId)
			{
				continue;
			}

			$row['notes'] = '';
			$row['is_current'] = 0;
		}

		unset($row);
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
		foreach ($this->attendance as &$row)
		{
			if ((int) $row['profile_id'] === $profileId)
			{
				$row['notes'] = '';
			}

			if ((int) ($row['checked_in_by'] ?? 0) === $userId)
			{
				$row['checked_in_by'] = 0;
			}
		}

		unset($row);
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
		$this->rsvps = array_values(
			array_filter(
				$this->rsvps,
				static fn (array $row): bool => (int) $row['profile_id'] !== $profileId
			)
		);
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
		foreach ($this->eventGroupMembers as &$row)
		{
			if ((int) $row['profile_id'] === $profileId)
			{
				$row['notes'] = '';
			}

			if ((int) ($row['assigned_by'] ?? 0) === $userId)
			{
				$row['assigned_by'] = 0;
			}
		}

		unset($row);
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
		foreach ($this->eventVolunteers as &$row)
		{
			if ((int) $row['profile_id'] === $profileId)
			{
				$row['notes'] = '';
			}
		}

		unset($row);
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
		foreach ($this->tripParticipants as &$row)
		{
			if ((int) $row['profile_id'] !== $profileId)
			{
				continue;
			}

			$row['lodging_notes'] = '';
			$row['arrival_notes'] = '';
			$row['departure_notes'] = '';
		}

		unset($row);
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
		foreach ($this->activities as &$row)
		{
			if ((int) $row['profile_id'] !== $profileId)
			{
				continue;
			}

			$row['title'] = '';
			$row['description'] = '';
			$row['start_location'] = '';
			$row['end_location'] = '';
			$row['motorcycle_id'] = null;
		}

		unset($row);
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
		foreach ($this->metricEntries as &$row)
		{
			if ((int) $row['profile_id'] === $profileId)
			{
				$row['note'] = '';
			}
		}

		unset($row);
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
		foreach ($this->memberAwards as &$row)
		{
			if ((int) $row['profile_id'] === $profileId)
			{
				$row['notes'] = '';
			}
		}

		unset($row);
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
		foreach ($this->duesPayments as &$row)
		{
			if ((int) $row['profile_id'] !== $profileId)
			{
				continue;
			}

			$row['notes'] = '';
			$row['receipt_number'] = '';
			$row['payment_method'] = '';
		}

		unset($row);
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
		foreach ($this->events as &$event)
		{
			foreach (['road_captain_id', 'sweep_id', 'volunteer_manager_id', 'volunteer_backup_id', 'trip_lead_id', 'trip_sweep_id'] as $column)
			{
				if ((int) ($event[$column] ?? 0) === $profileId)
				{
					$event[$column] = null;
				}
			}
		}

		unset($event);
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
		foreach ($this->eventGroups as &$group)
		{
			foreach (['road_captain_id', 'sweep_id'] as $column)
			{
				if ((int) ($group[$column] ?? 0) === $profileId)
				{
					$group[$column] = null;
				}
			}
		}

		unset($group);
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
		foreach ($this->profiles as &$profile)
		{
			if ((int) ($profile['sponsor_id'] ?? 0) === $profileId)
			{
				$profile['sponsor_id'] = null;
			}
		}

		unset($profile);
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
		foreach ($this->syncLog as &$row)
		{
			$detail = (string) ($row['log_detail'] ?? '');
			$performedBy = (int) ($row['performed_by'] ?? 0);

			if (!SyncLogText::isAttributedToMember($performedBy, $userId, $detail, $memberNumber))
			{
				continue;
			}

			$row['log_detail'] = SyncLogText::replaceExactTokens($detail, $redactionTokens, $replacement);

			if ($performedBy === $userId)
			{
				$row['performed_by'] = 0;
			}
		}

		unset($row);
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
		if (!isset($this->users[$userId]))
		{
			return;
		}

		$params = json_decode((string) ($this->users[$userId]['params'] ?? '{}'), true);

		if (!\is_array($params))
		{
			$params = [];
		}

		$params[RenewalReminderOptOut::PARAM_KEY] = 0;
		$this->users[$userId]['params'] = json_encode($params);
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
		foreach ($this->profiles as $profile)
		{
			if ((int) $profile['id'] === $profileId)
			{
				return (string) ($profile['photo'] ?? '');
			}
		}

		return '';
	}

	/**
	 * Filter rows belonging to a profile.
	 *
	 * @param   list<array<string, mixed>>  $rows       Table rows.
	 * @param   integer                     $profileId  Profile id.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	private function rowsFor(array $rows, int $profileId): array
	{
		return array_values(
			array_filter(
				$rows,
				static fn (array $row): bool => (int) ($row['profile_id'] ?? 0) === $profileId
			)
		);
	}

}
