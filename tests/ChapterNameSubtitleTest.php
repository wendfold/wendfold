<?php

/**
 * Wendfold - chapter_name page-header subtitle on site pages.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\ChapterSubtitle;

require_once dirname(__DIR__) . '/com_wendfold/site/src/Helper/ChapterSubtitle.php';

/**
 * Site dashboard, events, and officers pass chapter_name as the page subtitle.
 *
 * @since  1.0.0
 */
final class ChapterNameSubtitleTest extends TestCase
{
	/**
	 * Paths that must read chapter options into the shared page header subtitle.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const TEMPLATE_PATHS = [
		'com_wendfold/site/tmpl/dashboard/default.php',
		'com_wendfold/site/tmpl/events/default.php',
		'com_wendfold/site/tmpl/officers/default.php',
	];

	/**
	 * Render page_header.php with the given subtitle and return the HTML.
	 *
	 * @param   string  $subtitle  Value passed as hcPageHeader subtitle.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function renderHeader(string $subtitle): string
	{
		$hcPageHeader = [
			'title'    => 'Page title',
			'subtitle' => $subtitle,
		];

		ob_start();
		require dirname(__DIR__) . '/com_wendfold/site/tmpl/_includes/page_header.php';

		return (string) ob_get_clean();
	}

	/**
	 * ChapterSubtitle::format cases for name/number combinations.
	 *
	 * @return  array<string, array{string, string, string}>
	 *
	 * @since   1.0.0
	 */
	public static function formatProvider(): array
	{
		return [
			'name and number' => ['Hill Country', '123', '#123 Hill Country'],
			'name only' => ['Hill Country', '', 'Hill Country'],
			'name with blank number' => ['Hill Country', '   ', 'Hill Country'],
			'empty name ignores number' => ['', '123', ''],
			'whitespace name ignores number' => ['   ', '123', ''],
			'both empty' => ['', '', ''],
			'trims both sides' => ['  Hill Country  ', ' 123 ', '#123 Hill Country'],
		];
	}

	/**
	 * Format builds "#number name", name alone, or empty when name is missing.
	 *
	 * @param   string  $name      Chapter name.
	 * @param   string  $number    Chapter number.
	 * @param   string  $expected  Expected subtitle text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('formatProvider')]
	public function testFormatCombinesNumberAndName(string $name, string $number, string $expected): void
	{
		self::assertSame($expected, ChapterSubtitle::format($name, $number));
	}

	/**
	 * A set chapter subtitle appears in the subtitle element.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSetChapterNameRendersSubtitle(): void
	{
		$html = $this->renderHeader(ChapterSubtitle::format('Hill Country', '123'));

		self::assertStringContainsString('class="hc-page__subtitle"', $html);
		self::assertStringContainsString('#123 Hill Country', $html);
	}

	/**
	 * An empty chapter name omits the subtitle element entirely.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyChapterNameOmitsSubtitleElement(): void
	{
		$html = $this->renderHeader(ChapterSubtitle::format('', '123'));

		self::assertStringNotContainsString('hc-page__subtitle', $html);
		self::assertStringNotContainsString('#123', $html);
		self::assertStringNotContainsString('Chapter', $html);
	}

	/**
	 * A hostile chapter subtitle is escaped and rendered as text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHostileChapterNameIsEscaped(): void
	{
		$hostile = '"><script>alert(1)</script>';
		$html    = $this->renderHeader(ChapterSubtitle::format($hostile, '9'));

		self::assertStringContainsString('class="hc-page__subtitle"', $html);
		self::assertStringContainsString(
			htmlspecialchars('#9 ' . $hostile, ENT_QUOTES, 'UTF-8'),
			$html
		);
		self::assertStringNotContainsString('<script>alert(1)</script>', $html);
	}

	/**
	 * Dashboard, events, and officers wire chapter_name and chapter_number.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteTemplatesWireChapterNameSubtitle(): void
	{
		foreach (self::TEMPLATE_PATHS as $relative)
		{
			$source = (string) file_get_contents(dirname(__DIR__) . '/' . $relative);

			self::assertStringContainsString(
				"ComponentHelper::getParams('com_wendfold')",
				$source,
				$relative . ' must read component params'
			);
			self::assertStringContainsString(
				"->get('chapter_name', '')",
				$source,
				$relative . ' must read chapter_name'
			);
			self::assertStringContainsString(
				"->get('chapter_number', '')",
				$source,
				$relative . ' must read chapter_number'
			);
			self::assertStringContainsString(
				'ChapterSubtitle::format(',
				$source,
				$relative . ' must format the chapter subtitle'
			);
			self::assertStringContainsString(
				"'subtitle' => \$chapterSubtitle",
				$source,
				$relative . ' must pass the formatted subtitle to the page header'
			);
		}
	}

	/**
	 * Officers keep Current Officer Roster when the chapter name is empty.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOfficersKeepsRosterSubtitleWhenChapterNameEmpty(): void
	{
		$officers = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/officers/default.php'
		);
		$headerBlock = '';

		if (preg_match('/\$hcPageHeader\s*=\s*\[[\s\S]*?\];/', $officers, $matches) === 1)
		{
			$headerBlock = $matches[0];
		}

		self::assertNotSame('', $headerBlock, 'officers page header block missing');
		self::assertStringContainsString(
			"Text::_('COM_WENDFOLD_OFFICERS_CURRENT_ROSTER')",
			$headerBlock
		);
		self::assertStringContainsString(
			'$chapterSubtitle !== \'\'',
			$headerBlock
		);
	}
}
