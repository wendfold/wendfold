<?php

/**
 * Wendfold - Issue #112 Member Number label locks.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\MemberIdLabel;

/**
 * Members list uses Options member_id_label, with a language-key fallback.
 *
 * @since  1.0.0
 */
final class MemberNumberLabelTest extends TestCase
{
	/**
	 * A configured Options label wins over the language fallback.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testConfiguredMemberIdLabelIsUsed(): void
	{
		self::assertSame(
			'Card Number',
			MemberIdLabel::forListHeader('Card Number', 'Member Number')
		);
		self::assertSame(
			'Card Number',
			MemberIdLabel::forListHeader('  Card Number  ', 'Member Number')
		);
	}

	/**
	 * An empty Options value falls back to the translated language string.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyMemberIdLabelFallsBackToLanguageString(): void
	{
		self::assertSame(
			'Member Number',
			MemberIdLabel::forListHeader('', 'Member Number')
		);
		self::assertSame(
			'Member Number',
			MemberIdLabel::forListHeader('   ', 'Member Number')
		);
	}

	/**
	 * grid.sort receives escaped HTML so free text is never Text::_()'d.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGridSortTitleIsEscapedHtmlNotLanguageKey(): void
	{
		$title = MemberIdLabel::gridSortTitle('Member <Number>');

		self::assertStringStartsWith('<', $title);
		self::assertSame(
			'<span>Member &lt;Number&gt;</span>',
			$title
		);
	}

	/**
	 * The members list wires Options + language fallback into grid.sort.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembersListUsesMemberIdLabelHelper(): void
	{
		$adminIni = self::read('com_wendfold/admin/language/en-GB/com_wendfold.ini');
		$template = self::read('com_wendfold/admin/tmpl/members/default.php');

		self::assertStringContainsString(
			'COM_WENDFOLD_MEMBER_NUMBER="Member Number"',
			$adminIni
		);
		self::assertStringContainsString('MemberIdLabel::forListHeader', $template);
		self::assertStringContainsString("get('member_id_label'", $template);
		self::assertStringContainsString("Text::_('COM_WENDFOLD_MEMBER_NUMBER')", $template);
		self::assertStringContainsString('MemberIdLabel::gridSortTitle', $template);
		self::assertStringContainsString(
			"HTMLHelper::_('grid.sort', \$memberIdSortTitle, 'p.member_number'",
			$template
		);
		self::assertStringNotContainsString(
			"HTMLHelper::_('grid.sort', 'COM_WENDFOLD_MEMBER_NUMBER'",
			$template
		);
		self::assertStringNotContainsString(
			"HTMLHelper::_('grid.sort', htmlspecialchars(\$memberIdLabel",
			$template
		);
	}

	/**
	 * Other members-list Text::_ keys used on the page exist in the admin ini.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembersListComponentKeysAreDefined(): void
	{
		$adminIni = self::read('com_wendfold/admin/language/en-GB/com_wendfold.ini');
		$template = self::read('com_wendfold/admin/tmpl/members/default.php');

		preg_match_all("/Text::_\\('((?:COM_WENDFOLD_)[^']+)'\\)/", $template, $textMatches);
		preg_match_all("/HTMLHelper::_\\('grid\\.sort', '((?:COM_WENDFOLD_)[^']+)'/", $template, $sortMatches);

		$keys = array_values(array_unique(array_merge($textMatches[1], $sortMatches[1])));

		self::assertNotEmpty($keys);
		self::assertContains('COM_WENDFOLD_MEMBER_NUMBER', $keys);

		foreach ($keys as $key)
		{
			self::assertMatchesRegularExpression(
				'/^' . preg_quote($key, '/') . '=/m',
				$adminIni,
				$key . ' is used on the members list but missing from the admin language file.'
			);
		}
	}

	/**
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function read(string $relative): string
	{
		$contents = file_get_contents(dirname(__DIR__) . '/' . $relative);

		if ($contents === false)
		{
			self::fail('Could not read ' . $relative);
		}

		return $contents;
	}
}
