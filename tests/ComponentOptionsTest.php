<?php

/**
 * Wendfold - Component option defaults.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ComponentOptions.php';

/**
 * Group ids, dues, waiver version, and reminder windows, without a database.
 *
 * @since  1.0.0
 */
final class ComponentOptionsTest extends TestCase
{
	/**
	 * A saved group id is kept. An empty option receives the found id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyGroupIdsAreStoredAndSavedIdsStay(): void
	{
		$resolved = [];

		foreach (ComponentOptions::groups() as $group)
		{
			$resolved[$group['param']] = 10;
		}

		$resolved['group_officer'] = 6;

		$stored = [
			'chapter_name'  => 'North',
			'group_member'  => '4',
			'group_officer' => 0,
		];

		$write = ComponentOptions::groupIdsToStore($stored, $resolved);

		$this->assertArrayNotHasKey('group_member', $write);
		$this->assertSame(6, $write['group_officer']);
		$this->assertSame(10, $write['group_webmaster']);

		$json = ComponentOptions::mergeGroupParams((string) json_encode($stored), $resolved);
		$this->assertNotNull($json);
		$saved = json_decode((string) $json, true);
		$this->assertSame('North', $saved['chapter_name']);
		$this->assertSame('4', $saved['group_member']);
		$this->assertSame(6, $saved['group_officer']);
		$this->assertNull(ComponentOptions::mergeGroupParams((string) $json, $resolved));
	}

	/**
	 * Parents are listed before the groups nested under them.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupSeedListsParentsFirst(): void
	{
		$seen = [];

		foreach (ComponentOptions::groups() as $group)
		{
			if ($group['parent_param'] !== '')
			{
				$this->assertArrayHasKey($group['parent_param'], $seen);
			}

			$seen[$group['param']] = $group['title'];
		}

		$this->assertSame(
			[
				'group_member',
				'group_road_captain',
				'group_activities',
				'group_membership',
				'group_officer',
				'group_webmaster',
			],
			array_keys($seen)
		);
		$this->assertSame('Registered', ComponentOptions::groups()[0]['parent_title']);
	}

	/**
	 * Reminder day counts come from the options. The profile columns stay fixed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReminderWindowsUseConfiguredDays(): void
	{
		$windows = ComponentOptions::reminderWindows(45, 14);

		$this->assertSame(45, $windows[0]['days']);
		$this->assertSame('reminder_60_sent', $windows[0]['column']);
		$this->assertSame(14, $windows[1]['days']);
		$this->assertSame('reminder_30_sent', $windows[1]['column']);
		$this->assertSame(60, ComponentOptions::reminderWindows(0, 400)[0]['days']);
		$this->assertSame(30, ComponentOptions::reminderWindows('nope', '')[1]['days']);
	}

	/**
	 * The waiver option fills a blank value only on a new profile.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testWaiverDefaultAppliesOnlyToNewRecords(): void
	{
		$this->assertSame('2027', ComponentOptions::waiverVersionForRecord(0, '', '2027'));
		$this->assertSame('2026', ComponentOptions::waiverVersionForRecord(0, '  ', ''));
		$this->assertSame('2019', ComponentOptions::waiverVersionForRecord(0, ' 2019 ', '2027'));
		$this->assertSame('', ComponentOptions::waiverVersionForRecord(8, '', '2027'));
		$this->assertSame('2019', ComponentOptions::waiverVersionForRecord(8, '2019', '2027'));
	}

	/**
	 * Overlapping or reversed reminder days fall back to 60 and 30.
	 *
	 * A member who matches both windows receives only the nearer reminder,
	 * and both sent columns are marked.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReminderPairRejectsOverlapAndSendsOneEmail(): void
	{
		$overlap = ComponentOptions::reminderWindows(20, 15);
		$reversed = ComponentOptions::reminderWindows(30, 60);
		$equal = ComponentOptions::reminderWindows(40, 40);
		$touching = ComponentOptions::reminderWindows(40, 30);
		$separated = ComponentOptions::reminderWindows(41, 30);

		$this->assertSame(60, $overlap[0]['days']);
		$this->assertSame(30, $overlap[1]['days']);
		$this->assertSame(60, $reversed[0]['days']);
		$this->assertSame(30, $reversed[1]['days']);
		$this->assertSame(60, $equal[0]['days']);
		$this->assertSame(30, $equal[1]['days']);
		$this->assertSame(60, $touching[0]['days']);
		$this->assertSame(41, $separated[0]['days']);
		$this->assertSame(30, $separated[1]['days']);

		$windows = ComponentOptions::reminderWindows(60, 30);
		$choices = ComponentOptions::reminderSelection(
			$windows,
			[
				4 => [0, 1],
				5 => [0],
				6 => [1],
			]
		);

		$this->assertSame(1, $choices[4]['index']);
		$this->assertSame(30, $choices[4]['days']);
		$this->assertSame(['reminder_60_sent', 'reminder_30_sent'], $choices[4]['columns']);
		$this->assertSame(60, $choices[5]['days']);
		$this->assertSame(['reminder_60_sent'], $choices[5]['columns']);
		$this->assertSame(30, $choices[6]['days']);
		$this->assertSame(['reminder_30_sent'], $choices[6]['columns']);
	}

	/**
	 * Dues amount and waiver version fall back to the install defaults.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDuesAmountAndWaiverVersionDefaults(): void
	{
		$this->assertSame('25.00', ComponentOptions::duesAmount(''));
		$this->assertSame('25.00', ComponentOptions::duesAmount(-3));
		$this->assertSame('10.50', ComponentOptions::duesAmount('10.5'));
		$this->assertSame('2026', ComponentOptions::waiverVersion(''));
		$this->assertSame('2027', ComponentOptions::waiverVersion(' 2027 '));
		$this->assertSame(20, strlen(ComponentOptions::waiverVersion(str_repeat('A', 30))));
	}

	/**
	 * Uninstall deletes recorded groups only, children before parents.
	 *
	 * A foreign id, a core id, and any id Wendfold did not create stay even
	 * when they are empty. A created parent with an outside child stays.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUninstallDeleteSetSkipsForeignCoreAndUnrecordedGroups(): void
	{
		$groups = [
			['id' => 1, 'parent_id' => 0, 'title' => 'Public'],
			['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			['id' => 8, 'parent_id' => 1, 'title' => 'Super Users'],
			['id' => 10, 'parent_id' => 2, 'title' => 'Chapter Member'],
			['id' => 11, 'parent_id' => 10, 'title' => 'Chapter Officer'],
			['id' => 12, 'parent_id' => 2, 'title' => 'Foreign Empty'],
			['id' => 13, 'parent_id' => 10, 'title' => 'Outside Child'],
			['id' => 14, 'parent_id' => 2, 'title' => 'Created With Members'],
			['id' => 20, 'parent_id' => 2, 'title' => 'Empty Created'],
			['id' => 21, 'parent_id' => 20, 'title' => 'Empty Created Child'],
			['id' => 30, 'parent_id' => 0, 'title' => 'Custom Root'],
			['id' => 31, 'parent_id' => 2, 'title' => 'Public'],
		];
		$created = [1, 2, 8, 10, 11, 14, 20, 21, 30, 31];
		$members = [14 => 4];

		$delete = ComponentOptions::groupIdsToDelete($created, $groups, $members);

		$this->assertSame([21, 11, 20], $delete);
		$this->assertNotContains(1, $delete);
		$this->assertNotContains(2, $delete);
		$this->assertNotContains(8, $delete);
		$this->assertNotContains(12, $delete);
		$this->assertNotContains(13, $delete);
		$this->assertNotContains(10, $delete);
		$this->assertNotContains(14, $delete);
		$this->assertNotContains(30, $delete);
		$this->assertNotContains(31, $delete);

		$clean = [
			['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			['id' => 40, 'parent_id' => 2, 'title' => 'Chapter Member'],
			['id' => 41, 'parent_id' => 40, 'title' => 'Chapter Webmaster'],
			['id' => 42, 'parent_id' => 2, 'title' => 'Foreign Empty'],
		];

		$this->assertSame([41, 40], ComponentOptions::groupIdsToDelete([40, 41, 8], $clean, []));
		$this->assertNotContains(42, ComponentOptions::groupIdsToDelete([40, 41], $clean, []));
		$this->assertNotContains(8, ComponentOptions::groupIdsToDelete([40, 41, 8], $clean, []));
	}

	/**
	 * Update keeps a saved group id when that group still exists.
	 *
	 * The seed title is used only after the saved id is missing. A missing
	 * id and a missing title match is the only path that creates a group.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUpdateResolverPrefersSavedIdOverTitle(): void
	{
		$kept = ComponentOptions::resolveGroupChoice(44, true, 55);

		$this->assertSame(44, $kept['id']);
		$this->assertFalse($kept['create']);

		$renamed = ComponentOptions::resolveGroupChoice(44, true, 0);

		$this->assertSame(44, $renamed['id']);
		$this->assertFalse($renamed['create']);

		$cleared = ComponentOptions::resolveGroupChoice(0, false, 55);

		$this->assertSame(55, $cleared['id']);
		$this->assertFalse($cleared['create']);

		$missing = ComponentOptions::resolveGroupChoice(44, false, 55);

		$this->assertSame(55, $missing['id']);
		$this->assertFalse($missing['create']);

		$fresh = ComponentOptions::resolveGroupChoice(0, false, 0);

		$this->assertSame(0, $fresh['id']);
		$this->assertTrue($fresh['create']);

		$gone = ComponentOptions::resolveGroupChoice(44, false, 0);

		$this->assertTrue($gone['create']);
		$this->assertSame(0, $gone['id']);

		$groups = [
			10 => ['id' => 10, 'parent_id' => 2, 'title' => 'Chapter Member'],
			11 => ['id' => 11, 'parent_id' => 9, 'title' => 'Chapter Member'],
		];

		$this->assertSame(10, ComponentOptions::matchingGroupId($groups, 'Chapter Member', 2));
		$this->assertSame(0, ComponentOptions::matchingGroupId($groups, 'Chapter Member', 3));
		$this->assertSame(10, ComponentOptions::matchingGroupId($groups, 'Chapter Member', null));
	}

	/**
	 * The created-id list does not follow an option onto a foreign group.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreatedGroupIdsLeaveForeignTargetsOut(): void
	{
		$groups = [
			10 => ['id' => 10, 'parent_id' => 2, 'title' => 'Members'],
			11 => ['id' => 11, 'parent_id' => 10, 'title' => 'Chapter Webmaster'],
			99 => ['id' => 99, 'parent_id' => 2, 'title' => 'Visitors'],
		];

		$kept = ComponentOptions::createdIdsToRemember(
			[10, 1],
			$groups,
			[]
		);

		$this->assertSame([10], $kept);
		$this->assertNotContains(99, $kept);

		$inserted = ComponentOptions::createdIdsToRemember(
			[10],
			$groups,
			[11]
		);

		$this->assertSame([10, 11], $inserted);

		$json = (string) json_encode(
			[
				'group_member' => 99,
				'chapter_name' => 'North',
			]
		);
		$updated = ComponentOptions::mergeCreatedGroupIds($json, [10, 2, 11, 10]);

		$this->assertNotNull($updated);
		$saved = json_decode((string) $updated, true);
		$this->assertSame(99, $saved['group_member']);
		$this->assertSame('North', $saved['chapter_name']);
		$this->assertSame('10,11', $saved[ComponentOptions::CREATED_GROUP_IDS]);
		$this->assertNull(ComponentOptions::mergeCreatedGroupIds((string) $updated, [11, 10]));
	}

	/**
	 * A saved id is replaced when that group has been deleted.
	 *
	 * An id that still exists is kept, including one that is not a seed group.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeadSavedGroupIdIsReplacedOnUpdate(): void
	{
		$stored = [
			'group_activities' => 70,
			'group_member'     => 99,
		];
		$resolved = [
			'group_activities' => 74,
			'group_member'     => 99,
		];
		$existing = [2, 74, 99];

		$write = ComponentOptions::groupIdsToStore($stored, $resolved, $existing);

		$this->assertSame(74, $write['group_activities']);
		$this->assertArrayNotHasKey('group_member', $write);

		$json = ComponentOptions::mergeGroupParams((string) json_encode($stored), $resolved, $existing);
		$saved = json_decode((string) $json, true);

		$this->assertSame(74, $saved['group_activities']);
		$this->assertSame(99, $saved['group_member']);
		$this->assertArrayNotHasKey('group_member', ComponentOptions::groupIdsToStore($stored, $resolved));
	}

	/**
	 * A missing seed group is parented on Wendfold's seed chain.
	 *
	 * An option that points at a foreign group is not that parent.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMissingSeedGroupUsesCreatedParentNotForeignOption(): void
	{
		$roadCaptain = ComponentOptions::groups()[1];
		$groups = [
			2  => ['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			10 => ['id' => 10, 'parent_id' => 2, 'title' => 'Chapter Member'],
			20 => ['id' => 20, 'parent_id' => 10, 'title' => 'Chapter Road Captain'],
			21 => ['id' => 21, 'parent_id' => 99, 'title' => 'Chapter Road Captain'],
			99 => ['id' => 99, 'parent_id' => 2, 'title' => 'Visitors'],
		];

		$parent = ComponentOptions::seedParentId(
			$roadCaptain,
			['group_member' => 99],
			$groups,
			[10]
		);

		$this->assertSame(10, $parent);
		$this->assertSame(20, ComponentOptions::matchingGroupId($groups, 'Chapter Road Captain', $parent));
		$this->assertNotSame(21, ComponentOptions::matchingGroupId($groups, 'Chapter Road Captain', $parent));

		$createdParent = ComponentOptions::seedParentId(
			$roadCaptain,
			['group_member' => 10],
			$groups,
			[10]
		);

		$this->assertSame(10, $createdParent);

		$onlyForeign = [
			2  => ['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			99 => ['id' => 99, 'parent_id' => 2, 'title' => 'Visitors'],
		];

		$this->assertSame(
			0,
			ComponentOptions::seedParentId($roadCaptain, ['group_member' => 99], $onlyForeign, [])
		);
	}

	/**
	 * An upgrade that finds existing groups records none of them.
	 *
	 * A later update with no new insert still records nothing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUpgradeAdoptionRecordsNothing(): void
	{
		$groups = [
			2   => ['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			143 => ['id' => 143, 'parent_id' => 2, 'title' => 'Chapter Member'],
			147 => ['id' => 147, 'parent_id' => 143, 'title' => 'Chapter Officer'],
			149 => ['id' => 149, 'parent_id' => 147, 'title' => 'Chapter Webmaster'],
		];

		$first = ComponentOptions::createdIdsToRemember([], $groups, []);
		$second = ComponentOptions::createdIdsToRemember($first, $groups, []);

		$this->assertSame([], $first);
		$this->assertSame([], $second);
	}

	/**
	 * An inserted group is recorded once and stays recorded.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInsertedGroupIsRecordedOnce(): void
	{
		$groups = [
			2   => ['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			143 => ['id' => 143, 'parent_id' => 2, 'title' => 'Chapter Member'],
			150 => ['id' => 150, 'parent_id' => 143, 'title' => 'Chapter Webmaster'],
		];

		$created = ComponentOptions::createdIdsToRemember([], $groups, [150]);
		$again = ComponentOptions::createdIdsToRemember($created, $groups, [150]);

		$this->assertSame([150], $created);
		$this->assertSame([150], $again);
	}

	/**
	 * Uninstall removes only recorded groups that are empty and unprotected.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUninstallDeletesOnlyRecordedEmptyGroups(): void
	{
		$groups = [
			['id' => 1, 'parent_id' => 0, 'title' => 'Public'],
			['id' => 2, 'parent_id' => 1, 'title' => 'Registered'],
			['id' => 8, 'parent_id' => 1, 'title' => 'Super Users'],
			['id' => 143, 'parent_id' => 2, 'title' => 'Chapter Member'],
			['id' => 147, 'parent_id' => 143, 'title' => 'Chapter Officer'],
			['id' => 148, 'parent_id' => 147, 'title' => 'Chapter Webmaster'],
			['id' => 149, 'parent_id' => 147, 'title' => 'Chapter Webmaster'],
		];

		$delete = ComponentOptions::groupIdsToDelete(
			[143, 148, 2, 8],
			$groups,
			[143 => 3]
		);

		$this->assertSame([148], $delete);
		$this->assertNotContains(149, $delete);
		$this->assertNotContains(143, $delete);
		$this->assertNotContains(2, $delete);
		$this->assertNotContains(8, $delete);
	}

	/**
	 * Chapter groups sit inside their seed parents, and a repaired tree
	 * does not treat a member as every role.
	 *
	 * Seed parents: Registered contains Chapter Member. Chapter Member
	 * contains Road Captain, Activities, and Membership. Membership
	 * contains Officer. Officer contains Webmaster.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNestedPositionsRepairMembership(): void
	{
		$groups = [
			['id' => 1, 'parent_id' => 0, 'title' => 'Public', 'lft' => 0, 'rgt' => 0],
			['id' => 2, 'parent_id' => 1, 'title' => 'Registered', 'lft' => 0, 'rgt' => 0],
			['id' => 10, 'parent_id' => 2, 'title' => 'Chapter Member', 'lft' => 0, 'rgt' => 0],
			['id' => 11, 'parent_id' => 10, 'title' => 'Chapter Road Captain', 'lft' => 0, 'rgt' => 0],
			['id' => 12, 'parent_id' => 10, 'title' => 'Chapter Activities', 'lft' => 0, 'rgt' => 0],
			['id' => 13, 'parent_id' => 10, 'title' => 'Chapter Membership', 'lft' => 0, 'rgt' => 0],
			['id' => 14, 'parent_id' => 13, 'title' => 'Chapter Officer', 'lft' => 0, 'rgt' => 0],
			['id' => 15, 'parent_id' => 14, 'title' => 'Chapter Webmaster', 'lft' => 0, 'rgt' => 0],
		];

		$placed = ComponentOptions::nestedPositions($groups);
		$again = ComponentOptions::nestedPositions($placed);

		$this->assertSame($placed, $again);

		foreach ([10 => 2, 11 => 10, 12 => 10, 13 => 10, 14 => 13, 15 => 14] as $id => $parentId)
		{
			$this->assertGreaterThan(0, $placed[$id]['lft']);
			$this->assertGreaterThan($placed[$id]['lft'], $placed[$id]['rgt']);
			$this->assertGreaterThan($placed[$parentId]['lft'], $placed[$id]['lft']);
			$this->assertLessThan($placed[$parentId]['rgt'], $placed[$id]['rgt']);
		}

		$zeros = [];

		foreach ($groups as $group)
		{
			$zeros[$group['id']] = $group;
		}

		$broken = ComponentOptions::groupsForUser($zeros, [10]);

		$this->assertContains(14, $broken);
		$this->assertContains(15, $broken);

		$member = ComponentOptions::groupsForUser($placed, [10]);

		$this->assertContains(10, $member);
		$this->assertContains(2, $member);
		$this->assertContains(1, $member);
		$this->assertNotContains(11, $member);
		$this->assertNotContains(12, $member);
		$this->assertNotContains(13, $member);
		$this->assertNotContains(14, $member);
		$this->assertNotContains(15, $member);

		$officer = ComponentOptions::groupsForUser($placed, [14]);

		$this->assertContains(14, $officer);
		$this->assertContains(13, $officer);
		$this->assertContains(10, $officer);
		$this->assertContains(2, $officer);
		$this->assertNotContains(15, $officer);
		$this->assertNotContains(11, $officer);
	}
}
