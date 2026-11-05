<?php

/**
 * Wendfold - PublishRowState classification for list and event publish.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\PublishRowState;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/PublishRowState.php';

/**
 * PublishRowState shared rules for ListController and EventModel.
 *
 * @since  1.0.0
 */
final class EventPublishRowStateTest extends TestCase
{
	/**
	 * Already at target and free of foreign checkout is unchanged.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAlreadyAtTargetIsUnchanged(): void
	{
		self::assertSame(
			PublishRowState::UNCHANGED,
			PublishRowState::classify(1, 0, true, 1, 42)
		);
	}

	/**
	 * Checked out by another user is failed even when already at target.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckedOutByOtherIsCheckedOut(): void
	{
		self::assertSame(
			PublishRowState::CHECKED_OUT,
			PublishRowState::classify(1, 99, true, 1, 42)
		);
		self::assertSame(
			PublishRowState::CHECKED_OUT,
			PublishRowState::classify(0, 99, true, 1, 42)
		);
	}

	/**
	 * Checked out by the current user can still change.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckedOutByCurrentUserNeedsChange(): void
	{
		self::assertSame(
			PublishRowState::NEEDS_CHANGE,
			PublishRowState::classify(0, 42, true, 1, 42)
		);
	}

	/**
	 * EventModel classifies under the locked row before parent::publish.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventModelClassifiesInsideLockedSection(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/EventModel.php'
		);

		self::assertMatchesRegularExpression(
			'/EventRowLock::lock\([\s\S]*?PublishRowState::classify\(/',
			$source
		);
		self::assertStringContainsString('PublishRowState::UNCHANGED', $source);
		self::assertStringContainsString('PublishRowState::CHECKED_OUT', $source);
	}
}
