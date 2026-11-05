<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * National Sync Model
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\ChapterTransferReview;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\CsvRosterParser;
use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\ImportLock;
use Wendfold\Component\Wendfold\Administrator\Helper\ImportRowFreshness;
use Wendfold\Component\Wendfold\Administrator\Helper\MembershipImport;
use Wendfold\Component\Wendfold\Administrator\Service\ReminderService;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

/**
 * Sync Model
 *
 * Handles CSV import, renewal reminders, lapsed member cleanup,
 * and chapter-switch conflict detection/resolution.
 *
 * @since  1.0.0
 */
class SyncModel extends BaseDatabaseModel
{
	/**
	 * Language keys for mapped roster fields.
	 *
	 * @var  array<string, string>
	 */
	private const CSV_FIELD_LABELS = [
		'member_id'       => 'COM_WENDFOLD_CSV_COL_MEMBER_ID',
		'first_name'      => 'COM_WENDFOLD_CSV_COL_FIRST_NAME',
		'last_name'       => 'COM_WENDFOLD_CSV_COL_LAST_NAME',
		'email'           => 'COM_WENDFOLD_CSV_COL_EMAIL',
		'phone'           => 'COM_WENDFOLD_CSV_COL_PHONE',
		'address'         => 'COM_WENDFOLD_CSV_COL_ADDRESS',
		'city'            => 'COM_WENDFOLD_CSV_COL_CITY',
		'state'           => 'COM_WENDFOLD_CSV_COL_STATE',
		'postal_code'     => 'COM_WENDFOLD_CSV_COL_POSTAL_CODE',
		'country'         => 'COM_WENDFOLD_CSV_COL_COUNTRY',
		'join_date'       => 'COM_WENDFOLD_CSV_COL_JOIN_DATE',
		'expiry_date'     => 'COM_WENDFOLD_CSV_COL_EXPIRY_DATE',
		'membership_type' => 'COM_WENDFOLD_CSV_COL_MEMBERSHIP_TYPE',
		'life_member'     => 'COM_WENDFOLD_CSV_COL_LIFE_MEMBER',
		'status'          => 'COM_WENDFOLD_CSV_COL_STATUS',
		'chapter'         => 'COM_WENDFOLD_CSV_COL_CHAPTER',
	];

	// =========================================================
	// CSV Import
	// =========================================================

