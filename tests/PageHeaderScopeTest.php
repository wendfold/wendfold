<?php

/**
 * Wendfold - page_header.php must not overwrite the caller's $item.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Regression: the page header include shares the template scope.
 *
 * @since  1.0.0
 */
final class PageHeaderScopeTest extends TestCase
{
	/**
	 * Including page_header.php leaves a pre-set $item object unchanged.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPageHeaderDoesNotOverwriteCallerItem(): void
	{
		$item = (object) [
			'id' => 42,
			'title' => 'Overnight trip',
		];
		$hcPageHeader = [
			'title' => 'Header title',
			'subtitle' => 'Chapter',
			'metaItems' => [
				'Saturday',
				['html' => '<span title="Kick Stands Up">Departure</span>'],
				'Campground',
			],
		];

		ob_start();
		require dirname(__DIR__) . '/com_wendfold/site/tmpl/_includes/page_header.php';
		$html = (string) ob_get_clean();

		self::assertIsObject($item);
		self::assertSame(42, $item->id);
		self::assertSame('Overnight trip', $item->title);
		self::assertStringContainsString('Header title', $html);
		self::assertStringContainsString('Departure', $html);
		self::assertStringNotContainsString('wfHeader', $html);
	}

	/**
	 * Header-private locals are unset so they do not leak into the caller.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPageHeaderUnsetsPrivateLocals(): void
	{
		$item = (object) ['id' => 7];
		$hcPageHeader = [
			'title' => 'Scoped',
			'metaItems' => ['One', 'Two'],
		];

		ob_start();
		require dirname(__DIR__) . '/com_wendfold/site/tmpl/_includes/page_header.php';
		ob_end_clean();

		self::assertFalse(isset($wfHeaderConfig));
		self::assertFalse(isset($wfHeaderTitle));
		self::assertFalse(isset($wfHeaderMetaItems));
		self::assertFalse(isset($wfHeaderMetaEntry));
		self::assertSame(7, $item->id);
	}
}
