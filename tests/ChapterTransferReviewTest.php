<?php

/**
 * Wendfold - Chapter transfer review only changes a flagged member once.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\ChapterTransferReview;

/**
 * Locks Keep and Remove from Roster to flagged profiles, one log row each.
 *
 * @since  1.0.0
 */
final class ChapterTransferReviewTest extends TestCase
{
	private const MODEL = 'com_wendfold/admin/src/Model/SyncModel.php';

	private const CONTROLLER = 'com_wendfold/admin/src/Controller/SyncController.php';

	private const VIEW = 'com_wendfold/admin/src/View/Sync/HtmlView.php';

	private const TEMPLATE = 'com_wendfold/admin/tmpl/sync/default.php';

	private const LANGUAGE = 'com_wendfold/admin/language/en-GB/com_wendfold.ini';

	/**
	 * A member who is not flagged is not updated.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAnUnflaggedMemberIsLeftUnchanged(): void
	{
		$published = [
			'chapter_switch_flagged' => 0,
			'published'              => 1,
		];
		$missing = [
			'published' => 1,
		];
		$log = [];

		self::assertFalse($this->resolve($published, $log, 'remove'));
		self::assertFalse($this->resolve($missing, $log, 'keep'));
		self::assertSame([], $log);
		self::assertSame(1, $published['published']);
		self::assertSame(1, $missing['published']);
		self::assertFalse(ChapterTransferReview::wrote(0));
	}

	/**
	 * Repeating Keep or Remove does not add a second sync log row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testARepeatedResolveDoesNotLogAgain(): void
	{
		$kept = [
			'chapter_switch_flagged' => 1,
			'published'              => 1,
		];
		$removed = [
			'chapter_switch_flagged' => '1',
			'published'              => 1,
		];
		$keepLog = [];
		$removeLog = [];

		self::assertTrue($this->resolve($kept, $keepLog, 'keep'));
		self::assertFalse($this->resolve($kept, $keepLog, 'keep'));
		self::assertTrue($this->resolve($removed, $removeLog, 'remove'));
		self::assertFalse($this->resolve($removed, $removeLog, 'remove'));

		self::assertSame(['keep'], $keepLog);
		self::assertSame(['remove'], $removeLog);
		self::assertSame(1, $kept['published']);
		self::assertSame(0, $kept['chapter_switch_flagged']);
		self::assertSame(0, $removed['published']);
		self::assertSame(0, $removed['chapter_switch_flagged']);
	}

	/**
	 * The model updates only a flagged row and logs only after that update.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTheModelUpdatesOnlyAFlaggedRowAndLogsOnce(): void
	{
		$body = $this->functionBody($this->read(self::MODEL), 'resolveFlaggedMember');
		$wroteAt = strpos($body, 'ChapterTransferReview::wrote(');
		$insertAt = strpos($body, '->insert(');

		self::assertStringContainsString('ChapterTransferReview::isOpen(', $body);
		self::assertStringContainsString("quoteName('chapter_switch_flagged') . ' = 1'", $body);
		self::assertIsInt($wroteAt);
		self::assertIsInt($insertAt);
		self::assertLessThan($insertAt, $wroteAt);

		$controller = $this->read(self::CONTROLLER);

		self::assertSame(2, substr_count($controller, "Text::_('COM_WENDFOLD_SYNC_TRANSFER_NOT_FLAGGED')"));
		self::assertStringNotContainsString("Text::_('COM_WENDFOLD_SYNC_TRANSFER_ERROR')", $controller);
	}

	/**
	 * The review list includes an unpublished member and a member with no account.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTheReviewListIncludesEveryFlaggedMember(): void
	{
		$view = $this->read(self::VIEW);
		$start = strpos($view, 'members flagged for chapter switch');
		$end = $start === false ? false : strpos($view, '$this->flaggedMembers', $start);

		self::assertIsInt($start);
		self::assertIsInt($end);
		self::assertLessThan($end, $start);

		$query = substr($view, $start, $end - $start);

		self::assertStringContainsString("'LEFT'", $query);
		self::assertStringContainsString("quoteName('p.chapter_switch_flagged') . ' = 1'", $query);
		self::assertStringNotContainsString("'INNER'", $query);
		self::assertStringNotContainsString('published', $query);
	}

	/**
	 * The lapsed-cleanup text states the 30-day grace period.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTheLapsedCleanupTextStatesTheGracePeriod(): void
	{
		$language = $this->read(self::LANGUAGE);
		$template = $this->read(self::TEMPLATE);

		self::assertStringContainsString(
			'COM_WENDFOLD_SYNC_LAPSED_DESCRIPTION="Unpublish member records whose renewal date'
			. ' is more than 30 days ago. Publishing a member again reverses it."',
			$language
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_SYNC_LAPSED_CONFIRM="This will unpublish members whose renewal date'
			. ' is more than 30 days ago. Are you sure?"',
			$language
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_SYNC_REMOVE_CONFIRM="Unpublish this member and clear the chapter transfer flag?'
			. ' The member record is kept and can be published again."',
			$language
		);
		self::assertStringNotContainsString('lapsedResult', $template);
		self::assertStringNotContainsString(
			'COM_WENDFOLD_SYNC_REMOVE_CONFIRM="Remove this member from the roster? This cannot be undone."',
			$language
		);
		self::assertStringNotContainsString('renewal date has passed', $language);
	}

	/**
	 * Apply one review the way the model does: open flag, then one log row.
	 *
	 * @param   array<string, int|string>  $row     Profile fields, updated in place.
	 * @param   array<int, string>         $log     Actions that were recorded.
	 * @param   string                     $action  keep or remove.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function resolve(array &$row, array &$log, string $action): bool
	{
		$profile = (object) $row;

		if (!ChapterTransferReview::isOpen($profile))
		{
			return false;
		}

		$affected = (int) $row['chapter_switch_flagged'] === 1 ? 1 : 0;

		if ($action === 'remove')
		{
			$row['published'] = 0;
		}

		$row['chapter_switch_flagged'] = 0;

		if (!ChapterTransferReview::wrote($affected))
		{
			return false;
		}

		$log[] = $action;

		return true;
	}

	/**
	 * Reads a repository file.
	 *
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function read(string $relative): string
	{
		$contents = file_get_contents(dirname(__DIR__) . '/' . $relative);

		if ($contents === false)
		{
			self::fail('Unreadable ' . $relative);
		}

		return $contents;
	}

	/**
	 * Body of one function, without its signature.
	 *
	 * @param   string  $source  PHP source.
	 * @param   string  $name    Function name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function functionBody(string $source, string $name): string
	{
		$pattern = '/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*(?::\s*[^{\n]+)?\s*\{/';

		if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE))
		{
			self::fail('No function ' . $name);
		}

		$open = $match[0][1] + strlen($match[0][0]) - 1;
		$length = strlen($source);
		$depth = 0;

		for ($i = $open; $i < $length; $i++)
		{
			$char = $source[$i];

			if ($char === '{')
			{
				$depth++;
				continue;
			}

			if ($char === '}')
			{
				$depth--;

				if ($depth === 0)
				{
					return substr($source, $open + 1, $i - $open - 1);
				}
			}
		}

		self::fail('Unclosed function ' . $name);
	}
}
