<?php

/**
 * Wendfold - New meeting audience and RSVP choices.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MeetingSaveInput;
use Wendfold\Component\Wendfold\Administrator\Model\EventModel;
use Wendfold\Tests\Double\EventApplication;

require_once __DIR__ . '/support/EventSaveDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php';
require_once __DIR__ . '/support/FixtureEventModel.php';

/**
 * A posted audience is stored as sent. An omitted audience uses the meeting type default.
 */
final class MeetingSaveInputTest extends TestCase
{
	protected function tearDown(): void
	{
		Factory::$application = null;

		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: int, 1: int, 2: int}>
	 */
	public static function omittedFieldProvider(): array
	{
		return [
			'standard meeting' => [0, 2, 1],
			'chapter meeting' => [1, 2, 1],
			'auxiliary' => [2, 2, 1],
			'officers meeting' => [3, 2, 1],
			'road captain meeting' => [4, 2, 1],
			'unknown subtype' => [5, 2, 1],
			'negative subtype' => [-1, 2, 1],
		];
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function meetingSubtypeProvider(): array
	{
		return [
			'standard meeting' => [0],
			'chapter meeting' => [1],
			'auxiliary' => [2],
			'officers meeting' => [3],
			'road captain meeting' => [4],
			'unknown subtype' => [5],
			'negative subtype' => [-1],
		];
	}

	/**
	 * The save data may already say All Visitors. Without a posted audience, the subtype default wins.
	 */
	#[DataProvider('omittedFieldProvider')]
	public function testOmittedAudienceUsesTheSubtypeDefault(int $subtype, int $scope, int $rsvp): void
	{
		$saved = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			[
				'title' => 'Chapter night',
				'audience_scope' => 1,
				'allow_rsvp' => 1,
			],
			['title' => 'Chapter night']
		);

		self::assertSame($scope, $saved['audience_scope']);
		self::assertSame($rsvp, $saved['allow_rsvp']);
		self::assertSame('Chapter night', $saved['title']);
	}

	/**
	 * All Visitors posted as an integer or as the form's string is stored as 1.
	 */
	#[DataProvider('meetingSubtypeProvider')]
	public function testPostedAllVisitorsIsKept(int $subtype): void
	{
		foreach ([1, '1'] as $posted)
		{
			$saved = MeetingSaveInput::applyNewMeetingChoices(
				MeetingSaveInput::meetingDefaults(3, 0),
				['audience_scope' => 9, 'allow_rsvp' => 0],
				['audience_scope' => $posted, 'allow_rsvp' => '1']
			);

			self::assertSame(1, $saved['audience_scope']);
			self::assertSame(1, $saved['allow_rsvp']);
		}
	}

	public function testPostedAllowedAudiencesAreKept(): void
	{
		foreach (MeetingSaveInput::ALLOWED_SCOPES as $scope)
		{
			$saved = MeetingSaveInput::applyNewMeetingChoices(
				MeetingSaveInput::meetingDefaults(3, 0),
				[],
				['audience_scope' => (string) $scope]
			);

			self::assertSame($scope, $saved['audience_scope']);
			self::assertSame(1, $saved['allow_rsvp']);
		}
	}

	public function testInvalidPostedAudienceUsesTheSubtypeDefault(): void
	{
		foreach ([0, 5, 8, -1, '9', '', '1.5', '01', true, null, [1]] as $posted)
		{
			$saved = MeetingSaveInput::applyNewMeetingChoices(
				MeetingSaveInput::meetingDefaults(3, 0),
				['audience_scope' => 1],
				['audience_scope' => $posted]
			);

			self::assertSame(2, $saved['audience_scope']);
		}
	}

