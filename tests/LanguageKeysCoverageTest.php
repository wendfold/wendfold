<?php

/**
 * Wendfold - every used package language key must exist in en-GB ini files.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/scripts/ci/LanguageKeyScanner.php';

/**
 * Regression guard for missing Text::_ / Text::plural / XML language keys.
 *
 * @since  1.0.0
 */
final class LanguageKeysCoverageTest extends TestCase
{
	/**
	 * Used package keys are present in the en-GB ini files.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUsedPackageKeysExistInEnGbIniFiles(): void
	{
		$scanner = new \LanguageKeyScanner(dirname(__DIR__));
		$missing = $scanner->missingKeys();

		self::assertSame(
			[],
			$missing,
			"Missing en-GB language keys:\n - " . implode("\n - ", $missing)
		);
	}

	/**
	 * Each en-GB ini defines every key at most once.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEnGbIniFilesHaveNoDuplicateKeys(): void
	{
		$scanner = new \LanguageKeyScanner(dirname(__DIR__));
		$duplicates = $scanner->duplicateKeys();

		self::assertSame(
			[],
			$duplicates,
			"Duplicate en-GB language keys:\n - " . implode("\n - ", $duplicates)
		);
	}

	/**
	 * Publish-failure plural keys required by ListController are defined.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFailedPublishingPluralKeysAreDefined(): void
	{
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);

		self::assertStringContainsString(
			'COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING_1=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_OFFICERS_N_ITEMS_FAILED_PUBLISHING=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_OFFICERS_N_ITEMS_FAILED_PUBLISHING_1=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_NO_ITEMS_NEEDED_CHANGING=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_OFFICERS_NO_ITEMS_NEEDED_CHANGING=',
			$adminIni
		);
	}

	/**
	 * Event status-guard keys from the row-lock attendance work are defined.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventStatusGuardKeysAreDefined(): void
	{
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$siteIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);

		self::assertStringContainsString(
			'COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS=',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_RCATTENDANCE_EVENT_ALREADY_CLOSED=',
			$siteIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_RCATTENDANCE_CANCEL_HAS_CHECKINS=',
			$siteIni
		);
	}

	/**
	 * Officer role seeds rendered on the site officers page exist in site en-GB.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteOfficerRoleKeysExistForLookupTitle(): void
	{
		$siteIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);
		$officers = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/officers/default.php'
		);
		$lookup = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php'
		);

		self::assertStringContainsString('LookupTitle::display', $officers);
		self::assertStringContainsString('LanguageHelper::parseIniFile', $lookup);
		self::assertStringContainsString('hasKey', $lookup);
		self::assertStringContainsString('JPATH_ADMINISTRATOR', $lookup);
		self::assertDoesNotMatchRegularExpression('/->load\s*\(/', $lookup);

		$roles = [
			'COM_WENDFOLD_OFFICER_ROLE_DIRECTOR',
			'COM_WENDFOLD_OFFICER_ROLE_ASSISTANT_DIRECTOR',
			'COM_WENDFOLD_OFFICER_ROLE_SECRETARY',
			'COM_WENDFOLD_OFFICER_ROLE_TREASURER',
			'COM_WENDFOLD_OFFICER_ROLE_ACTIVITIES',
			'COM_WENDFOLD_OFFICER_ROLE_SAFETY',
			'COM_WENDFOLD_OFFICER_ROLE_ROAD_CAPTAIN',
			'COM_WENDFOLD_OFFICER_ROLE_CHAPTER_MANAGER',
			'COM_WENDFOLD_OFFICER_ROLE_VOLUNTEER',
			'COM_WENDFOLD_OFFICER_ROLE_SERGEANT',
			'COM_WENDFOLD_OFFICER_ROLE_AUXILIARY',
			'COM_WENDFOLD_OFFICER_ROLE_MEMBERSHIP',
			'COM_WENDFOLD_OFFICER_ROLE_EDITOR',
			'COM_WENDFOLD_OFFICER_ROLE_PHOTOGRAPHER',
			'COM_WENDFOLD_OFFICER_ROLE_WEBMASTER',
			'COM_WENDFOLD_OFFICER_ROLE_HISTORIAN',
			'COM_WENDFOLD_OFFICER_ROLE_OTHER',
		];

		foreach ($roles as $key)
		{
			self::assertStringContainsString($key . '=', $siteIni, $key);
		}
	}

	/**
	 * Failed plurals exist for every list batch state.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFailedBatchPluralKeysExistForEveryState(): void
	{
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);

		foreach ([
			'COM_WENDFOLD_N_ITEMS_FAILED_UNPUBLISHING',
			'COM_WENDFOLD_N_ITEMS_FAILED_ARCHIVING',
			'COM_WENDFOLD_N_ITEMS_FAILED_TRASHING',
			'COM_WENDFOLD_N_ITEMS_FAILED_DELETING',
		] as $key)
		{
			self::assertStringContainsString($key . '=', $adminIni, $key);
			self::assertStringContainsString($key . '_1=', $adminIni, $key . '_1');
		}
	}

	/**
	 * Event cancellation notice keys exist in admin and site en-GB.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventCancelNoticeKeysAreDefined(): void
	{
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$siteIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);

		foreach ([
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_SUBJECT',
			'COM_WENDFOLD_EMAIL_EVENT_CANCEL_BODY',
		] as $key)
		{
			self::assertStringContainsString($key . '=', $adminIni, 'admin ' . $key);
			self::assertStringContainsString($key . '=', $siteIni, 'site ' . $key);
		}
	}
}
