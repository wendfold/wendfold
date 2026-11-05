<?php

/**
 * Wendfold - Lookup rules, delete guard, and behavior keys.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupDeleteGuard;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupRecordRules;
use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Administrator\Helper\MeetingAudience;
use Wendfold\Component\Wendfold\Administrator\Helper\MembershipImport;
use Wendfold\Component\Wendfold\Administrator\Helper\OfficerRoleKey;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeBehavior.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/OfficerRoleKey.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupDeleteGuard.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupRecordRules.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MembershipImport.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingAudience.php';

/**
 * Rules the lookup tables and models apply, without a database.
 *
 * @since  1.0.0
 */
final class LookupRulesTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBehaviorBranches(): void
	{
		$this->assertTrue(EventTypeBehavior::isRide(EventTypeBehavior::Ride->value));
		$this->assertTrue(EventTypeBehavior::isMeeting(EventTypeBehavior::Meeting->value));
		$this->assertTrue(EventTypeBehavior::isMeeting(0, 3));
		$this->assertFalse(EventTypeBehavior::isMeeting(EventTypeBehavior::Ride->value, 2));
		$this->assertTrue(EventTypeBehavior::isOvernight(EventTypeBehavior::Overnight->value));
		$this->assertTrue(EventTypeBehavior::isOvernight(0, 5));
		$this->assertFalse(EventTypeBehavior::isOvernight(EventTypeBehavior::General->value, 1));
		$this->assertSame(2, EventTypeBehavior::workflow(EventTypeBehavior::Ride->value));
		$this->assertSame(3, EventTypeBehavior::workflow(EventTypeBehavior::Meeting->value));
		$this->assertSame(5, EventTypeBehavior::workflow(EventTypeBehavior::Overnight->value));
		$this->assertSame(1, EventTypeBehavior::workflow(EventTypeBehavior::General->value));
		$this->assertFalse(EventTypeBehavior::isValid(0));
		$this->assertTrue(EventTypeBehavior::isValid(4));
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHeadRoadCaptainKey(): void
	{
		$this->assertTrue(OfficerRoleKey::isHeadRoadCaptain(OfficerRoleKey::HEAD_ROAD_CAPTAIN));
		$this->assertFalse(OfficerRoleKey::isHeadRoadCaptain('Head Road Captain'));
		$this->assertFalse(OfficerRoleKey::isHeadRoadCaptain(null));
		$this->assertNull(OfficerRoleKey::normalise('  '));
		$this->assertSame('head_road_captain', OfficerRoleKey::normalise('Head_Road_Captain'));
		$this->assertFalse(OfficerRoleKey::normalise('1bad'));
		$this->assertFalse(OfficerRoleKey::normalise('has space'));
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteGuardBlocksRowsInUse(): void
	{
		$this->assertNull(LookupDeleteGuard::messageKey(0));
		$this->assertFalse(LookupDeleteGuard::isBlocked(0));
		$this->assertSame('COM_WENDFOLD_LOOKUP_DELETE_IN_USE', LookupDeleteGuard::messageKey(2));
		$this->assertTrue(LookupDeleteGuard::isBlocked(1));
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLookupRecordRules(): void
	{
		$blank = LookupRecordRules::title('   ');
		$this->assertFalse($blank['ok']);
		$this->assertSame('COM_WENDFOLD_LOOKUP_TITLE_REQUIRED', $blank['message']);

		$titled = LookupRecordRules::title('  Ride leader  ');
		$this->assertTrue($titled['ok']);
		$this->assertSame('Ride leader', $titled['title']);

		$key = LookupRecordRules::systemKey('Head_Road_Captain');
		$this->assertTrue($key['ok']);
		$this->assertSame(OfficerRoleKey::HEAD_ROAD_CAPTAIN, $key['systemKey']);

		$badKey = LookupRecordRules::systemKey('No Spaces');
		$this->assertFalse($badKey['ok']);

		$behavior = LookupRecordRules::behavior(9);
		$this->assertFalse($behavior['ok']);
		$this->assertSame('COM_WENDFOLD_EVENT_TYPE_BEHAVIOR_INVALID', $behavior['message']);
		$this->assertTrue(LookupRecordRules::behavior(EventTypeBehavior::Overnight->value)['ok']);
		$this->assertSame(1, LookupRecordRules::workflow(9));
		$this->assertSame(5, LookupRecordRules::workflow(5));
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLanguageKeyDetection(): void
	{
		$this->assertTrue(LookupTitle::isLanguageKey('COM_WENDFOLD_EVENT_TYPE_RIDE'));
		$this->assertFalse(LookupTitle::isLanguageKey('Chapter ride'));
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembershipImportCodes(): void
	{
		$this->assertSame('Full', MembershipImport::canonicalCode('1'));
		$this->assertSame('Associate', MembershipImport::canonicalCode('Assoc'));
		$this->assertSame('Life', MembershipImport::canonicalCode('lifetime'));
		$this->assertSame('Honorary', MembershipImport::canonicalCode(' Honorary '));
		$this->assertNull(MembershipImport::canonicalCode('   '));
		$this->assertSame('__life__', MembershipImport::LIFE_FLAG);
	}

	/**
	 * An unknown membership code is reported. A known code is not.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnknownMembershipCodeIsARowWarning(): void
	{
		$rows = [
			['line' => 2, 'has_type' => true, 'membership_type' => 'Full'],
			['line' => 3, 'has_type' => true, 'membership_type' => 'Honorary'],
			['line' => 4, 'has_type' => true, 'membership_type' => MembershipImport::LIFE_FLAG],
			['line' => 5, 'has_type' => false, 'membership_type' => 'Mystery'],
		];
		$resolved = [
			'Full'                       => 1,
			'Honorary'                   => 0,
			MembershipImport::LIFE_FLAG  => 0,
		];

		$warnings = MembershipImport::unresolvedRows($rows, $resolved);

		$this->assertCount(2, $warnings);
		$this->assertSame(3, $warnings[0]['line']);
		$this->assertSame('Honorary', $warnings[0]['code']);
		$this->assertSame('Honorary', $warnings[0]['label']);
		$this->assertSame(4, $warnings[1]['line']);
		$this->assertSame('Life', $warnings[1]['label']);
	}

	/**
	 * Volunteer officer is a system key, and it is not an install seed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testVolunteerOfficerIsNotASeededRole(): void
	{
		$sql = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/sql/install.mysql.utf8.sql');

		$this->assertStringContainsString("'head_road_captain'", $sql);
		$this->assertDoesNotMatchRegularExpression("/'volunteer_officer'/", $sql);
		$this->assertSame('volunteer_officer', OfficerRoleKey::VOLUNTEER_OFFICER);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMeetingAudienceFollowsScopeNotASubtypeNumber(): void
	{
		$this->assertSame('officers', MeetingAudience::poolMode(AudienceHelper::SCOPE_OFFICERS));
		$this->assertSame('road_captains', MeetingAudience::poolMode(AudienceHelper::SCOPE_ROAD_CAPTAINS));
		$this->assertSame('standard', MeetingAudience::poolMode(AudienceHelper::SCOPE_MEMBERS));
		$this->assertTrue(MeetingAudience::canTakeRoll(AudienceHelper::SCOPE_ROAD_CAPTAINS, false, true));
		$this->assertFalse(MeetingAudience::canTakeRoll(AudienceHelper::SCOPE_OFFICERS, false, true));
		$this->assertTrue(MeetingAudience::canTakeRoll(AudienceHelper::SCOPE_MEMBERS, true, false));
	}
}