	/**
	 * Import a member roster CSV.
	 *
	 * Column headers come from the CSV Import component options. The
	 * identifier defaults to "Member ID" and also accepts member_id,
	 * memberid, member number, and membernumber. Dates, emails, and the
	 * other mapped values are validated before any write. A validation
	 * failure rolls nothing forward: existing rows stay as they were.
	 *
	 * A chapter value that does not match the configured chapter number
	 * flags the profile (chapter_switch_flagged = 1) and updates
	 * chapter_on_file. The member is not unpublished.
	 *
	 * @param   array    $file    Uploaded file array (from Joomla input files)
	 * @param   boolean  $dryRun  Preview mode -- no DB writes
	 *
	 * @return  array    Stats array with keys: success, processed, updated,
	 *                   added, skipped, errored, flagged, error, preview
	 *
	 * @since   1.0.0
	 */
	public function processCsvUpload($file, $dryRun = false)
	{
		$stats = [
			'success'    => false,
			'processed'  => 0,
			'updated'    => 0,
			'added'      => 0,
			'skipped'    => 0,
			'errored'    => 0,
			'flagged'    => 0,
			'error'        => '',
			'notes'        => [],
			'row_errors'   => [],
			'row_warnings' => [],
			'preview'      => null,
		];

		$tmpName = (string) ($file['tmp_name'] ?? '');
		$bytes   = (int) ($file['size'] ?? 0);

		if ($tmpName !== '' && is_file($tmpName))
		{
			$bytes = max($bytes, (int) filesize($tmpName));
		}

		if ($bytes > CsvRosterParser::MAX_BYTES)
		{
			$stats['error'] = Text::_('COM_WENDFOLD_SYNC_FILE_TOO_LARGE');

			return $stats;
		}

		$params           = $this->getComponentParams();
		$ourChapterNumber = trim((string) $params->get('chapter_number', ''));
		$tmpRoot          = (string) Factory::getApplication()->get('tmp_path', '');

		if ($tmpRoot === '' || !is_dir($tmpRoot))
		{
			$tmpRoot = sys_get_temp_dir();
		}

		$tmpPath = rtrim($tmpRoot, '/\\') . '/wendfold_sync_' . bin2hex(random_bytes(8)) . '.csv';
		$parsed  = null;

		if ($tmpName === '' || !is_uploaded_file($tmpName) || !move_uploaded_file($tmpName, $tmpPath))
		{
			if (is_file($tmpPath))
			{
				unlink($tmpPath);
			}

			$stats['error'] = Text::_('COM_WENDFOLD_SYNC_UPLOAD_FAILED');

			return $stats;
		}

		try
		{
			$parsed = CsvRosterParser::parse($tmpPath, $this->getCsvColumnMapping());
		}
		catch (\Throwable $exception)
		{
			$parsed = null;

			try
			{
				\Joomla\CMS\Log\Log::add($exception->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_wendfold');
			}
			catch (\Throwable $logError)
			{
				// Logging must not hide the failed read.
			}
		}
		finally
		{
			if (is_file($tmpPath))
			{
				unlink($tmpPath);
			}
		}

		if (!is_array($parsed))
		{
			$stats['error'] = Text::_('COM_WENDFOLD_SYNC_READ_FAILED');

			return $stats;
		}

		$preview             = $this->buildCsvPreview($parsed);
		$stats['preview']    = $preview;
		$stats['notes']      = $preview['notes'];
		$stats['row_errors'] = $preview['errors'];
		$stats['processed']  = (int) $parsed['processed'];

		if ($parsed['file_error'] !== null)
		{
			$stats['error'] = $preview['file_error_message'];

			return $stats;
		}

		if ($parsed['row_errors'] !== [])
		{
			$stats['errored'] = count($parsed['row_errors']);
			$stats['error']   = Text::sprintf('COM_WENDFOLD_SYNC_VALIDATION_FAILED', $stats['errored']);

			return $stats;
		}

		$profiles    = $this->loadProfilesByMemberNumber(array_column($parsed['rows'], 'member_id'));
		$emailErrors = $this->findCsvEmailConflicts($parsed['rows'], $profiles);

		if ($emailErrors !== [])
		{
			$stats['row_errors']        = $emailErrors;
			$stats['preview']['errors'] = $emailErrors;
			$stats['preview']['valid']  = false;
			$stats['errored']           = count($emailErrors);
			$stats['error']             = Text::sprintf('COM_WENDFOLD_SYNC_VALIDATION_FAILED', $stats['errored']);

			return $stats;
		}

		$plan             = $this->planCsvUpdates($parsed['rows'], $profiles, $ourChapterNumber);
		$plan['writes']   = $this->attachMembershipTypes($plan['writes']);
		$stats['updated'] = $plan['updated'];
		$stats['skipped'] = $plan['skipped'];
		$stats['flagged'] = $plan['flagged'];
		$stats['row_warnings'] = $this->membershipTypeWarnings($plan['writes']);
		$stats['preview']['warnings'] = $stats['row_warnings'];

		if ($dryRun)
		{
			$stats['success'] = true;

			return $stats;
		}

		$db = $this->getDatabase();

		if (!ImportLock::acquire($db))
		{
			$stats['error'] = Text::_('COM_WENDFOLD_SYNC_IMPORT_ALREADY_RUNNING');

			return $stats;
		}

		try
		{
			try
			{
				$db->transactionStart();

				$applied = 0;
				$flaggedApplied = 0;
				$flaggedDetailsApplied = [];
				$staleNotes = [];

				foreach ($plan['writes'] as $write)
				{
					$row = $write['row'];
					$line = (int) ($row['line'] ?? 0);
					$previewProfile = $write['profile'];
					$fresh = $this->lockCsvProfileForUpdate((int) $previewProfile->id);

					if ($fresh === null)
					{
						$stats['skipped']++;
						$staleNotes[] = Text::sprintf(
							'COM_WENDFOLD_SYNC_ROW_WARNING',
							$line,
							Text::_('COM_WENDFOLD_SYNC_WARN_MODIFIED_SINCE_PREVIEW')
						);
						continue;
					}

					$previewModified = isset($previewProfile->modified)
						? (string) $previewProfile->modified
						: null;
					$currentModified = isset($fresh->modified)
						? (string) $fresh->modified
						: null;

					if (ImportRowFreshness::changedSincePreview($previewModified, $currentModified))
					{
						$stats['skipped']++;
						$staleNotes[] = Text::sprintf(
							'COM_WENDFOLD_SYNC_ROW_WARNING',
							$line,
							Text::_('COM_WENDFOLD_SYNC_WARN_MODIFIED_SINCE_PREVIEW')
						);
						continue;
					}

					try
					{
						$this->writeCsvProfileUpdate(
							$fresh,
							$row,
							(bool) $write['mismatch'],
							(int) ($write['membership_type_id'] ?? 0)
						);
						$this->writeCsvUserUpdate($fresh, $row);
					}
					catch (\Throwable $writeError)
					{
						if (DuplicateKey::is($writeError))
						{
							throw new \RuntimeException(
								Text::sprintf('COM_WENDFOLD_SYNC_ERR_DUPLICATE_KEY', $line),
								0,
								$writeError
							);
						}

						throw $writeError;
					}

					$applied++;

					if (!empty($write['mismatch']))
					{
						$flaggedApplied++;
						$flaggedDetailsApplied[] = 'Member ' . $row['member_id']
							. ' (chapter ' . $row['chapter'] . ')';
					}
				}

				$stats['updated'] = $applied;
				$stats['flagged'] = $flaggedApplied;
				$stats['row_warnings'] = array_merge($stats['row_warnings'], $staleNotes);
				$stats['preview']['warnings'] = $stats['row_warnings'];

				$this->insertCsvSyncLog($file, $stats, $flaggedDetailsApplied, $preview['log_notes']);
				$db->transactionCommit();
			}
			catch (\Throwable $exception)
			{
				try
				{
					$db->transactionRollback();
				}
				catch (\Throwable $rollbackError)
				{
					// The connection may already have rolled back.
				}

				try
				{
					\Joomla\CMS\Log\Log::add($exception->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_wendfold');
				}
				catch (\Throwable $logError)
				{
					// Logging must not hide the failed import.
				}

				$stats['success'] = false;
				$stats['updated'] = 0;
				$stats['flagged'] = 0;
				$stats['error']   = $this->csvWriteFailureMessage($exception);

				return $stats;
			}
		}
		finally
		{
			ImportLock::release($db);
		}

		$stats['success'] = true;

		return $stats;
	}

	/**
	 * Translated message for a failed CSV write, including mapped duplicate keys.
	 *
	 * @param   \Throwable  $exception  Write or wrapper exception.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function csvWriteFailureMessage(\Throwable $exception): string
	{
		$previous = $exception->getPrevious();

		if ($previous instanceof \Throwable && DuplicateKey::is($previous))
		{
			return $exception->getMessage();
		}

		if (DuplicateKey::is($exception))
		{
			$message = $exception->getMessage();

			return $message !== '' ? $message : Text::_('COM_WENDFOLD_SYNC_WRITE_FAILED');
		}

		return Text::_('COM_WENDFOLD_SYNC_WRITE_FAILED');
	}

	/**
	 * Re-read one profile with FOR UPDATE inside the import transaction.
	 *
	 * @param   integer  $profileId  Profile primary key.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function lockCsvProfileForUpdate(int $profileId): ?object
	{
		if ($profileId < 1)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('user_id'),
					$db->quoteName('member_number'),
					$db->quoteName('member_since'),
					$db->quoteName('modified'),
				]
			)
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$query->setQuery((string) $query . ' FOR UPDATE');
		$row = $db->setQuery($query)->loadObject();

		return \is_object($row) ? $row : null;
	}

	// =========================================================
	// Chapter Transfers
	// =========================================================

	/**
	 * Return all profiles where chapter_switch_flagged = 1.
	 *
	 * Joined to #__users so we have the member name for display.
	 *
	 * @return  array  Array of stdClass objects.
	 *
	 * @since   1.0.0
	 */
	public function getFlaggedMembers()
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				'p.id',
				'p.member_number',
				'p.chapter_on_file',
				'p.national_sync_date',
				'p.published',
				'u.name',
			])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->where($db->quoteName('p.chapter_switch_flagged') . ' = 1')
			->order('u.name ASC');

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	/**
	 * Unpublish a flagged profile and clear the chapter-switch flag.
	 *
	 * A profile that is not flagged is left unchanged. The sync log gains a
	 * row only when this update changes a row.
	 *
	 * @param   int  $profileId  Profile ID to remove.
	 *
	 * @return  boolean  True when a flagged row was unpublished.
	 *
	 * @since   1.0.0
	 */
	public function removeFromRoster(int $profileId): bool
	{
		return $this->resolveFlaggedMember($profileId, true);
	}

