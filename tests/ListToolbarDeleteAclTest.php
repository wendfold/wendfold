<?php

/**
 * Wendfold - list toolbar delete/trash ACL gates.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Delete and trash toolbar buttons must require the matching ACL action.
 *
 * @since  1.0.0
 */
final class ListToolbarDeleteAclTest extends TestCase
{
	/**
	 * Events delete uses ContentHelper core.delete; trash uses core.edit.state.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventsToolbarGatesTrashAndDelete(): void
	{
		$source = self::read('com_wendfold/admin/src/View/Events/HtmlView.php');

		self::assertStringContainsString('ContentHelper::getActions(\'com_wendfold\')', $source);
		self::assertMatchesRegularExpression(
			'/if\s*\(\s*\$canDo->get\(\'core\.edit\.state\'\)\s*\)\s*\{[^}]*trash\(/s',
			$source
		);
		self::assertMatchesRegularExpression(
			'/if\s*\(\s*\$canDo->get\(\'core\.delete\'\)\s*\)\s*\{[^}]*delete\(/s',
			$source
		);
	}

	/**
	 * Other list views that expose delete gate it on core.delete.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListViewsGateDeleteButtons(): void
	{
		$views = [
			'com_wendfold/admin/src/View/Members/HtmlView.php',
			'com_wendfold/admin/src/View/Locations/HtmlView.php',
			'com_wendfold/admin/src/View/Dues/HtmlView.php',
			'com_wendfold/admin/src/View/Officers/HtmlView.php',
			'com_wendfold/admin/src/View/Awarddefinitions/HtmlView.php',
			'com_wendfold/admin/src/View/Eventtypes/HtmlView.php',
			'com_wendfold/admin/src/View/Membershiptypes/HtmlView.php',
			'com_wendfold/admin/src/View/Metricdefinitions/HtmlView.php',
			'com_wendfold/admin/src/View/Officerroles/HtmlView.php',
		];

		foreach ($views as $relative)
		{
			$source = self::read($relative);
			$hasDelete = str_contains($source, '->delete(')
				|| str_contains($source, 'deleteList(');

			self::assertTrue($hasDelete, $relative . ' should declare a delete control.');
			self::assertTrue(
				str_contains($source, "get('core.delete')")
					|| str_contains($source, "authorise('core.delete', 'com_wendfold')"),
				$relative . ' must gate delete on core.delete.'
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
