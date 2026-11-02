<?php

/**
 * Wendfold - Renewal reminder opt-out accessor.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;

require_once __DIR__ . '/support/RegistryStub.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RenewalReminderOptOut.php';

/**
 * Opt-out is read from #__users.params without a profile column.
 *
 * @since  1.0.0
 */
final class RenewalReminderOptOutTest extends TestCase
{
	/**
	 * JSON, array, and Registry forms of the flat params key are recognised.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReadsOptOutFromUserParamsShapes(): void
	{
		$key = RenewalReminderOptOut::PARAM_KEY;

		$this->assertTrue(RenewalReminderOptOut::isOptedOutFromParams('{"' . $key . '":"1"}'));
		$this->assertTrue(RenewalReminderOptOut::isOptedOutFromParams([$key => 1]));
		$this->assertTrue(RenewalReminderOptOut::isOptedOutFromParams(new Registry([$key => 'true'])));
		$this->assertFalse(RenewalReminderOptOut::isOptedOutFromParams('{"' . $key . '":"0"}'));
		$this->assertFalse(RenewalReminderOptOut::isOptedOutFromParams(null));
		$this->assertFalse(RenewalReminderOptOut::isOptedOutFromParams(''));
	}

	/**
	 * Session form data keeps the posted opt-out; only missing keys are filled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFillFormDataPreservesPostedOptOut(): void
	{
		$field = RenewalReminderOptOut::FORM_FIELD;

		$session = RenewalReminderOptOut::fillFormDataIfMissing(
			['user_id' => 4, $field => 1],
			false
		);
		$this->assertSame(1, $session[$field]);

		$fromItem = RenewalReminderOptOut::fillFormDataIfMissing(
			['user_id' => 4],
			true
		);
		$this->assertSame(1, $fromItem[$field]);

		$object = (object) ['user_id' => 4, $field => 0];
		$kept = RenewalReminderOptOut::fillFormDataIfMissing($object, true);
		$this->assertSame(0, $kept->$field);
	}

	/**
	 * A checked opt-out in user-state survives redisplay over a stored false.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckedOptOutSurvivesValidationRedisplay(): void
	{
		$field = RenewalReminderOptOut::FORM_FIELD;
		$formState = [$field => 1, 'shirt_size' => 'L'];

		$this->assertSame(
			1,
			RenewalReminderOptOut::resolveFormOptOut($formState, false)
		);
	}

	/**
	 * An unchecked opt-out in user-state survives redisplay over a stored true.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUncheckedOptOutSurvivesValidationRedisplay(): void
	{
		$field = RenewalReminderOptOut::FORM_FIELD;
		$formState = [$field => 0];

		$this->assertSame(
			0,
			RenewalReminderOptOut::resolveFormOptOut($formState, true)
		);
	}

	/**
	 * Without user-state, the stored param is shown; resolve does not write params.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testStoredOptOutUsedUntilSuccessfulSave(): void
	{
		$params = [RenewalReminderOptOut::PARAM_KEY => 0];

		$this->assertSame(0, RenewalReminderOptOut::resolveFormOptOut(null, false));
		$this->assertSame(1, RenewalReminderOptOut::resolveFormOptOut([], true));
		$this->assertSame(
			0,
			RenewalReminderOptOut::resolveFormOptOut(['shirt_size' => 'M'], false)
		);

		// Stored params are untouched by resolve (no save path involved).
		$this->assertFalse(RenewalReminderOptOut::isOptedOutFromParams($params));
		$this->assertSame(0, $params[RenewalReminderOptOut::PARAM_KEY]);
	}

	/**
	 * Admin member-edit session data keeps the posted switch via fillFormDataIfMissing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminSessionDataKeepsPostedOptOut(): void
	{
		$field = RenewalReminderOptOut::FORM_FIELD;
		$session = [
			'user_id' => 9,
			$field    => 1,
		];

		$filled = RenewalReminderOptOut::fillFormDataIfMissing($session, false);

		$this->assertSame(1, $filled[$field]);
		$this->assertSame(
			1,
			RenewalReminderOptOut::resolveFormOptOut($filled, false)
		);
	}
}
