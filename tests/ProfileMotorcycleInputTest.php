<?php

/**
 * Wendfold - Profile-edit motorcycle subform rules.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\ProfileMotorcycleInput;

require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/ProfileMotorcycleInput.php';

/**
 * Blank rows are skipped; owned rows update or delete; foreign ids are rejected.
 *
 * @since  1.0.0
 */
final class ProfileMotorcycleInputTest extends TestCase
{
	/**
	 * An empty subform plans no inserts and no updates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptySubformGivesZeroRows(): void
	{
		$plan = ProfileMotorcycleInput::plan(
			[],
			[
				[
					'id'       => 0,
					'year'     => '',
					'make'     => '',
					'model'    => '',
					'vin'      => '',
					'color'    => '',
					'nickname' => '',
				],
			],
			new \DateTimeImmutable('2026-10-31')
		);

		$this->assertSame([], $plan['insert']);
		$this->assertSame([], $plan['update']);
		$this->assertSame([], $plan['delete']);
		$this->assertCount(0, $plan['insert']);
	}

	/**
	 * One filled row and one blank row plan a single insert.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOneFilledRowAndOneBlankRowGiveOneRow(): void
	{
		$plan = ProfileMotorcycleInput::plan(
			[],
			[
				[
					'id'    => 0,
					'year'  => '2018',
					'make'  => 'ExampleMake',
					'model' => 'Roadster',
					'color' => 'Black',
				],
				[
					'id'    => 0,
					'year'  => '   ',
					'make'  => '',
					'model' => '',
					'vin'   => '',
					'color' => '',
				],
			],
			new \DateTimeImmutable('2026-10-31')
		);

		$this->assertCount(1, $plan['insert']);
		$this->assertSame([], $plan['update']);
		$this->assertSame([], $plan['delete']);
		$this->assertSame(2018, $plan['insert'][0]['year']);
		$this->assertSame('ExampleMake', $plan['insert'][0]['make']);
		$this->assertSame('Roadster', $plan['insert'][0]['model']);
	}

	/**
	 * An edit with a matching owned id updates that row in place.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEditUpdatesInPlace(): void
	{
		$plan = ProfileMotorcycleInput::plan(
			[12],
			[
				[
					'id'    => 12,
					'year'  => '2020',
					'make'  => 'UpdatedMake',
					'model' => 'Tour',
					'color' => 'Red',
					'vin'   => '1M8GDM9AXKP042788',
				],
			],
			new \DateTimeImmutable('2026-10-31'),
			[
				12 => ['year' => 2019, 'vin' => 'OLDVINVALUE000001'],
			]
		);

		$this->assertSame([], $plan['insert']);
		$this->assertCount(1, $plan['update']);
		$this->assertSame([], $plan['delete']);
		$this->assertSame(12, $plan['update'][0]['id']);
		$this->assertSame('UpdatedMake', $plan['update'][0]['make']);
		$this->assertSame('1M8GDM9AXKP042788', $plan['update'][0]['vin']);
	}

	/**
	 * A Remove checkbox on an owned row deletes it; omitting a row does not.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovingARowDeletesIt(): void
	{
		$plan = ProfileMotorcycleInput::plan(
			[5, 9],
			[
				[
					'id'    => 5,
					'year'  => '2015',
					'make'  => 'KeepMake',
					'model' => 'KeepModel',
				],
				[
					'id'     => 9,
					'remove' => true,
				],
			],
			new \DateTimeImmutable('2026-10-31'),
			[
				5 => ['year' => 2015, 'vin' => ''],
				9 => ['year' => 2010, 'vin' => ''],
			]
		);

		$this->assertSame([], $plan['insert']);
		$this->assertCount(1, $plan['update']);
		$this->assertSame([9], $plan['delete']);
	}

	/**
	 * A crafted id that belongs to another member is rejected.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCraftedForeignIdIsRejected(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Foreign motorcycle id.');

		ProfileMotorcycleInput::plan(
			[3],
			[
				[
					'id'    => 99,
					'year'  => '2019',
					'make'  => 'StolenMake',
					'model' => 'Forge',
				],
			],
			new \DateTimeImmutable('2026-10-31')
		);
	}

	/**
	 * Whitespace-only visible fields count as blank.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testWhitespaceOnlyRowIsBlank(): void
	{
		$this->assertTrue(
			ProfileMotorcycleInput::isBlankRow(
				[
					'year'     => '  ',
					'make'     => "\t",
					'model'    => '',
					'vin'      => '',
					'color'    => ' ',
					'nickname' => '',
				]
			)
		);
	}

	/**
	 * Year max is the calendar year of the clock plus one.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testYearMaxIsCurrentYearPlusOne(): void
	{
		$this->assertSame(2027, ProfileMotorcycleInput::yearMax(new \DateTimeImmutable('2026-10-31')));
		$this->assertSame(2025, ProfileMotorcycleInput::yearMax(new \DateTimeImmutable('2024-01-01')));
	}

	/**
	 * Year validation uses the translated message format and dynamic upper bound.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testYearValidationMessageUsesDynamicBound(): void
	{
		$now = new \DateTimeImmutable('2026-10-31');

		$this->assertSame(
			'Motorcycle year must be between 1885 and 2027.',
			ProfileMotorcycleInput::yearMessage($now)
		);

		try
		{
			ProfileMotorcycleInput::normalizeYear('1884', $now);
			$this->fail('Expected year validation to fail.');
		}
		catch (\InvalidArgumentException $error)
		{
			$this->assertSame(ProfileMotorcycleInput::MSG_YEAR, $error->getMessage());
		}

		$this->assertSame(1885, ProfileMotorcycleInput::normalizeYear('1885', $now));
		$this->assertSame(2027, ProfileMotorcycleInput::normalizeYear('2027', $now));
	}

	/**
	 * VIN validation uses the fixed translated message and exact length for new values.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testVinValidationMessage(): void
	{
		$this->assertSame(
			'VIN must be 17 characters, letters and digits, without I, O or Q.',
			ProfileMotorcycleInput::vinMessage()
		);

		try
		{
			ProfileMotorcycleInput::normalizeVin('SHORT');
			$this->fail('Expected short VIN to fail.');
		}
		catch (\InvalidArgumentException $error)
		{
			$this->assertSame(ProfileMotorcycleInput::MSG_VIN, $error->getMessage());
		}

		$this->assertSame('', ProfileMotorcycleInput::normalizeVin(''));
		$this->assertSame('1M8GDM9AXKP042788', ProfileMotorcycleInput::normalizeVin('1m8gdm9axkp042788'));
	}

	/**
	 * An unchanged legacy VIN (admin length) and year save without re-validation.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUnchangedLegacyVinIsAccepted(): void
	{
		$legacy = 'LEGACYVINVALUE1234567890';
		$now    = new \DateTimeImmutable('2026-10-31');

		$this->assertSame(24, \strlen($legacy));
		$this->assertLessThanOrEqual(ProfileMotorcycleInput::VIN_MAX_LENGTH, \strlen($legacy));

		$this->assertSame($legacy, ProfileMotorcycleInput::normalizeVin($legacy, $legacy));
		$this->assertSame(1800, ProfileMotorcycleInput::normalizeYear('1800', $now, 1800));

		$plan = ProfileMotorcycleInput::plan(
			[7],
			[
				[
					'id'    => 7,
					'year'  => '1800',
					'make'  => 'LegacyMake',
					'model' => 'LegacyModel',
					'vin'   => $legacy,
				],
			],
			$now,
			[
				7 => ['year' => 1800, 'vin' => $legacy],
			]
		);

		$this->assertCount(1, $plan['update']);
		$this->assertSame($legacy, $plan['update'][0]['vin']);
		$this->assertSame(1800, $plan['update'][0]['year']);
		$this->assertSame([], $plan['delete']);
	}

	/**
	 * Changing a VIN still requires the 17-character format.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testChangedVinMustMeetFormatRule(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage(ProfileMotorcycleInput::MSG_VIN);

		ProfileMotorcycleInput::normalizeVin(
			'CHANGEDBUTSTILLTOOLONG12',
			'LEGACYVINVALUE1234567890'
		);
	}

	/**
	 * Motorcycle validation is invoked before the photo upload write in the controller.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMotorcycleValidationRunsBeforePhotoUpload(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Controller/ProfileeditController.php'
		);

		$this->assertNotFalse($source);

		$validationPos = strpos($source, 'motorcycleValidationError(');
		$photoPos      = strpos($source, 'handlePhotoUpload(');

		$this->assertNotFalse($validationPos);
		$this->assertNotFalse($photoPos);
		$this->assertLessThan(
			$photoPos,
			$validationPos,
			'Motorcycle validation must run before handlePhotoUpload writes files.'
		);
	}

	/**
	 * When remaining bikes have no primary, the lowest id is chosen.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrimaryIdAssignedWhenNoneRemainPrimary(): void
	{
		$this->assertSame(2, ProfileMotorcycleInput::primaryIdToAssign([5, 2, 9], []));
		$this->assertNull(ProfileMotorcycleInput::primaryIdToAssign([5, 2, 9], [5]));
		$this->assertNull(ProfileMotorcycleInput::primaryIdToAssign([], []));
		$this->assertSame(4, ProfileMotorcycleInput::primaryIdToAssign([4], []));
	}

	/**
	 * Owned rows are deleted only when Remove is posted; blank visible fields keep notes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBlankOwnedRowWithoutRemoveIsNotDeleted(): void
	{
		$plan = ProfileMotorcycleInput::plan(
			[3, 8],
			[
				[
					'id'       => 3,
					'year'     => '',
					'make'     => '',
					'model'    => '',
					'vin'      => '',
					'color'    => '',
					'nickname' => '',
				],
				[
					'id'    => 8,
					'year'  => '2012',
					'make'  => 'KeepMake',
					'model' => 'KeepModel',
				],
			],
			new \DateTimeImmutable('2026-10-31'),
			[
				3 => ['year' => 1999, 'vin' => 'NOTESONLYLEGACYVIN1'],
				8 => ['year' => 2011, 'vin' => ''],
			]
		);

		$this->assertSame([], $plan['delete']);
		$this->assertCount(1, $plan['update']);
		$this->assertSame(8, $plan['update'][0]['id']);

		$omitted = ProfileMotorcycleInput::plan(
			[3],
			[],
			new \DateTimeImmutable('2026-10-31'),
			[
				3 => ['year' => 1999, 'vin' => 'NOTESONLYLEGACYVIN1'],
			]
		);

		$this->assertSame([], $omitted['delete']);
		$this->assertSame([], $omitted['update']);
	}
}
