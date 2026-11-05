<?php

/**
 * Wendfold - Administrator member save input rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MemberSaveInput;

/**
 * Locks the id check and the photo strip used by the administrator member save.
 */
final class MemberSaveInputTest extends TestCase
{
    public function testAMissingIdIsNotAMismatch(): void
    {
        self::assertFalse(MemberSaveInput::idMismatches(5, ['user_id' => 3]));
        self::assertFalse(MemberSaveInput::idMismatches(0, []));
    }

    public function testAMatchingIdIsNotAMismatch(): void
    {
        self::assertFalse(MemberSaveInput::idMismatches(5, ['id' => 5]));
        self::assertFalse(MemberSaveInput::idMismatches(5, ['id' => '5']));
        self::assertFalse(MemberSaveInput::idMismatches(0, ['id' => 0]));
        self::assertFalse(MemberSaveInput::idMismatches(0, ['id' => '']));
    }

    public function testAForgedIdIsAMismatchForAnyRecord(): void
    {
        self::assertTrue(MemberSaveInput::idMismatches(5, ['id' => 9]));
        self::assertTrue(MemberSaveInput::idMismatches(0, ['id' => 9]));
        self::assertTrue(MemberSaveInput::idMismatches(5, ['id' => ['9']]));
    }

    public function testAnEditDropsPostedPhotoFieldsAndKeepsTheRest(): void
    {
        $jform = MemberSaveInput::withoutPhoto(
            [
                'id' => 5,
                'user_id' => 3,
                'photo' => 'images/wendfold/profiles/9.jpg',
                'photo_path' => 'images/wendfold/profiles/9.jpg',
                'photo_upload' => 'ignored',
                'shirt_size' => 'L',
            ],
            5
        );

        self::assertSame(
            [
                'id' => 5,
                'user_id' => 3,
                'shirt_size' => 'L',
            ],
            $jform
        );
        self::assertArrayNotHasKey('photo', $jform);
        self::assertArrayNotHasKey('photo_path', $jform);
    }

    public function testANewMemberStoresAnEmptyPhoto(): void
    {
        $jform = MemberSaveInput::withoutPhoto(
            [
                'user_id' => 3,
                'photo' => 'images/wendfold/profiles/9.jpg',
                'photo_path' => 'images/other.jpg',
            ],
            0
        );

        self::assertSame('', $jform['photo']);
        self::assertArrayNotHasKey('photo_path', $jform);
        self::assertSame(3, $jform['user_id']);
    }
}
