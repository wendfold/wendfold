<?php

/**
 * Wendfold - ListController publish/delete message wiring.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ListControllerDoubles.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/BulkListMessages.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/PublishRowState.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Controller/ListController.php';

/**
 * Concrete ListController for message assertions.
 *
 * @since  1.0.0
 */
final class ListControllerHarness extends \Wendfold\Component\Wendfold\Administrator\Controller\ListController
{
	/** @var list<array{0: string, 1: string}> */
	public array $messages = [];

	/** @var list<array{key: string, count: int, type: string}> */
	public array $planned = [];

	/**
	 * @param   list<int>  $cid   Selected ids.
	 * @param   string     $task  Task name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function installInput(array $cid, string $task): void
	{
		$input = new \Wendfold\Tests\Support\ListInputDouble($cid);
		$app = new \Wendfold\Tests\Support\ListAppDouble($input);
		$this->app = $app;
		$this->setTaskForTest($task);
		$this->messages =& $app->messages;
		$this->planned = [];
	}

	/**
	 * @param   list<array{key: string, count: int, type: string}>  $messages  Planned messages.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function enqueueBulkMessages(array $messages): void
	{
		$this->planned = $messages;
		parent::enqueueBulkMessages($messages);
	}
}

/**
 * ListController reports successes and failures separately.
 *
 * @since  1.0.0
 */
final class ListControllerPublishResultsTest extends TestCase
{
	/**
	 * Reset publish-prune row fixtures between tests.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$GLOBALS['wendfold_list_table_rows'] = [];
	}

	/**
	 * All items publish: one success plural, no error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllSucceed(): void
	{
		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				self::assertSame(1, $value);
				self::assertCount(1, $pks);

				return true;
			},
			[4, 5, 6],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->messages[0][0]);
		self::assertStringNotContainsString('FAILED_', $controller->messages[0][0]);
	}

	/**
	 * Partial DB failure counts only the rows that changed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishPartialDbFailureCountsHonestly(): void
	{
		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				$pk = (int) reset($pks);

				if ($pk === 5)
				{
					$GLOBALS['wendfold_list_model_errors'] = ['SQLSTATE[45000]: Trigger blocked publish'];

					return false;
				}

				return true;
			},
			[4, 5, 6],
			'publish'
		);

		$controller->publish();

		self::assertCount(2, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->messages[0][0]);
		self::assertSame('error', $controller->messages[1][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->messages[1][0]);
		self::assertStringNotContainsString('SQLSTATE', $controller->messages[0][0]);
		self::assertStringNotContainsString('SQLSTATE', $controller->messages[1][0]);
		self::assertStringNotContainsString('Trigger blocked', $controller->messages[1][0]);
	}

	/**
	 * ACL refusals count as failures, not successes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAclRefusalCountedAsFailed(): void
	{
		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				$pks = [];
				$GLOBALS['wendfold_list_model_errors'] = [];

				return false;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->messages);
		self::assertSame('error', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->messages[0][0]);
	}

	/**
	 * All rows pruned with publish true (ACL-style) show failed, not no-change.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllAclPruneTrueShowsFailedNotNoChange(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 0],
			5 => ['published' => 0, 'checked_out' => 0],
		];

		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				$pks = [];

				return true;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertSame('error', $controller->planned[0]['type']);
		self::assertCount(1, $controller->messages);
		self::assertSame('error', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->messages[0][0]);
		self::assertStringNotContainsString('NO_ITEMS_NEEDED_CHANGING', $controller->messages[0][0]);
	}

	/**
	 * Mixed changed, ACL-pruned, already-at-target, and checked-out counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishMixedChangedAclUnchangedAndCheckedOut(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 0],
			5 => ['published' => 0, 'checked_out' => 0],
			6 => ['published' => 1, 'checked_out' => 0],
			7 => ['published' => 0, 'checked_out' => 99],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$pk = (int) reset($pks);

				if ($pk === 5)
				{
					$pks = [];

					return true;
				}

				$published[] = $pk;

				return true;
			},
			[4, 5, 6, 7],
			'publish'
		);

		$controller->publish();

		self::assertSame([4], $published);
		self::assertCount(2, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(1, $controller->planned[0]['count']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[1]['key']);
		self::assertSame(2, $controller->planned[1]['count']);
		self::assertCount(2, $controller->messages);
	}

	/**
	 * Partial trash uses the trashed success plural and failed-trashing plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTrashPartialFailureUsesTranslatedFailedPlural(): void
	{
		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				self::assertSame(-2, $value);
				$pk = (int) reset($pks);

				if ($pk === 5)
				{
					$GLOBALS['wendfold_list_model_errors'] = ['ERROR 1235: raw trash failure'];

					return false;
				}

				return true;
			},
			[4, 5, 6],
			'trash'
		);

		$controller->publish();

		self::assertCount(2, $controller->messages);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_TRASHED', $controller->messages[0][0]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_TRASHING', $controller->messages[1][0]);
		self::assertStringNotContainsString('ERROR 1235', implode("\n", array_column($controller->messages, 0)));
	}

	/**
	 * All publish attempts fail: only the failed-publishing plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllFail(): void
	{
		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				$pks = [];
				$GLOBALS['wendfold_list_model_errors'] = ['Denied'];

				return false;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->messages);
		self::assertSame('error', $controller->messages[0][1]);
		self::assertStringContainsString(
			'COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING',
			$controller->messages[0][0]
		);
	}

	/**
	 * Partial batch with one row checked out by another user: 2 changed, 1 failed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishPartialCheckedOutCountsAsFailed(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 0],
			5 => ['published' => 0, 'checked_out' => 99],
			6 => ['published' => 0, 'checked_out' => 0],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$pk = (int) reset($pks);
				$published[] = $pk;
				self::assertNotSame(5, $pk);

				return true;
			},
			[4, 5, 6],
			'publish'
		);

		$controller->publish();

		self::assertSame([4, 6], $published);
		self::assertCount(2, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[1]['key']);
		self::assertSame(1, $controller->planned[1]['count']);
		self::assertCount(2, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->messages[0][0]);
		self::assertSame('error', $controller->messages[1][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->messages[1][0]);
	}

	/**
	 * Every selected row checked out by another user: only the failed plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllCheckedOutCountsAsFailed(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 99],
			5 => ['published' => 0, 'checked_out' => 77],
		];

		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				$pks = [];

				return true;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertCount(1, $controller->messages);
		self::assertSame('error', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->messages[0][0]);
	}

	/**
	 * A row checked out by the current user still changes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishCheckedOutByCurrentUserStillChanges(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 42],
		];

		$controller = $this->controller(
			static function (array &$pks, int $value): bool {
				self::assertSame([4], array_values($pks));

				return true;
			},
			[4],
			'publish'
		);

		$controller->publish();

		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(1, $controller->planned[0]['count']);
		self::assertCount(1, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->messages[0][0]);
	}

	/**
	 * Already at target and not checked out does not increment the published count.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAlreadyAtStateNotCheckedOutIsNoOp(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 1, 'checked_out' => 0],
			5 => ['published' => 0, 'checked_out' => 0],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$pk = (int) reset($pks);
				$published[] = $pk;
				self::assertSame(5, $pk);

				return true;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertSame([5], $published);
		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(1, $controller->planned[0]['count']);
		self::assertCount(1, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->messages[0][0]);
		self::assertStringNotContainsString('FAILED_', $controller->messages[0][0]);
	}

	/**
	 * Mixed changed, already-at-target, and checked-out rows give exact counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishMixedChangedUnchangedAndCheckedOut(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 0],
			5 => ['published' => 1, 'checked_out' => 0],
			6 => ['published' => 0, 'checked_out' => 99],
			7 => ['published' => 0, 'checked_out' => 0],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$published[] = (int) reset($pks);

				return true;
			},
			[4, 5, 6, 7],
			'publish'
		);

		$controller->publish();

		self::assertSame([4, 7], $published);
		self::assertCount(2, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[1]['key']);
		self::assertSame(1, $controller->planned[1]['count']);
		self::assertCount(2, $controller->messages);
	}

	/**
	 * Mixed changed, at-target, checked-out, and missing ids: 1 changed, 2 failed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishMixedChangedUnchangedCheckedOutAndMissing(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 0, 'checked_out' => 0],
			5 => ['published' => 1, 'checked_out' => 0],
			6 => ['published' => 0, 'checked_out' => 99],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$published[] = (int) reset($pks);

				return true;
			},
			[4, 5, 6, 8],
			'publish'
		);

		// Id 8 was seeded as unpublished by the helper; remove it so load fails.
		unset($GLOBALS['wendfold_list_table_rows'][8]);

		$controller->publish();

		self::assertSame([4], $published);
		self::assertCount(2, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_PUBLISHED', $controller->planned[0]['key']);
		self::assertSame(1, $controller->planned[0]['count']);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[1]['key']);
		self::assertSame(2, $controller->planned[1]['count']);
		self::assertCount(2, $controller->messages);
	}

	/**
	 * Every selected id missing shows only the failed plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllMissingCountsAsFailed(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$published[] = (int) reset($pks);

				return true;
			},
			[4, 5],
			'publish'
		);

		unset($GLOBALS['wendfold_list_table_rows'][4], $GLOBALS['wendfold_list_table_rows'][5]);

		$controller->publish();

		self::assertSame([], $published);
		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_N_ITEMS_FAILED_PUBLISHING', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertCount(1, $controller->messages);
		self::assertSame('error', $controller->messages[0][1]);
	}

	/**
	 * Every selected row already at target shows the no-change notice.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPublishAllAlreadyAtTargetShowsNotice(): void
	{
		$GLOBALS['wendfold_list_table_rows'] = [
			4 => ['published' => 1, 'checked_out' => 0],
			5 => ['published' => 1, 'checked_out' => 0],
		];
		$published = [];

		$controller = $this->controller(
			static function (array &$pks, int $value) use (&$published): bool {
				$published[] = (int) reset($pks);

				return true;
			},
			[4, 5],
			'publish'
		);

		$controller->publish();

		self::assertSame([], $published);
		self::assertCount(1, $controller->planned);
		self::assertSame('COM_WENDFOLD_NO_ITEMS_NEEDED_CHANGING', $controller->planned[0]['key']);
		self::assertSame(2, $controller->planned[0]['count']);
		self::assertSame('notice', $controller->planned[0]['type']);
		self::assertCount(1, $controller->messages);
		self::assertSame('notice', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_NO_ITEMS_NEEDED_CHANGING', $controller->messages[0][0]);
	}

	/**
	 * Delete exceptions are caught: no 500, translated failure plural only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteDbExceptionDoesNotBubble(): void
	{
		$controller = $this->controller(
			null,
			[4, 5, 6],
			'delete',
			static function (array &$pks): bool {
				$pk = (int) reset($pks);

				if ($pk === 5)
				{
					throw new \RuntimeException('SQLSTATE[HY000]: forced delete failure');
				}

				return true;
			}
		);

		$controller->delete();

		self::assertCount(2, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_DELETED', $controller->messages[0][0]);
		self::assertSame('error', $controller->messages[1][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_DELETING', $controller->messages[1][0]);
		self::assertStringNotContainsString('SQLSTATE', implode("\n", array_column($controller->messages, 0)));
	}

	/**
	 * Partial delete with model false keeps success and failed plurals.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeletePartialFailureUsesTranslatedPlurals(): void
	{
		$controller = $this->controller(
			null,
			[4, 5, 6],
			'delete',
			static function (array &$pks): bool {
				$pk = (int) reset($pks);

				if ($pk === 5)
				{
					$GLOBALS['wendfold_list_model_errors'] = [
						'Could not delete event "Beta": The event could not be deleted.',
					];

					return false;
				}

				return true;
			}
		);

		$controller->delete();

		self::assertCount(2, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_DELETED', $controller->messages[0][0]);
		self::assertSame('error', $controller->messages[1][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_FAILED_DELETING', $controller->messages[1][0]);
		self::assertStringNotContainsString('Could not delete event', $controller->messages[1][0]);
	}

	/**
	 * Delete all succeed uses the deleted plural.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDeleteAllSucceed(): void
	{
		$controller = $this->controller(
			null,
			[4, 5],
			'delete',
			static function (array &$pks): bool {
				return true;
			}
		);

		$controller->delete();

		self::assertCount(1, $controller->messages);
		self::assertSame('message', $controller->messages[0][1]);
		self::assertStringContainsString('COM_WENDFOLD_N_ITEMS_DELETED', $controller->messages[0][0]);
	}

	/**
	 * @param   (callable(array, int): bool)|null  $publish  Publish callback.
	 * @param   list<int>                          $cid      Selected ids.
	 * @param   string                             $task     Task name.
	 * @param   (callable(array): bool)|null       $delete   Delete callback.
	 *
	 * @return  ListControllerHarness
	 *
	 * @since   1.0.0
	 */
	private function controller(
		?callable $publish,
		array $cid,
		string $task,
		?callable $delete = null
	): ListControllerHarness {
		$GLOBALS['wendfold_list_model_errors'] = [];
		$GLOBALS['wendfold_list_publish_callback'] = $publish;
		$GLOBALS['wendfold_list_delete_callback'] = $delete;
		$GLOBALS['wendfold_list_table_rows'] = $GLOBALS['wendfold_list_table_rows'] ?? [];

		foreach ($cid as $pk)
		{
			$pk = (int) $pk;

			if (!isset($GLOBALS['wendfold_list_table_rows'][$pk]))
			{
				$GLOBALS['wendfold_list_table_rows'][$pk] = [
					'published'   => 0,
					'checked_out' => 0,
				];
			}
		}

		$controller = new ListControllerHarness;
		$controller->installInput($cid, $task);

		return $controller;
	}
}