	public function testPostedRsvpIsKeptWhenTheSuggestionDiffers(): void
	{
		$officersOn = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			[],
			['audience_scope' => '1', 'allow_rsvp' => '1']
		);
		$officersOff = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			[],
			['allow_rsvp' => 0]
		);
		$chapterOff = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			[],
			['allow_rsvp' => '0']
		);

		$visitorsOnly = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			['allow_rsvp' => 1],
			['audience_scope' => 1]
		);

		self::assertSame(1, $officersOn['audience_scope']);
		self::assertSame(1, $officersOn['allow_rsvp']);
		self::assertSame(2, $officersOff['audience_scope']);
		self::assertSame(0, $officersOff['allow_rsvp']);
		self::assertSame(2, $chapterOff['audience_scope']);
		self::assertSame(0, $chapterOff['allow_rsvp']);
		self::assertSame(1, $visitorsOnly['audience_scope']);
		self::assertSame(1, $visitorsOnly['allow_rsvp']);
	}

	public function testInvalidPostedRsvpUsesTheSubtypeDefault(): void
	{
		$officers = MeetingSaveInput::applyNewMeetingChoices(MeetingSaveInput::meetingDefaults(3, 0), [], ['allow_rsvp' => '2']);
		$chapter = MeetingSaveInput::applyNewMeetingChoices(MeetingSaveInput::meetingDefaults(3, 0), [], ['allow_rsvp' => 'yes']);

		self::assertSame(1, $officers['allow_rsvp']);
		self::assertSame(2, $officers['audience_scope']);
		self::assertSame(1, $chapter['allow_rsvp']);
		self::assertSame(2, $chapter['audience_scope']);
	}

	/**
	 * Standard RSVP is workflow 1. The meeting suggestion is workflow 3.
	 */
	#[DataProvider('meetingSubtypeProvider')]
	public function testPostedStandardWorkflowIsKept(int $subtype): void
	{
		foreach ([1, '1'] as $posted)
		{
			$saved = MeetingSaveInput::applyNewMeetingChoices(
				MeetingSaveInput::meetingDefaults(3, 0),
				['workflow_mode' => 3],
				['workflow_mode' => $posted]
			);

			self::assertSame(1, $saved['workflow_mode']);
		}

		$prepared = MeetingSaveInput::prepareSaveData(
			['event_type' => 2, 'meeting_subtype' => $subtype, 'workflow_mode' => 3],
			['event_type' => 2, 'workflow_mode' => '1', 'audience_scope' => '1'],
			true,
			2,
			3,
			0
		);

		self::assertNotNull($prepared);
		self::assertSame(1, $prepared['workflow_mode']);
	}

	/**
	 * The save data may already say Standard RSVP. Without a posted workflow, the suggestion wins.
	 */
	#[DataProvider('meetingSubtypeProvider')]
	public function testOmittedWorkflowUsesTheSubtypeDefault(int $subtype): void
	{
		$saved = MeetingSaveInput::applyNewMeetingChoices(
			MeetingSaveInput::meetingDefaults(3, 0),
			['workflow_mode' => 1, 'title' => 'Chapter night'],
			['title' => 'Chapter night']
		);

		self::assertSame(3, $saved['workflow_mode']);
		self::assertSame('Chapter night', $saved['title']);

		$prepared = MeetingSaveInput::prepareSaveData(
			['event_type' => 2, 'meeting_subtype' => $subtype, 'workflow_mode' => 1, 'title' => 'Chapter night'],
			['event_type' => 2, 'title' => 'Chapter night'],
			true,
			2,
			3,
			0
		);

		self::assertNotNull($prepared);
		self::assertSame(3, $prepared['workflow_mode']);
	}

	public function testEditRejectsAnAudienceOutsideTheList(): void
	{
		foreach ([9, 0, 5, -1, '9', 'abc', '', '1.5', true, null, [1]] as $posted)
		{
			$saved = MeetingSaveInput::prepareSaveData(
				[
					'id' => 15,
					'event_type' => 2,
					'meeting_subtype' => 3,
					'audience_scope' => $posted,
					'title' => 'Board',
				],
				['audience_scope' => $posted],
				false,
				2,
				3,
				0
			);

			self::assertNull($saved);
		}
	}

	public function testEditKeepsAnAllowedAudience(): void
	{
		$saved = MeetingSaveInput::prepareSaveData(
			['id' => 15, 'event_type' => 2, 'audience_scope' => '3', 'title' => 'Board'],
			['audience_scope' => '3'],
			false,
			2,
			3,
			0
		);

		self::assertNotNull($saved);
		self::assertSame(3, $saved['audience_scope']);
		self::assertSame('Board', $saved['title']);
	}

	public function testNonMeetingRejectsAnAudienceOutsideTheList(): void
	{
		foreach ([9, 0, '9', 'abc', 'nope', true] as $posted)
		{
			$saved = MeetingSaveInput::prepareSaveData(
				['event_type' => 1, 'audience_scope' => $posted, 'title' => 'Sunday ride'],
				['audience_scope' => $posted],
				true,
				1,
				2,
				1
			);

			self::assertNull($saved);

			$edited = MeetingSaveInput::prepareSaveData(
				['id' => 4, 'event_type' => 1, 'audience_scope' => $posted, 'title' => 'Sunday ride'],
				['audience_scope' => $posted],
				false,
				1,
				2,
				1
			);

			self::assertNull($edited);
		}
	}

	public function testNonMeetingKeepsAnAllowedAudience(): void
	{
		$saved = MeetingSaveInput::prepareSaveData(
			['event_type' => 1, 'audience_scope' => '1', 'title' => 'Sunday ride'],
			['audience_scope' => '1'],
			true,
			1,
			2,
			1
		);

		self::assertNotNull($saved);
		self::assertSame(1, $saved['audience_scope']);
		self::assertSame('Sunday ride', $saved['title']);
	}

	/**
	 * Filtered data already holds the form default. The raw post is still blank.
	 */
	public function testBlankAudienceOnEditIsRefused(): void
	{
		$meeting = MeetingSaveInput::prepareSaveData(
			[
				'id' => 15,
				'event_type' => 2,
				'meeting_subtype' => 1,
				'audience_scope' => 1,
				'title' => 'Chapter night',
			],
			['audience_scope' => ''],
			false,
			2,
			3,
			0
		);
		$ride = MeetingSaveInput::prepareSaveData(
			[
				'id' => 8,
				'event_type' => 1,
				'audience_scope' => 1,
				'title' => 'Sunday ride',
			],
			['audience_scope' => ''],
			false,
			1,
			2,
			1
		);

		self::assertNull($meeting);
		self::assertNull($ride);
	}

	/**
	 * A new ride is not a meeting, so a blank audience is not replaced with a suggestion.
	 */
	public function testBlankAudienceOnANewNonMeetingIsRefused(): void
	{
		$saved = MeetingSaveInput::prepareSaveData(
			['event_type' => 1, 'audience_scope' => 1, 'title' => 'Sunday ride'],
			['audience_scope' => ''],
			true,
			1,
			2,
			1
		);

		self::assertNull($saved);
	}

	/**
	 * An array is not one of 1-4. The model records the field validation message.
	 */
	public function testArrayAudienceIsRefusedAndSetsTheMessage(): void
	{
		$saved = MeetingSaveInput::prepareSaveData(
			['id' => 4, 'event_type' => 1, 'audience_scope' => 1, 'title' => 'Sunday ride'],
			['audience_scope' => ['2']],
			false,
			1,
			2,
			1
		);

		self::assertNull($saved);

		Factory::$application = new EventApplication(['audience_scope' => ['2']]);
		$model = new FixtureEventModel;
		$stored = $model->save([
			'id' => 4,
			'event_type_id' => 1,
			'audience_scope' => 1,
			'title' => 'Sunday ride',
			]
		);

		self::assertFalse($stored);
		self::assertSame('Invalid field: Audience', $model->getError());
	}

	/**
	 * No audience key in the post. The subtype suggestion is stored.
	 */
	#[DataProvider('omittedFieldProvider')]
	public function testOmittedAudienceOnANewMeetingUsesTheSubtypeDefault(int $subtype, int $scope, int $rsvp): void
	{
		$saved = MeetingSaveInput::prepareSaveData(
			[
				'event_type' => 2,
				'meeting_subtype' => $subtype,
				'audience_scope' => 1,
				'allow_rsvp' => 1,
				'title' => 'Chapter night',
			],
			['title' => 'Chapter night'],
			true,
			2,
			3,
			0
		);

		self::assertNotNull($saved);
		self::assertSame($scope, $saved['audience_scope']);
		self::assertSame($rsvp, $saved['allow_rsvp']);
		self::assertSame('Chapter night', $saved['title']);
	}

	/**
	 * Form validation runs before save. An omitted audience is not a required
	 * field, so validation passes and the subtype suggestion is applied.
	 */
	#[DataProvider('omittedFieldProvider')]
	public function testOmittedAudiencePassesFormValidationAndUsesTheSubtypeDefault(int $subtype, int $scope, int $rsvp): void
	{
		$posted = [
			'title' => 'Chapter night',
			'event_date' => '2026-11-02',
			'event_type_id' => 2,
		];
		$model = new EventModel;
		$validated = $model->validate(null, $posted);

		self::assertIsArray(
			$validated,
			'Form validation rejected an omitted audience: ' . (string) $model->getError()
		);
		self::assertArrayNotHasKey('audience_scope', $validated);

		$saved = MeetingSaveInput::prepareSaveData($validated, $posted, true, 2, 3, 0);

		self::assertNotNull($saved);
		self::assertSame($scope, $saved['audience_scope']);
		self::assertSame($rsvp, $saved['allow_rsvp']);
	}

	/**
	 * Leaving the audience out of an edit is valid. The value already stored stays.
	 */
	public function testOmittedAudienceOnEditPassesFormValidationAndKeepsTheStoredValue(): void
	{
		$posted = [
			'id' => 15,
			'title' => 'Chapter night',
			'event_date' => '2026-11-02',
			'event_type_id' => 2,
		];
		$model = new EventModel;
		$validated = $model->validate(null, $posted);

		self::assertIsArray($validated, (string) $model->getError());
		self::assertArrayNotHasKey('audience_scope', $validated);

		$untouched = MeetingSaveInput::prepareSaveData($validated, $posted, false, 2, 3, 0);

		self::assertNotNull($untouched);
		self::assertArrayNotHasKey('audience_scope', $untouched);

		$validated['audience_scope'] = 3;
		$saved = MeetingSaveInput::prepareSaveData($validated, $posted, false, 2, 3, 0);

		self::assertNotNull($saved);
		self::assertSame(3, $saved['audience_scope']);
	}
}
