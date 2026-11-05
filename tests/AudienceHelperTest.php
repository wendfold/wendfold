<?php

/**
 * Wendfold - Audience scope regression tests.
 *
 * One case per leak from issue 53, plus every scope and role.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;

/**
 * Locks the shared audience rule used by the list, detail, RSVP, and GPX paths.
 */
final class AudienceHelperTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: array<string, bool>|null, 2: bool, 3: int[]}>
     */
    public static function scopeAndRoleProvider(): array
    {
        $roles = [
            'guest or no profile' => null,
            'member' => self::member(),
            'officer' => self::officer(),
            'road captain' => self::roadCaptain(),
            'officer and road captain' => self::officerAndRoadCaptain(),
        ];

        $allowed = [
            'guest or no profile' => [1],
            'member' => [1, 2],
            'officer' => [1, 2, 3, 4],
            'road captain' => [1, 2, 4],
            'officer and road captain' => [1, 2, 3, 4],
        ];

        $cases = [];

        foreach ($roles as $role => $flags) {
            foreach ([0, 1, 2, 3, 4, 5, -1, 99] as $scope) {
                $cases[$role . ' scope ' . $scope] = [
                    $scope,
                    $flags,
                    in_array($scope, $allowed[$role], true),
                    $allowed[$role],
                ];
            }
        }

        return $cases;
    }

    /**
     * @param   array<string, bool>|null  $flags
     * @param   int[]                     $expectedScopes
     */
    #[DataProvider('scopeAndRoleProvider')]
    public function testEveryScopeAndRole(int $scope, ?array $flags, bool $canAccess, array $expectedScopes): void
    {
        self::assertSame($expectedScopes, AudienceHelper::allowedScopes($flags));
        self::assertSame($canAccess, AudienceHelper::canAccess($scope, $flags));
    }

    public function testEventsListHidesMembersScopeFromGuestsAndNoProfile(): void
    {
        self::assertSame([AudienceHelper::SCOPE_ALL_VISITORS], AudienceHelper::allowedScopes(null));
        self::assertNotContains(AudienceHelper::SCOPE_MEMBERS, AudienceHelper::allowedScopes(null));
        self::assertSame(
            [AudienceHelper::SCOPE_ALL_VISITORS, AudienceHelper::SCOPE_MEMBERS],
            AudienceHelper::allowedScopes(self::member())
        );
    }

    public function testDetailAndTripDenyRestrictedScopes(): void
    {
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_MEMBERS, null));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ALL_VISITORS, null));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_MEMBERS, self::member()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::member()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member()));
    }

    public function testParentAndChildUseTheSameRule(): void
    {
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::member()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::officer()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member()));
    }

    public function testRsvpRefusesEventsTheUserCannotSee(): void
    {
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::member()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_MEMBERS, null));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::roadCaptain()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::officer()));
    }

    public function testMyRsvpsAndDashboardFilterToAllowedScopes(): void
    {
        self::assertSame([1, 2], AudienceHelper::allowedScopes(self::member()));
        self::assertNotContains(3, AudienceHelper::allowedScopes(self::member()));
        self::assertNotContains(4, AudienceHelper::allowedScopes(self::member()));
        self::assertSame([1, 2, 4], AudienceHelper::allowedScopes(self::roadCaptain()));
        self::assertSame([1], AudienceHelper::allowedScopes(null));
    }

    public function testCheckinDetailsFollowScope(): void
    {
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ALL_VISITORS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_MEMBERS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::member()));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::officer()));
    }

    public function testGpxLinkFollowsScope(): void
    {
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ALL_VISITORS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_MEMBERS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_OFFICERS, self::roadCaptain()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::officer()));
    }

    public function testManagerAndAdminSeeOutOfScopeDetailAndGpx(): void
    {
        $scope = AudienceHelper::SCOPE_OFFICERS;
        $member = self::member();

        self::assertFalse(AudienceHelper::canSeeEvent($scope, $member, false, false));
        self::assertTrue(AudienceHelper::canSeeEvent($scope, $member, true, false));
        self::assertTrue(AudienceHelper::canSeeEvent($scope, $member, false, true));
        self::assertTrue(AudienceHelper::canSeeEvent($scope, null, false, true));
        self::assertFalse(AudienceHelper::canSeeEvent($scope, null, false, false));
        self::assertTrue(AudienceHelper::canSeeEvent(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member(), true, false));
    }

    public function testUnknownScopeIsDeniedForEveryRole(): void
    {
        $roles = [
            null,
            self::member(),
            self::officer(),
            self::roadCaptain(),
            self::officerAndRoadCaptain(),
            [],
        ];

        foreach ($roles as $flags) {
            foreach ([0, 5, -1, 99, 8] as $scope) {
                self::assertFalse(AudienceHelper::canAccess($scope, $flags));
                self::assertNotContains($scope, AudienceHelper::allowedScopes($flags));
            }
        }
    }

    public function testScopeFourAllowsOfficersAndRoadCaptains(): void
    {
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, null));
        self::assertFalse(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::member()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::officer()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::roadCaptain()));
        self::assertTrue(AudienceHelper::canAccess(AudienceHelper::SCOPE_ROAD_CAPTAINS, self::officerAndRoadCaptain()));
        self::assertSame([1, 2, 3, 4], AudienceHelper::allowedScopes(self::officer()));
        self::assertSame([1, 2, 3, 4], AudienceHelper::allowedScopes(self::officerAndRoadCaptain()));
        self::assertNotContains(AudienceHelper::SCOPE_OFFICERS, AudienceHelper::allowedScopes(self::roadCaptain()));
    }

    public function testEmptyFlagsAreAPublishedMemberNotAGuest(): void
    {
        self::assertSame([1, 2], AudienceHelper::allowedScopes([]));
        self::assertNull(AudienceHelper::flagsFromProfile(null));

        $profile = (object) ['is_officer' => 0, 'is_road_captain' => 1];

        self::assertSame(
            ['is_officer' => false, 'is_road_captain' => true],
            AudienceHelper::flagsFromProfile($profile)
        );
        self::assertSame([1, 2, 4], AudienceHelper::allowedScopes(AudienceHelper::flagsFromProfile($profile)));
    }

    /**
     * @return array{is_officer: bool, is_road_captain: bool}
     */
    private static function member(): array
    {
        return ['is_officer' => false, 'is_road_captain' => false];
    }

    /**
     * @return array{is_officer: bool, is_road_captain: bool}
     */
    private static function officer(): array
    {
        return ['is_officer' => true, 'is_road_captain' => false];
    }

    /**
     * @return array{is_officer: bool, is_road_captain: bool}
     */
    private static function roadCaptain(): array
    {
        return ['is_officer' => false, 'is_road_captain' => true];
    }

    /**
     * @return array{is_officer: bool, is_road_captain: bool}
     */
    private static function officerAndRoadCaptain(): array
    {
        return ['is_officer' => true, 'is_road_captain' => true];
    }
}
