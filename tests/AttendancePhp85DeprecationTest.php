<?php

/**
 * Wendfold - Issue #111 attendance PHP 8.5 deprecation locks.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\AttendanceMemberLabel;

/**
 * Profiles without a Joomla user must not pass null into htmlspecialchars().
 *
 * @since  1.0.0
 */
final class AttendancePhp85DeprecationTest extends TestCase
{
	/**
	 * Reproduce the PHP 8.1+ deprecation that flooded the attendance log.
	 *
	 * Joomla templates are weak-typed. Under weak typing, htmlspecialchars(null)
	 * raises E_DEPRECATED on PHP 8.1+. This test file uses strict_types, so the
	 * call goes through a weak-typed shim that mirrors those templates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHtmlspecialcharsRejectsNullWithDeprecation(): void
	{
		if (\PHP_VERSION_ID < 80100)
		{
			self::markTestSkipped('Null-to-string deprecations start in PHP 8.1.');
		}

		$sawDeprecation = include __DIR__ . '/support/htmlspecialchars_null.php';

		self::assertTrue(
			$sawDeprecation === true,
			'htmlspecialchars(null) must raise E_DEPRECATED so the attendance fix stays justified.'
		);
	}

	/**
	 * Casting null to string is the safe replacement used by the attendance modal.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCastingNullNameAvoidsHtmlspecialcharsDeprecation(): void
	{
		$sawDeprecation = false;

		set_error_handler(
			static function (int $errno, string $errstr) use (&$sawDeprecation): bool {
				if ($errno === \E_DEPRECATED && str_contains($errstr, 'htmlspecialchars'))
				{
					$sawDeprecation = true;
				}

				return true;
			}
		);

		try
		{
			$name = null;
			htmlspecialchars((string) ($name ?? ''), \ENT_QUOTES, 'UTF-8');
		}
		finally
		{
			restore_error_handler();
		}

		self::assertFalse($sawDeprecation);
	}

	/**
	 * The attendance template must not query the database or escape a raw null name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAttendanceTemplateUsesAvailableMembersAndStringCast(): void
	{
		$template = self::read('com_wendfold/admin/tmpl/attendance/default.php');
		$model = self::read('com_wendfold/admin/src/Model/AttendanceModel.php');
		$view = self::read('com_wendfold/admin/src/View/Attendance/HtmlView.php');

		self::assertStringNotContainsString("get('DatabaseDriver')", $template);
		self::assertStringNotContainsString('htmlspecialchars($m->name', $template);
		self::assertStringNotContainsString('htmlspecialchars($member->name,', $template);
		self::assertStringContainsString('availableMembers', $template);
		self::assertStringContainsString("(string) (\$member->name ?? '')", $template);
		self::assertStringContainsString('function getAvailableMembers(): array', $model);
		self::assertStringContainsString('AttendanceMemberLabel::resolve', $model);
		self::assertStringContainsString('preferred_name', $model);
		self::assertStringContainsString('member_number', $model);
		self::assertStringContainsString('$model->getAvailableMembers()', $view);
	}

	/**
	 * Profiles without a Joomla user get preferred name, member number, or Member #id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAvailableMemberLabelFallsBackWithoutBlankEntries(): void
	{
		require_once __DIR__ . '/support/EventSaveDoubles.php';

		$adminIni = self::read('com_wendfold/admin/language/en-GB/com_wendfold.ini');
		$helper = self::read('com_wendfold/admin/src/Helper/AttendanceMemberLabel.php');
		$template = self::read('com_wendfold/admin/tmpl/attendance/default.php');

		self::assertStringContainsString(
			'COM_WENDFOLD_ATTENDANCE_MEMBER_FALLBACK="Member #%d"',
			$adminIni
		);
		self::assertStringContainsString(
			"Text::sprintf('COM_WENDFOLD_ATTENDANCE_MEMBER_FALLBACK'",
			$helper
		);
		self::assertStringContainsString(
			"htmlspecialchars((string) (\$member->name ?? ''), ENT_QUOTES, 'UTF-8')",
			$template
		);

		self::assertSame('Ada Lovelace', AttendanceMemberLabel::resolve('Ada Lovelace', 'Ignore', '99', 7));
		self::assertSame('Road Name', AttendanceMemberLabel::resolve(null, 'Road Name', '99', 7));
		self::assertSame('Road Name', AttendanceMemberLabel::resolve('', 'Road Name', '99', 7));
		self::assertSame('42', AttendanceMemberLabel::resolve(null, null, '42', 7));
		self::assertSame('42', AttendanceMemberLabel::resolve('', '  ', '42', 7));
		self::assertSame('Member #7', AttendanceMemberLabel::resolve(null, null, null, 7));
		self::assertSame('Member #7', AttendanceMemberLabel::resolve('', '', '', 7));
		self::assertNotSame('', AttendanceMemberLabel::resolve(null, null, null, 7));
	}

	/**
	 * Site roll-call templates already coerce member names before escaping.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteRollCallTemplatesCastMemberNames(): void
	{
		$meeting = self::read('com_wendfold/site/tmpl/meetingattendance/default.php');
		$rc = self::read('com_wendfold/site/tmpl/rcattendance/default.php');
		$checkin = self::read('com_wendfold/site/tmpl/checkin/default.php');

		self::assertDoesNotMatchRegularExpression(
			'/htmlspecialchars\(\s*\$[a-zA-Z_][a-zA-Z0-9_]*->(?:name|member_name)\s*,/',
			$meeting
		);
		self::assertDoesNotMatchRegularExpression(
			'/htmlspecialchars\(\s*\$[a-zA-Z_][a-zA-Z0-9_]*->(?:name|member_name)\s*,/',
			$rc
		);
		self::assertDoesNotMatchRegularExpression(
			'/htmlspecialchars\(\s*\$[a-zA-Z_][a-zA-Z0-9_]*->(?:name|member_name)\s*,/',
			$checkin
		);
		self::assertStringContainsString("(string) (\$member->member_name ?? '')", $meeting);
		self::assertStringContainsString("(string) (\$a->member_name ?? '')", $rc);
		self::assertStringContainsString('htmlspecialchars((string) $a->member_name', $checkin);
	}

	/**
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function read(string $relative): string
	{
		$contents = file_get_contents(dirname(__DIR__) . '/' . $relative);

		if ($contents === false)
		{
			self::fail('Could not read ' . $relative);
		}

		return $contents;
	}
}
