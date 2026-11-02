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
 * Export and anonymization rules for Wendfold personal data.
 *
 * Aggregate rows (attendance, metric entries, awards, dues, officer history)
 * stay linked to the anonymized profile so chapter totals do not change.
 *
 * @since  1.0.0
 */
final class PrivacyDataHandler
{
	/**
	 * Language key returned when removal must be blocked.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const REFUSAL_CURRENT_OFFICER = 'PLG_PRIVACY_WENDFOLD_ERROR_CURRENT_OFFICER';

	/**
	 * Replacement used when redacting sync-log text.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const REDACTED = '[redacted]';

	/**
	 * Member store.
	 *
	 * @var    PrivacyMemberStoreInterface
	 * @since  1.0.0
	 */
	private PrivacyMemberStoreInterface $store;

	/**
	 * @param   PrivacyMemberStoreInterface  $store  Persistence adapter.
	 *
	 * @since   1.0.0
	 */
	public function __construct(PrivacyMemberStoreInterface $store)
	{
		$this->store = $store;
	}

	/**
	 * Reason language key when removal must be refused, or null when allowed.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function removalBlockReason(int $userId): ?string
	{
		$profile = $this->store->findProfileByUserId($userId);

		if ($profile === null)
		{
			return null;
		}

		if ($this->store->isCurrentOfficer((int) $profile['id']))
		{
			return self::REFUSAL_CURRENT_OFFICER;
		}

		return null;
	}

	/**
	 * Collect export domains for the user.
	 *
	 * @param   integer  $userId    Joomla user id.
	 * @param   string   $userName  Joomla display name.
	 *
	 * @return  array<string, list<array<string, mixed>>>
	 *
	 * @since   1.0.0
	 */
	public function export(int $userId, string $userName = ''): array
	{
		$domains = [];

		$domains['wendfold_reminder_optout'] = [
			[
				'user_id' => $userId,
				'wendfold_renewal_reminders_optout' => $this->store->isReminderOptedOut($userId) ? '1' : '0',
			],
		];

		$profile = $this->store->findProfileByUserId($userId);

		if ($profile === null)
		{
			return $domains;
		}

		$profileId = (int) $profile['id'];
		$loaded    = $this->store->loadExportDomains($profileId, $userId, $userName);

		foreach ($loaded as $name => $rows)
		{
			$domains[$name] = $rows;
		}

		return $domains;
	}

	/**
	 * Anonymize Wendfold personal data for the user.
	 *
	 * Idempotent: a second call after the profile user_id is cleared is a no-op
	 * aside from clearing the reminder opt-out again.
	 *
	 * @param   integer       $userId      Joomla user id.
	 * @param   string        $siteRoot    Absolute site root for photo files.
	 * @param   list<string>  $nameTokens  Extra name tokens to redact in sync logs.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function remove(int $userId, string $siteRoot, array $nameTokens = []): void
	{
		$profile = $this->store->findProfileByUserId($userId);

		if ($profile === null)
		{
			$this->store->clearReminderOptOut($userId);

			return;
		}

		$profileId = (int) $profile['id'];
		$photoPath = $this->store->getProfilePhotoPath($profileId);
		$memberNumber = trim((string) ($profile['member_number'] ?? ''));
		$redactionTokens = $this->buildRedactionTokens($profile, $nameTokens);

		$this->store->transaction(
			function () use ($profileId, $userId, $memberNumber, $redactionTokens): void
			{
				$this->store->anonymizeMotorcycles($profileId);
				$this->store->anonymizeOfficers($profileId);
				$this->store->anonymizeAttendance($profileId, $userId);
				$this->store->deleteRsvps($profileId);
				$this->store->anonymizeEventGroupMembers($profileId, $userId);
				$this->store->anonymizeEventVolunteers($profileId);
				$this->store->anonymizeTripParticipants($profileId);
				$this->store->anonymizeActivities($profileId);
				$this->store->anonymizeMetricEntries($profileId);
				$this->store->anonymizeMemberAwards($profileId);
				$this->store->anonymizeDuesPayments($profileId);
				$this->store->clearEventRoleAssignments($profileId);
				$this->store->clearEventGroupRoleAssignments($profileId);
				$this->store->clearSponsorLinks($profileId);
				$this->store->redactSyncLog($userId, $memberNumber, $redactionTokens, self::REDACTED);
				$this->store->anonymizeProfile($profileId);
				$this->store->clearReminderOptOut($userId);
			}
		);

		$this->deleteProfilePhotos($siteRoot, $profileId, $photoPath);
	}

	/**
	 * Build unique non-empty redaction tokens for attributed sync-log rows.
	 *
	 * These tokens are never used to attribute a row. Attribution uses only
	 * performed_by or the unique member_number.
	 *
	 * @param   array<string, mixed>  $profile     Profile row.
	 * @param   list<string>          $nameTokens  Extra tokens.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function buildRedactionTokens(array $profile, array $nameTokens): array
	{
		$candidates = array_merge(
			[
				(string) ($profile['member_number'] ?? ''),
				(string) ($profile['preferred_name'] ?? ''),
				(string) ($profile['emergency_name'] ?? ''),
			],
			$nameTokens
		);

		$tokens = [];

		foreach ($candidates as $token)
		{
			$token = trim($token);

			if ($token === '')
			{
				continue;
			}

			$tokens[$token] = $token;
		}

		return array_values($tokens);
	}

	/**
	 * Delete uploaded profile photo files for the member.
	 *
	 * @param   string  $siteRoot   Absolute site root.
	 * @param   integer $profileId  Profile id.
	 * @param   string  $photoPath  Relative photo path from the profile row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function deleteProfilePhotos(string $siteRoot, int $profileId, string $photoPath): void
	{
		$root = rtrim(str_replace('\\', '/', $siteRoot), '/');

		if ($root === '')
		{
			return;
		}

		$pattern = $root . '/images/wendfold/profiles/' . $profileId . '.*';

		foreach (glob($pattern) ?: [] as $file)
		{
			if (is_file($file))
			{
				@unlink($file);
			}
		}

		$relative = ltrim(str_replace('\\', '/', $photoPath), '/');

		if ($relative === '' || str_contains($relative, '..'))
		{
			return;
		}

		$absolute = $root . '/' . $relative;

		if (is_file($absolute))
		{
			@unlink($absolute);
		}
	}
}
