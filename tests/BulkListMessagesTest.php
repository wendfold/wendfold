<?php

/**
 * Wendfold - honest admin list batch result messages.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\BulkListMessages;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/BulkListMessages.php';

/**
 * BulkListMessages counts successes and failures for list controllers.
 *
 * @since  1.0.0
 */
final class BulkListMessagesTest extends TestCase
{
	/**
	 * All selected items publish successfully.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllSucceed(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD', 1, 3, 3, 0);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $messages[0]['key']);
		self::assertSame(3, $messages[0]['count']);
		self::assertSame(BulkListMessages::TYPE_SUCCESS, $messages[0]['type']);
	}

	/**
	 * Partial publish uses success and failed-publishing plurals only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishPartialFailure(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD', 1, 3, 2, 1);

		self::assertCount(2, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $messages[0]['key']);
		self::assertSame(2, $messages[0]['count']);
		self::assertSame(BulkListMessages::TYPE_SUCCESS, $messages[0]['type']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $messages[1]['key']);
		self::assertSame(1, $messages[1]['count']);
		self::assertSame(BulkListMessages::TYPE_ERROR, $messages[1]['type']);
		self::assertArrayNotHasKey('raw', $messages[1]);
	}

	/**
	 * Every selected item fails to publish.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllFail(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD', 1, 2, 0, 2);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $messages[0]['key']);
		self::assertSame(2, $messages[0]['count']);
	}

	/**
	 * Unpublish, archive, and trash use matching failed plurals.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEveryStateHasTranslatedFailurePlural(): void
	{
		$cases = [
			[0, 'COM_WENDFOLD_N_ITEMS_UNPUBLISHED', 'COM_WENDFOLD_N_ITEMS_FAILED_UNPUBLISHING'],
			[2, 'COM_WENDFOLD_N_ITEMS_ARCHIVED', 'COM_WENDFOLD_N_ITEMS_FAILED_ARCHIVING'],
			[-2, 'COM_WENDFOLD_N_ITEMS_TRASHED', 'COM_WENDFOLD_N_ITEMS_FAILED_TRASHING'],
		];

		foreach ($cases as [$value, $successKey, $failureKey])
		{
			$messages = BulkListMessages::forPublish('COM_WENDFOLD', $value, 3, 2, 1);

			self::assertCount(2, $messages, 'value ' . $value);
			self::assertSame($successKey, $messages[0]['key']);
			self::assertSame(2, $messages[0]['count']);
			self::assertSame($failureKey, $messages[1]['key']);
			self::assertSame(1, $messages[1]['count']);
			self::assertArrayNotHasKey('raw', $messages[0]);
			self::assertArrayNotHasKey('raw', $messages[1]);
		}
	}

	/**
	 * ACL-style failures with zero successes show only the failure plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAclRefusedCountedAsFailedNotSucceeded(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD', -2, 2, 0, 2);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_TRASHING', $messages[0]['key']);
		self::assertSame(2, $messages[0]['count']);
	}

	/**
	 * Delete all succeed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteAllSucceed(): void
	{
		$messages = BulkListMessages::forDelete('COM_WENDFOLD', 2, 2, 0);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_DELETED', $messages[0]['key']);
		self::assertSame(2, $messages[0]['count']);
	}

	/**
	 * Partial delete keeps success and a translated failed-deleting plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeletePartialFailure(): void
	{
		$messages = BulkListMessages::forDelete('COM_WENDFOLD', 3, 2, 1);

		self::assertCount(2, $messages);
		self::assertSame(BulkListMessages::TYPE_SUCCESS, $messages[0]['type']);
		self::assertSame(2, $messages[0]['count']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_DELETING', $messages[1]['key']);
		self::assertSame(1, $messages[1]['count']);
		self::assertSame(BulkListMessages::TYPE_ERROR, $messages[1]['type']);
		self::assertArrayNotHasKey('raw', $messages[1]);
	}

	/**
	 * Delete all fail shows only the failed-deleting plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteAllFail(): void
	{
		$messages = BulkListMessages::forDelete('COM_WENDFOLD', 2, 0, 2);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_DELETING', $messages[0]['key']);
		self::assertSame(2, $messages[0]['count']);
	}

	/**
	 * Officers text_prefix uses the officers failure keys.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOfficersFailedPublishingUsesPrefix(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD_OFFICERS', 1, 1, 0, 1);

		self::assertSame(
			'COM_WENDFOLD_OFFICERS_N_ITEMS_FAILED_PUBLISHING',
			$messages[0]['key']
		);
	}

	/**
	 * All rows already at target emit the no-change notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllUnchangedShowsNotice(): void
	{
		$messages = BulkListMessages::forPublish('COM_WENDFOLD', 1, 3, 0, 0);

		self::assertCount(1, $messages);
		self::assertSame('COM_WENDFOLD_NO_ITEMS_NEEDED_CHANGING', $messages[0]['key']);
		self::assertSame(3, $messages[0]['count']);
		self::assertSame(BulkListMessages::TYPE_NOTICE, $messages[0]['type']);
	}
}
