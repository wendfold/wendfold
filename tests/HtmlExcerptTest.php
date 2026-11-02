<?php

/**
 * Wendfold - plain-text HTML teasers decode entities before escaping.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;

/**
 * Locks teaser decoding for &, quotes, tags, and multibyte text.
 *
 * @since  1.0.0
 */
final class HtmlExcerptTest extends TestCase
{
	/**
	 * An ampersand entity becomes a single escaped ampersand, not &amp;amp;.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAmpersandEntityIsDecodedThenEscapedOnce(): void
	{
		$html = '<p>Bold and a link - Tom &amp; Jerry one two three</p>';

		self::assertSame(
			'Bold and a link - Tom &amp; Jerry one two three',
			OutputEscape::htmlExcerpt($html, 100)
		);
		self::assertStringNotContainsString('&amp;amp;', OutputEscape::htmlExcerpt($html, 100));
	}

	/**
	 * Quotes survive decoding and are escaped for HTML text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testQuotesAreDecodedThenEscaped(): void
	{
		$html = '<p>He said &quot;hello&quot; &amp; left</p>';

		self::assertSame(
			'He said &quot;hello&quot; &amp; left',
			OutputEscape::htmlExcerpt($html, 100)
		);
	}

	/**
	 * Angle brackets from tags are removed; literal &lt; text stays escaped.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTagsAreStrippedAndLiteralLtStaysEscaped(): void
	{
		self::assertSame(
			'Bold text',
			OutputEscape::htmlExcerpt('<p><strong>Bold</strong> text</p>', 100)
		);
		self::assertSame(
			'a &lt; b',
			OutputEscape::htmlExcerpt('<p>a &lt; b</p>', 100)
		);
	}

	/**
	 * Truncation uses character length so multibyte text is not split mid-glyph.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMultibyteTextTruncatesOnCharacterBoundaries(): void
	{
		$html = '<p>' . str_repeat('日本語', 20) . '</p>';
		$excerpt = OutputEscape::htmlExcerpt($html, 10);

		self::assertSame(htmlspecialchars(str_repeat('日本語', 3) . '日...', \ENT_QUOTES, 'UTF-8'), $excerpt);
		self::assertSame(10 + 3, mb_strlen(html_entity_decode(strip_tags($excerpt), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
	}

	/**
	 * Empty editor leftovers have no visible content and yield no teaser.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptyEditorMarkupHasNoVisibleContentOrExcerpt(): void
	{
		$cases = [
			'<p></p>',
			'<p>&nbsp;</p>',
			'<p><br></p>',
			" \u{00A0} ",
		];

		foreach ($cases as $html)
		{
			self::assertFalse(OutputEscape::hasVisibleContent($html), $html);
			self::assertSame('', OutputEscape::htmlExcerpt($html, 100), $html);
		}
	}

	/**
	 * Real text and media-only HTML count as visible content.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRealTextAndImgOnlyDescriptionsAreVisible(): void
	{
		self::assertTrue(OutputEscape::hasVisibleContent('<p>Ride notes</p>'));
		self::assertSame('Ride notes', OutputEscape::htmlExcerpt('<p>Ride notes</p>', 100));

		$imgOnly = '<p><img src="media/com_wendfold/route.png" alt=""></p>';
		self::assertTrue(OutputEscape::hasVisibleContent($imgOnly));
		self::assertSame('', OutputEscape::htmlExcerpt($imgOnly, 100));
	}

	/**
	 * Paragraphs and list items leave a space so words are not glued together.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testParagraphsAndListItemsInsertSpaces(): void
	{
		$html = '<p>café</p><ul><li>one</li><li>two</li></ul>';

		self::assertSame('café one two', OutputEscape::htmlExcerpt($html, 100));
		self::assertStringNotContainsString('caféone', OutputEscape::htmlExcerpt($html, 100));
	}

	/**
	 * Line breaks and headings separate words the same way.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBrAndHeadingsInsertSpaces(): void
	{
		self::assertSame(
			'line one line two',
			OutputEscape::htmlExcerpt('line one<br>line two', 100)
		);
		self::assertSame(
			'line one line two',
			OutputEscape::htmlExcerpt('line one<br/>line two', 100)
		);
		self::assertSame(
			'Title Body',
			OutputEscape::htmlExcerpt('<h2 class="x">Title</h2><p>Body</p>', 100)
		);
	}

	/**
	 * Table cells separate words; attributes and case do not matter.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTableCellsInsertSpaces(): void
	{
		$html = '<TABLE><TR><TD class="c">alpha</TD><TD>beta</TD></TR></TABLE>';

		self::assertSame('alpha beta', OutputEscape::htmlExcerpt($html, 100));
	}

	/**
	 * Inline tags do not insert spaces around their text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInlineTagsDoNotInsertSpaces(): void
	{
		self::assertSame('Bold', OutputEscape::htmlExcerpt('Bo<b>ld</b>', 100));
		self::assertSame('Bold', OutputEscape::htmlExcerpt('Bo<strong>ld</strong>', 100));
		self::assertSame('Italic', OutputEscape::htmlExcerpt('It<em>alic</em>', 100));
		self::assertSame('link', OutputEscape::htmlExcerpt('<a href="/x">link</a>', 100));
		self::assertSame('span', OutputEscape::htmlExcerpt('<span>span</span>', 100));
	}

	/**
	 * Entities are still decoded once when block tags insert spaces.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEntitiesStillDecodedOnceAcrossBlocks(): void
	{
		$html = '<p>Tom &amp; Jerry</p><p>more</p>';

		self::assertSame('Tom &amp; Jerry more', OutputEscape::htmlExcerpt($html, 100));
		self::assertStringNotContainsString('&amp;amp;', OutputEscape::htmlExcerpt($html, 100));
	}

	/**
	 * Truncation still cuts on character length after block spacing.
	 *
	 * Words stay separated; the cut is a character index, so a long run of
	 * spaced words truncates cleanly without gluing adjacent blocks.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTruncationStillOnCharacterBoundariesWithBlocks(): void
	{
		$html = '<p>café</p><ul><li>one</li><li>two</li><li>three</li></ul>';

		// Plain text is "café one two three" (18 chars). Cut at 8 → "café one".
		$excerpt = OutputEscape::htmlExcerpt($html, 8);

		self::assertSame('café one...', $excerpt);
		self::assertStringNotContainsString('caféone', $excerpt);
	}

	/**
	 * hasVisibleContent stays false for empty editor leftovers after spacing.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHasVisibleContentUnchangedForEmptyBlocks(): void
	{
		self::assertFalse(OutputEscape::hasVisibleContent('<p></p><ul><li></li></ul>'));
		self::assertFalse(OutputEscape::hasVisibleContent('<div><br/></div>'));
		self::assertTrue(OutputEscape::hasVisibleContent('<p>café</p><ul><li>one</li></ul>'));
	}

	/**
	 * Events list and trip itinerary templates call the shared helpers.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListTemplatesUseHtmlExcerptHelper(): void
	{
		$events = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/site/tmpl/events/default.php');
		$trip = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/site/tmpl/trip/default.php');
		$event = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/site/tmpl/event/default.php');

		self::assertStringContainsString('OutputEscape::htmlExcerpt((string) $item->description, 100)', $events);
		self::assertStringContainsString("OutputEscape::htmlExcerpt((string) (\$childEvent->description ?? ''), 140)", $trip);
		self::assertStringContainsString('OutputEscape::hasVisibleContent', $event);
		self::assertStringContainsString('OutputEscape::hasVisibleContent', $trip);
		self::assertStringContainsString('OutputEscape::hasVisibleContent', $events);
	}
}
