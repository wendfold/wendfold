<?php

/**
 * Wendfold - site attendance event published-state gate.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\AttendanceEventAccess;

/**
 * Locks published/archived allow, unpublished message, and invalid for trash/missing
 * for both roll-call and RC attendance views and save tasks.
 *
 * @since  1.0.0
 */
final class AttendanceEventAccessTest extends TestCase
{
	/**
	 * @return array<string, array{0: int|null, 1: bool, 2: string|null}>
	 */
	public static function publishedStateProvider(): array
	{
		return [
			'published' => [1, true, null],
			'archived' => [2, true, null],
			'unpublished' => [0, false, AttendanceEventAccess::KEY_NOT_PUBLISHED],
			'trashed' => [-2, false, AttendanceEventAccess::KEY_INVALID],
			'missing' => [null, false, AttendanceEventAccess::KEY_INVALID],
			'unknown' => [99, false, AttendanceEventAccess::KEY_INVALID],
		];
	}

	/**
	 * Published and archived open attendance; unpublished, trash, and missing do not.
	 *
	 * @param   integer|null  $published  Event published value.
	 * @param   boolean       $allowed    Whether attendance may open/save.
	 * @param   string|null   $denialKey  Expected language key when blocked.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('publishedStateProvider')]
	public function testAllowsAndDenialKey(?int $published, bool $allowed, ?string $denialKey): void
	{
		self::assertSame($allowed, AttendanceEventAccess::allows($published));
		self::assertSame($denialKey, AttendanceEventAccess::denialKey($published));
		self::assertSame(
			$published === 0 || $published === 1 || $published === 2,
			AttendanceEventAccess::isRouterResolvable($published)
		);
	}

	/**
	 * Allowed states are exactly published and archived.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllowedStatesArePublishedAndArchived(): void
	{
		self::assertSame([1, 2], AttendanceEventAccess::allowedStates());
	}

	/**
	 * View display and save tasks share the same denial keys for every state.
	 *
	 * @param   integer|null  $published  Event published value.
	 * @param   boolean       $allowed    Whether attendance may open/save.
	 * @param   string|null   $denialKey  Expected language key when blocked.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('publishedStateProvider')]
	public function testMeetingViewAndSaveShareTheSameGate(?int $published, bool $allowed, ?string $denialKey): void
	{
		$gate = new AttendanceEventAccessGateDouble($published);

		self::assertSame($denialKey, $gate->viewDenial('meeting'));
		self::assertSame($denialKey, $gate->saveDenial('meeting', 'add'));
		self::assertSame($denialKey, $gate->saveDenial('meeting', 'remove'));
		self::assertSame($allowed, $gate->viewDenial('meeting') === null);
		self::assertSame($allowed, $gate->saveDenial('meeting', 'add') === null);
	}

	/**
	 * RC view and save tasks use the same gate as meeting attendance.
	 *
	 * @param   integer|null  $published  Event published value.
	 * @param   boolean       $allowed    Whether attendance may open/save.
	 * @param   string|null   $denialKey  Expected language key when blocked.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('publishedStateProvider')]
	public function testRcViewAndSaveShareTheSameGate(?int $published, bool $allowed, ?string $denialKey): void
	{
		$gate = new AttendanceEventAccessGateDouble($published);

		self::assertSame($denialKey, $gate->viewDenial('rc'));
		self::assertSame($denialKey, $gate->saveDenial('rc', 'add'));
		self::assertSame($denialKey, $gate->saveDenial('rc', 'remove'));
		self::assertSame($allowed, $gate->viewDenial('rc') === null);
		self::assertSame($allowed, $gate->saveDenial('rc', 'add') === null);
	}

	/**
	 * Both attendance views and both save controllers call the shared model gate.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testViewsAndControllersCallAttendanceEventDenialKey(): void
	{
		$paths = [
			'com_wendfold/site/src/View/Meetingattendance/HtmlView.php',
			'com_wendfold/site/src/View/Rcattendance/HtmlView.php',
			'com_wendfold/site/src/Controller/MeetingattendanceController.php',
			'com_wendfold/site/src/Controller/RcattendanceController.php',
			'com_wendfold/site/src/Model/MeetingattendanceModel.php',
			'com_wendfold/site/src/Model/RcattendanceModel.php',
		];

		foreach ($paths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			self::assertNotFalse($source, $relative);
			self::assertStringContainsString('attendanceEventDenialKey', $source, $relative);
		}

		$modelPaths = [
			'com_wendfold/site/src/Model/MeetingattendanceModel.php',
			'com_wendfold/site/src/Model/RcattendanceModel.php',
		];

		foreach ($modelPaths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			self::assertNotFalse($source, $relative);
			self::assertStringContainsString('AttendanceEventAccess::TRASHED', $source, $relative);
			self::assertStringContainsString("e.published') . ' <> :trashed", $source, $relative);
			self::assertStringContainsString(
				'AttendanceEventAccess::denialKeyForEvent',
				$source,
				$relative
			);
		}

		$controllerPaths = [
			'com_wendfold/site/src/Controller/MeetingattendanceController.php',
			'com_wendfold/site/src/Controller/RcattendanceController.php',
		];

		foreach ($controllerPaths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			self::assertNotFalse($source, $relative);
			self::assertMatchesRegularExpression(
				'/attendanceEventDenialKey\([^)]+\).*isAuthorized/s',
				$source,
				$relative . ' must deny unpublished before ACL'
			);
		}
	}

	/**
	 * Site and admin language files define the unpublished message.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLanguageFilesDefineNotPublishedMessage(): void
	{
		$needle = 'COM_WENDFOLD_EVENT_NOT_PUBLISHED="This event is not published."';
		$languagePaths = [
			'com_wendfold/language/en-GB/com_wendfold.ini',
			'com_wendfold/admin/language/en-GB/com_wendfold.ini',
		];

		foreach ($languagePaths as $relative)
		{
			$source = file_get_contents(dirname(__DIR__) . '/' . $relative);
			self::assertNotFalse($source, $relative);
			self::assertStringContainsString($needle, $source, $relative);
		}
	}
}

/**
 * Mirrors the shared published-state check used by both attendance views and save tasks.
 *
 * @since  1.0.0
 */
final class AttendanceEventAccessGateDouble
{
	/**
	 * Event published column, or null when the id is missing.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $published;

	/**
	 * @param   integer|null  $published  Event published value.
	 *
	 * @since   1.0.0
	 */
	public function __construct(?int $published)
	{
		$this->published = $published;
	}

	/**
	 * Denial key for opening a view, or null when the page may render.
	 *
	 * @param   string  $view  meeting or rc.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function viewDenial(string $view): ?string
	{
		unset($view);

		return AttendanceEventAccess::denialKey($this->published);
	}

	/**
	 * Denial key for a save task, or null when the POST may continue.
	 *
	 * @param   string  $view  meeting or rc.
	 * @param   string  $task  Controller task name.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public function saveDenial(string $view, string $task): ?string
	{
		unset($view, $task);

		return AttendanceEventAccess::denialKey($this->published);
	}
}
