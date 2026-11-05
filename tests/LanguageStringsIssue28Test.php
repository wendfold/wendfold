<?php

/**
 * Wendfold - Issue #28 language keys and unknown-core-key cleanup.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Member awards and sync last-sync labels must use component keys.
 *
 * @since  1.0.0
 */
final class LanguageStringsIssue28Test extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberAwardsAndSyncUseComponentLanguageKeys(): void
	{
		$adminIni = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$memberAwards = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/memberawards/default.php'
		);
		$sync = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/sync/default.php'
		);
		$attendance = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/attendance/default.php'
		);

		self::assertStringContainsString(
			'COM_WENDFOLD_MEMBERAWARDS_COL_ACTIONS="Actions"',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_SYNC_LOG_NOTES="Notes"',
			$adminIni
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_ATTENDANCE_METHOD_QR="QR Scan"',
			$adminIni
		);
		self::assertStringContainsString(
			"Text::_('COM_WENDFOLD_MEMBERAWARDS_COL_ACTIONS')",
			$memberAwards
		);
		self::assertStringNotContainsString("Text::_('JACTION')", $memberAwards);
		self::assertStringContainsString(
			"Text::_('COM_WENDFOLD_SYNC_LOG_NOTES')",
			$sync
		);
		self::assertStringNotContainsString("Text::_('JNOTES')", $sync);
		self::assertStringContainsString(
			"Text::_('COM_WENDFOLD_ATTENDANCE_METHOD_QR')",
			$attendance
		);
		self::assertStringNotContainsString("'QR Scan'", $attendance);
	}
}