	/**
	 * Clear the chapter-switch flag without changing published state.
	 *
	 * A profile that is not flagged is left unchanged. The sync log gains a
	 * row only when this update changes a row.
	 *
	 * @param   int  $profileId  Profile ID to keep.
	 *
	 * @return  boolean  True when a flagged row was kept.
	 *
	 * @since   1.0.0
	 */
	public function keepMember(int $profileId): bool
	{
		return $this->resolveFlaggedMember($profileId, false);
	}

	/**
	 * Keep or unpublish one profile that is still flagged.
	 *
	 * The update matches chapter_switch_flagged = 1, so a member who was
	 * never flagged, or who was already resolved, is not written again and
	 * does not get another sync log row.
	 *
	 * @param   integer  $profileId  Profile id.
	 * @param   boolean  $unpublish  True sets published to 0. False leaves it.
	 *
	 * @return  boolean  True when one flagged row changed.
	 *
	 * @since   1.0.0
	 */
	private function resolveFlaggedMember(int $profileId, bool $unpublish): bool
	{
		$db   = $this->getDatabase();
		$user = Factory::getApplication()->getIdentity();

		$query = $db->createQuery()
			->select(['member_number', 'chapter_on_file', 'chapter_switch_flagged'])
			->from($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('id') . ' = ' . (int) $profileId);

		$profile = $db->setQuery($query)->loadObject();

		if (!is_object($profile) || !ChapterTransferReview::isOpen($profile))
		{
			return false;
		}

		try
		{
			$update = $db->createQuery()
				->update($db->quoteName('#__wendfold_profiles'))
				->set($db->quoteName('chapter_switch_flagged') . ' = 0')
				->set($db->quoteName('modified') . ' = NOW()')
				->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
				->where($db->quoteName('id') . ' = ' . (int) $profileId)
				->where($db->quoteName('chapter_switch_flagged') . ' = 1');

			if ($unpublish)
			{
				$update->set($db->quoteName('published') . ' = 0');
			}

			$db->setQuery($update)->execute();

			if (!ChapterTransferReview::wrote((int) $db->getAffectedRows()))
			{
				return false;
			}

			$detail = ($unpublish
				? 'Removed from roster via chapter transfer review: Member # '
				: 'Kept on roster via chapter transfer review: Member # ')
				. $profile->member_number
				. ' (CSV chapter: ' . $profile->chapter_on_file . ')';

			$log = $db->createQuery()
				->insert($db->quoteName('#__wendfold_sync_log'))
				->columns([
					'sync_type', 'sync_date', 'performed_by',
					'records_processed', 'records_updated',
					'records_added', 'records_skipped', 'records_errored',
					'filename', 'status', 'log_detail',
				])
				->values(
					'2, NOW(), ' . (int) $user->id
					. ', 1, 1, 0, 0, 0'
					. ', ' . $db->quote($unpublish ? 'chapter_transfer_remove' : 'chapter_transfer_keep')
					. ', 1'
					. ', ' . $db->quote($detail)
				);

			$db->setQuery($log)->execute();

			return true;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	// =========================================================
	// Lapsed Member Cleanup (unchanged from Phase 10)
	// =========================================================

	/**
	 * Unpublish members whose renewal_date has lapsed beyond grace_days.
	 *
	 * @param   int  $graceDays  Number of days past expiry before unpublishing.
	 *
	 * @return  array  Stats with keys: unpublished, members (array of objects).
	 *
	 * @since   1.0.0
	 */
	public function runLapsedCleanup(int $graceDays = 30): array
	{
		$db      = $this->getDatabase();
		$user    = Factory::getApplication()->getIdentity();
		$site    = SiteTime::fromApplication();
		$cutoff  = $site->shiftDays(-$graceDays);
		$ranOn   = $site->today();

		$result = [
			'unpublished' => 0,
			'members'     => [],
		];

		// Find lapsed members
		$query = $db->createQuery()
			->select(['p.id', 'p.member_number', 'p.renewal_date', 'u.name'])
			->from($db->quoteName('#__wendfold_profiles', 'p'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = p.user_id')
			->where($db->quoteName('p.published') . ' = 1')
			->where($db->quoteName('p.renewal_date') . ' IS NOT NULL')
			->where($db->quoteName('p.renewal_date') . ' < ' . $db->quote($cutoff))
			->order('p.renewal_date ASC');

		$lapsed = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($lapsed as $member)
		{
			$update = $db->createQuery()
				->update($db->quoteName('#__wendfold_profiles'))
				->set($db->quoteName('published') . ' = 0')
				->set($db->quoteName('modified') . ' = NOW()')
				->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
				->where($db->quoteName('id') . ' = ' . (int) $member->id);

			$db->setQuery($update)->execute();

			$result['unpublished']++;
			$result['members'][] = $member;
		}

		// Log
		if ($result['unpublished'] > 0)
		{
			$names = array_map(static function ($m) {
				return $m->name . ' (Member # ' . $m->member_number . ')';
			}, $result['members']);

			$detail = 'Lapsed cleanup: unpublished ' . $result['unpublished']
				. ' member(s): ' . implode(', ', $names);

			$log = $db->createQuery()
				->insert($db->quoteName('#__wendfold_sync_log'))
				->columns([
					'sync_type', 'sync_date', 'performed_by',
					'records_processed', 'records_updated',
					'records_added', 'records_skipped', 'records_errored',
					'filename', 'status', 'log_detail',
				])
				->values(
					'2, NOW(), ' . (int) $user->id
					. ', ' . (int) $result['unpublished']
					. ', ' . (int) $result['unpublished']
					. ', 0, 0, 0'
					. ', ' . $db->quote('lapsed_cleanup_' . $ranOn)
					. ', 1'
					. ', ' . $db->quote($detail)
				);

			$db->setQuery($log)->execute();
		}

		return $result;
	}

	// =========================================================
	// Sync Log
	// =========================================================

	/**
	 * Get recent sync log entries.
	 *
	 * @param   int  $limit  Number of entries to return.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getSyncLog(int $limit = 10): array
	{
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				's.id',
				's.sync_type',
				's.sync_date',
				's.records_processed',
				's.records_updated',
				's.records_added',
				's.records_skipped',
				's.records_errored',
				's.filename',
				's.log_detail',
				'u.name AS performed_by_name',
			])
			->from($db->quoteName('#__wendfold_sync_log', 's'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON u.id = s.performed_by')
			->order('s.sync_date DESC')
			->setLimit($limit);

		return $db->setQuery($query)->loadObjectList() ?: [];
	}

	// =========================================================
	// Renewal Reminders (unchanged from Phase 10)
	// =========================================================

	/**
	 * Send renewal reminder emails via the shared ReminderService.
	 *
	 * @return  array  Stats with keys: sent_60, sent_30, failed, days_60, days_30.
	 *
	 * @since   1.0.0
	 */
	public function sendRenewalReminders(): array
	{
		$params  = $this->getComponentParams();
		$windows = ComponentOptions::reminderWindows(
			$params->get('reminder_days_60', 60),
			$params->get('reminder_days_30', 30)
		);
		$app       = Factory::getApplication();
		$fromEmail = (string) $app->get('mailfrom');
		$fromName  = (string) $app->get('fromname', 'Wendfold');

		$service = ReminderService::fromDatabase(
			$this->getDatabase(),
			SiteTime::fromApplication(),
			$windows,
			$fromEmail,
			$fromName
		);

		return $service->sendRenewalReminders();
	}

	// =========================================================
	// Private helpers
	// =========================================================

	/**
	 * Get component params object.
	 *
	 * @return  \Joomla\Registry\Registry
	 *
	 * @since   1.0.0
	 */
	private function getComponentParams(): \Joomla\Registry\Registry
	{
		return \Joomla\CMS\Component\ComponentHelper::getParams('com_wendfold');
	}

	/**
	 * Header names saved in the component options, with built-in defaults.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	private function getCsvColumnMapping(): array
	{
		$params = $this->getComponentParams();
		$map    = [];

		foreach (CsvRosterParser::DEFAULT_COLUMNS as $field => $default)
		{
			$value = trim((string) $params->get('csv_col_' . $field, $default));
			$map[$field] = $value !== '' ? $value : $default;
		}

		return $map;
	}

	/**
	 * Build the escaped-ready preview payload for the sync screen.
	 *
	 * Values that originated in the file are already filtered by the parser.
	 * Percent signs are doubled before they are placed in translated strings.
	 *
	 * @param   array  $parsed  Parser result.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function buildCsvPreview(array $parsed): array
	{
		$errors = [];

		foreach ($parsed['row_errors'] as $error)
		{
			$errors[] = $this->translateCsvRowError($error);
		}

		if (!empty($parsed['truncated_errors']))
		{
			$errors[] = Text::_('COM_WENDFOLD_SYNC_ERR_TRUNCATED');
		}

		$notes    = [];
		$logNotes = [];

		if (!empty($parsed['header_line']))
		{
			$notes[]    = Text::sprintf('COM_WENDFOLD_SYNC_HEADER_ROW_NOTE', (int) $parsed['header_line']);
			$logNotes[] = 'Header row ' . (int) $parsed['header_line'];
		}

		$delimiterLabel = $this->csvDelimiterLabel((string) $parsed['delimiter']);
		$logNotes[]     = 'Delimiter ' . $delimiterLabel;

		if (!empty($parsed['bom']))
		{
			$logNotes[] = 'UTF-8 BOM removed';
		}

		$unmapped = array_slice($parsed['unmapped'] ?? [], 0, 8);

		if ($unmapped !== [])
		{
			$notes[]    = Text::sprintf('COM_WENDFOLD_SYNC_UNMAPPED_NOTE', $this->safeSprintfValue(implode(', ', $unmapped)));
			$logNotes[] = 'Unmapped columns: ' . implode(', ', $unmapped);
		}

		return [
			'valid'              => $parsed['file_error'] === null && $parsed['row_errors'] === [],
			'delimiter'          => (string) $parsed['delimiter'],
			'delimiter_label'    => $delimiterLabel,
			'bom'                => !empty($parsed['bom']),
			'header_line'        => (int) $parsed['header_line'],
			'headers'            => $parsed['headers'],
			'mapping'            => $parsed['mapping'],
			'sample'             => $parsed['sample'],
			'unmapped'           => $unmapped,
			'errors'             => $errors,
			'notes'              => $notes,
			'log_notes'          => $logNotes,
			'file_error_message' => $parsed['file_error'] !== null ? $this->translateCsvFileError($parsed) : '',
		];
	}

	/**
	 * @param   array  $parsed  Parser result.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function translateCsvFileError(array $parsed): string
	{
		switch ($parsed['file_error'])
		{
			case 'too_large':
				return Text::_('COM_WENDFOLD_SYNC_FILE_TOO_LARGE');

			case 'empty':
				return Text::_('COM_WENDFOLD_SYNC_EMPTY_FILE');

			case 'encoding':
				return Text::_('COM_WENDFOLD_SYNC_ERR_ENCODING');

			case 'binary':
				return Text::_('COM_WENDFOLD_SYNC_ERR_BINARY');

			case 'missing_id_column':
				return Text::sprintf(
					'COM_WENDFOLD_SYNC_MISSING_ID_COLUMN',
					$this->safeSprintfValue((string) ($parsed['file_error_detail'] ?? 'Member ID'))
				);

			case 'too_many_rows':
				return Text::sprintf('COM_WENDFOLD_SYNC_ERR_TOO_MANY_ROWS', CsvRosterParser::MAX_DATA_ROWS);

			case 'too_many_columns':
				return Text::sprintf('COM_WENDFOLD_SYNC_ERR_TOO_MANY_COLUMNS', CsvRosterParser::MAX_COLUMNS);

			default:
				return Text::_('COM_WENDFOLD_SYNC_READ_FAILED');
		}
	}

	/**
	 * @param   array  $error  Parser row error.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function translateCsvRowError(array $error): string
	{
		$field = (string) ($error['field'] ?? '');
		$label = Text::_(self::CSV_FIELD_LABELS[$field] ?? 'COM_WENDFOLD_SYNC_CSV_FILE_LABEL');
		$code  = (string) ($error['code'] ?? '');

		switch ($code)
		{
			case 'empty_member_id':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_EMPTY_MEMBER_ID');
				break;

			case 'invalid_member_id':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_INVALID_MEMBER_ID');
				break;

			case 'duplicate_member_id':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_DUPLICATE_MEMBER_ID');
				break;

			case 'invalid_email':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_INVALID_EMAIL');
				break;

			case 'invalid_phone':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_INVALID_PHONE');
				break;

			case 'invalid_date':
				$message = Text::sprintf('COM_WENDFOLD_SYNC_ERR_INVALID_DATE', $label);
				break;

			case 'invalid_type':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_INVALID_TYPE');
				break;

			case 'invalid_status':
				$message = Text::_('COM_WENDFOLD_SYNC_ERR_INVALID_STATUS');
				break;

			case 'too_long':
				$message = Text::sprintf('COM_WENDFOLD_SYNC_ERR_TOO_LONG', $label);
				break;

			case 'illegal_characters':
				$message = Text::sprintf('COM_WENDFOLD_SYNC_ERR_ILLEGAL', $label);
				break;

			default:
				$message = Text::_('COM_WENDFOLD_SYNC_READ_FAILED');
				break;
		}

		return Text::sprintf('COM_WENDFOLD_SYNC_ROW_ERROR', (int) ($error['line'] ?? 0), $message);
	}

	/**
	 * @param   string  $delimiter  Detected separator.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function csvDelimiterLabel(string $delimiter): string
	{
		if ($delimiter === ';')
		{
			return Text::_('COM_WENDFOLD_SYNC_DELIMITER_SEMICOLON');
		}

		if ($delimiter === "\t")
		{
			return Text::_('COM_WENDFOLD_SYNC_DELIMITER_TAB');
		}

		return Text::_('COM_WENDFOLD_SYNC_DELIMITER_COMMA');
	}

	/**
	 * @param   string  $value  Text that will be passed to Text::sprintf().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function safeSprintfValue(string $value): string
	{
		return str_replace('%', '%%', $value);
	}

	/**
	 * @param   array  $memberNumbers  Identifier values from the file.
	 *
	 * @return  array<string, object>
	 *
	 * @since   1.0.0
	 */
	private function loadProfilesByMemberNumber(array $memberNumbers): array
	{
		$unique = [];

		foreach ($memberNumbers as $number)
		{
			$number = trim((string) $number);

			if ($number !== '')
			{
				$unique[$number] = $number;
			}
		}

		$map = [];

		if ($unique === [])
		{
			return $map;
		}

		$db = $this->getDatabase();

		foreach (array_chunk(array_values($unique), 400) as $chunk)
		{
			$query = $db->createQuery()
				->select([
					$db->quoteName('id'),
					$db->quoteName('user_id'),
					$db->quoteName('member_number'),
					$db->quoteName('member_since'),
					$db->quoteName('modified'),
				])
				->from($db->quoteName('#__wendfold_profiles'))
				->whereIn($db->quoteName('member_number'), $chunk, ParameterType::STRING);

			$loaded = $db->setQuery($query)->loadObjectList() ?: [];

			foreach ($loaded as $profile)
			{
				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Mirrors the member_number database column.
				$map[strtoupper(trim((string) $profile->member_number))] = $profile;
			}
		}

		return $map;
	}

	/**
	 * Reject the file when a mapped email belongs to a different account.
	 *
	 * @param   array  $rows      Validated parser rows.
	 * @param   array  $profiles  Profiles keyed by upper-case member id.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	private function findCsvEmailConflicts(array $rows, array $profiles): array
	{
		$intended = [];
		$errors   = [];

		foreach ($rows as $row)
		{
			if (empty($row['has_email']))
			{
				continue;
			}

			$key = strtoupper((string) $row['member_id']);

			if (!isset($profiles[$key]))
			{
				continue;
			}

			$emailKey = strtolower((string) $row['email']);
			$userId   = (int) $profiles[$key]->user_id;

			if (isset($intended[$emailKey]) && $intended[$emailKey]['user_id'] !== $userId)
			{
				$errors[] = Text::sprintf(
					'COM_WENDFOLD_SYNC_ROW_ERROR',
					(int) $row['line'],
					Text::_('COM_WENDFOLD_SYNC_ERR_EMAIL_CONFLICT')
				);
			}

			$intended[$emailKey] = [
				'user_id' => $userId,
				'line'    => (int) $row['line'],
				'email'   => (string) $row['email'],
			];
		}

		if ($intended === [])
		{
			return array_values(array_unique($errors));
		}

		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('email'),
			])
			->from($db->quoteName('#__users'))
			->whereIn($db->quoteName('email'), array_column($intended, 'email'), ParameterType::STRING);

		$owners = $db->setQuery($query)->loadObjectList() ?: [];

		foreach ($owners as $owner)
		{
			$emailKey = strtolower((string) $owner->email);

			if (!isset($intended[$emailKey]))
			{
				continue;
			}

			if ((int) $owner->id !== $intended[$emailKey]['user_id'])
			{
				$errors[] = Text::sprintf(
					'COM_WENDFOLD_SYNC_ROW_ERROR',
					$intended[$emailKey]['line'],
					Text::_('COM_WENDFOLD_SYNC_ERR_EMAIL_CONFLICT')
				);
			}
		}

		return array_values(array_unique($errors));
	}

	/**
	 * @param   array   $rows              Validated parser rows.
	 * @param   array   $profiles          Profiles keyed by upper-case member id.
	 * @param   string  $ourChapterNumber  Configured chapter number.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function planCsvUpdates(array $rows, array $profiles, string $ourChapterNumber): array
	{
		$plan = [
			'updated'         => 0,
			'skipped'         => 0,
			'flagged'         => 0,
			'flagged_details' => [],
			'writes'          => [],
		];

		$ourChapter = strtoupper(trim($ourChapterNumber));

		foreach ($rows as $row)
		{
			$key = strtoupper((string) $row['member_id']);

			if (!isset($profiles[$key]))
			{
				$plan['skipped']++;
				continue;
			}

			$plan['updated']++;
			$mismatch = false;

			if ($ourChapter !== '' && !empty($row['has_chapter'])
				&& strtoupper((string) $row['chapter']) !== $ourChapter)
			{
				$mismatch = true;
				$plan['flagged']++;
				$plan['flagged_details'][] = 'Member ' . $row['member_id'] . ' (chapter ' . $row['chapter'] . ')';
			}

			$plan['writes'][] = [
				'profile'  => $profiles[$key],
				'row'      => $row,
				'mismatch' => $mismatch,
			];
		}

		return $plan;
	}

	/**
	 * Lookup id for a roster import code, or for the life-member flag.
	 *
	 * @param   string  $code  Import code, or MembershipImport::LIFE_FLAG.
	 *
	 * @return  integer  0 when no published type matches.
	 *
	 * @since   1.0.0
	 */
	private function membershipTypeIdForImport(string $code): int
	{
		$db = $this->getDatabase();

		if ($code === MembershipImport::LIFE_FLAG)
		{
			$query = $db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_membership_types'))
				->where($db->quoteName('exempt_from_renewal') . ' = 1')
				->where($db->quoteName('published') . ' = 1')
				->order($db->quoteName('ordering') . ' ASC');

			$query->setLimit(1, 0);

			return (int) $db->setQuery($query)->loadResult();
		}

		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__wendfold_membership_types'))
			->where($db->quoteName('published') . ' = 1')
			->where('LOWER(' . $db->quoteName('import_code') . ') = LOWER(:importCode)')
			->bind(':importCode', $code);

		$query->setLimit(1, 0);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Resolve each write's membership type once.
	 *
	 * An unknown code is stored as 0. The profile update skips that column
	 * and membershipTypeWarnings() reports the row.
	 *
	 * @param   array  $writes  Planned profile writes.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function attachMembershipTypes(array $writes): array
	{
		$resolved = [];

		foreach ($writes as $index => $write)
		{
			$row  = $write['row'] ?? [];
			$code = trim((string) ($row['membership_type'] ?? ''));
			$id   = 0;

			if (!empty($row['has_type']) && $code !== '')
			{
				if (!\array_key_exists($code, $resolved))
				{
					$resolved[$code] = $this->membershipTypeIdForImport($code);
				}

				$id = $resolved[$code];
			}

			$writes[$index]['membership_type_id'] = $id;
		}

		return $writes;
	}

	/**
	 * Per-row warnings for membership codes that did not match a type.
	 *
	 * @param   array  $writes  Writes from attachMembershipTypes().
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	private function membershipTypeWarnings(array $writes): array
	{
		$rows     = [];
		$resolved = [];

		foreach ($writes as $write)
		{
			$row = $write['row'] ?? [];
			$rows[] = $row;
			$code = trim((string) ($row['membership_type'] ?? ''));

			if (!empty($row['has_type']) && $code !== '')
			{
				$resolved[$code] = (int) ($write['membership_type_id'] ?? 0);
			}
		}

		$messages = [];

		foreach (MembershipImport::unresolvedRows($rows, $resolved) as $warning)
		{
			$messages[] = Text::sprintf(
				'COM_WENDFOLD_SYNC_ROW_WARNING',
				$warning['line'],
				Text::sprintf(
					'COM_WENDFOLD_SYNC_WARN_UNKNOWN_MEMBERSHIP',
					$this->safeSprintfValue($warning['label'])
				)
			);
		}

		return $messages;
	}

	/**
	 * Write one matched profile inside the open import transaction.
	 *
	 * Blank CSV cells are not applied, so a partial row cannot wipe stored values.
	 * An unknown membership type is not written. The import result warns for that row.
	 *
	 * @param   object   $profile           Existing profile row.
	 * @param   array    $row               Validated CSV row.
	 * @param   boolean  $mismatch          True when the chapter value differs.
	 * @param   integer  $membershipTypeId  Resolved type id, or 0 when unknown.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function writeCsvProfileUpdate(object $profile, array $row, bool $mismatch, int $membershipTypeId): void
	{
		$db        = $this->getDatabase();
		$profileId = (int) $profile->id;
		$update    = $db->createQuery()
			->update($db->quoteName('#__wendfold_profiles'))
			->where($db->quoteName('id') . ' = :profileId')
			->bind(':profileId', $profileId, ParameterType::INTEGER);

		$update->set($db->quoteName('national_sync_date') . ' = NOW()');

		if (!empty($row['has_expiry']))
		{
			$expiryDate = (string) $row['expiry_date'];
			$update->set($db->quoteName('renewal_date') . ' = :expiryDate')
				->bind(':expiryDate', $expiryDate);
		}

		if ($row['national_verified'] !== null)
		{
			$verified = (int) $row['national_verified'];
			$update->set($db->quoteName('national_verified') . ' = :verified')
				->bind(':verified', $verified, ParameterType::INTEGER);
			$verifiedOn = SiteTime::fromApplication()->today();
			$update->set($db->quoteName('national_verified_date') . ' = :verifiedOn')
				->bind(':verifiedOn', $verifiedOn);
		}

		if ($membershipTypeId > 0)
		{
			$update->set($db->quoteName('membership_type_id') . ' = :membershipTypeId')
				->bind(':membershipTypeId', $membershipTypeId, ParameterType::INTEGER);
		}

		if (!empty($row['has_chapter']))
		{
			$chapter = (string) $row['chapter'];
			$update->set($db->quoteName('chapter_on_file') . ' = :chapterOnFile')
				->bind(':chapterOnFile', $chapter);
		}

		$existingSince = trim((string) ($profile->member_since ?? ''));

		if (!empty($row['has_join']) && ($existingSince === '' || $existingSince === '0000-00-00'))
		{
			$joinDate = (string) $row['join_date'];
			$update->set($db->quoteName('member_since') . ' = :joinDate')
				->bind(':joinDate', $joinDate);
		}

		if ($mismatch)
		{
			$chapterFlag = 1;
			$update->set($db->quoteName('chapter_switch_flagged') . ' = :chapterFlag')
				->bind(':chapterFlag', $chapterFlag, ParameterType::INTEGER);
		}

		$db->setQuery($update)->execute();
	}

	/**
	 * Update the linked Joomla account name and email when those columns are present.
	 *
	 * Phone and postal address are validated for the preview only. The profile
	 * table has no general phone or mailing-address columns.
	 *
	 * @param   object  $profile  Existing profile row.
	 * @param   array   $row      Validated CSV row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function writeCsvUserUpdate(object $profile, array $row): void
	{
		$db     = $this->getDatabase();
		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- Mirrors the user_id database column.
		$userId = (int) $profile->user_id;

		if ($userId <= 0)
		{
			return;
		}

		if (!empty($row['has_name']))
		{
			$memberName = (string) $row['full_name'];
			$update     = $db->createQuery()
				->update($db->quoteName('#__users'))
				->set($db->quoteName('name') . ' = :memberName')
				->where($db->quoteName('id') . ' = :userId')
				->bind(':memberName', $memberName)
				->bind(':userId', $userId, ParameterType::INTEGER);

			$db->setQuery($update)->execute();
		}

		if (!empty($row['has_email']))
		{
			$email  = (string) $row['email'];
			$update = $db->createQuery()
				->update($db->quoteName('#__users'))
				->set($db->quoteName('email') . ' = :memberEmail')
				->where($db->quoteName('id') . ' = :emailUserId')
				->bind(':memberEmail', $email)
				->bind(':emailUserId', $userId, ParameterType::INTEGER);

			$db->setQuery($update)->execute();
		}
	}

	/**
	 * @param   array  $file             Uploaded file array.
	 * @param   array  $stats            Import counters.
	 * @param   array  $flaggedDetails   Sanitized chapter-switch notes.
	 * @param   array  $logNotes         Parser notes safe for storage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function insertCsvSyncLog(array $file, array $stats, array $flaggedDetails, array $logNotes): void
	{
		$db          = $this->getDatabase();
		$user        = Factory::getApplication()->getIdentity();
		$performedBy = $user ? (int) $user->id : 0;
		$syncType    = 1;
		$processed   = (int) $stats['processed'];
		$updated     = (int) $stats['updated'];
		$added       = (int) $stats['added'];
		$skipped     = (int) $stats['skipped'];
		$errored     = (int) $stats['errored'];
		$status      = 1;
		$filename    = $this->cleanCsvFilename((string) ($file['name'] ?? ''));
		$parts       = [];

		if ($flaggedDetails !== [])
		{
			$shown   = array_slice($flaggedDetails, 0, 30);
			$parts[] = 'Chapter-switch candidates (' . count($flaggedDetails) . '): ' . implode('; ', $shown);
		}

		if ($logNotes !== [])
		{
			$parts[] = implode(' | ', $logNotes);
		}

		$detail = implode(' || ', $parts);

		if (strlen($detail) > 4000)
		{
			$detail = substr($detail, 0, 4000);
		}

		$query = $db->createQuery()
			->insert($db->quoteName('#__wendfold_sync_log'))
			->columns([
				'sync_type',
				'sync_date',
				'performed_by',
				'records_processed',
				'records_updated',
				'records_added',
				'records_skipped',
				'records_errored',
				'filename',
				'status',
				'log_detail',
			])
			->values(':syncType, NOW(), :performedBy, :processed, :updated, :added, :skipped, :errored, :filename, :logStatus, :detail')
			->bind(':syncType', $syncType, ParameterType::INTEGER)
			->bind(':performedBy', $performedBy, ParameterType::INTEGER)
			->bind(':processed', $processed, ParameterType::INTEGER)
			->bind(':updated', $updated, ParameterType::INTEGER)
			->bind(':added', $added, ParameterType::INTEGER)
			->bind(':skipped', $skipped, ParameterType::INTEGER)
			->bind(':errored', $errored, ParameterType::INTEGER)
			->bind(':filename', $filename)
			->bind(':logStatus', $status, ParameterType::INTEGER)
			->bind(':detail', $detail);

		$db->setQuery($query)->execute();
	}

	/**
	 * @param   string  $name  Client-supplied file name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function cleanCsvFilename(string $name): string
	{
		$name = basename(str_replace(["\0", '/', '\\'], '', $name));
		$name = preg_replace('/[^A-Za-z0-9._ -]+/', '', $name) ?? '';
		$name = trim($name);

		if ($name === '')
		{
			return 'import.csv';
		}

		return substr($name, 0, 255);
	}

}
