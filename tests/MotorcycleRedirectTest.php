<?php

/**
 * Wendfold - motorcycle save/cancel redirects to one member-edit URL.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Wendfold\Component\Wendfold\Administrator\Controller\MotorcycleController;
use Wendfold\Tests\Support\MotorcycleAppDouble;
use Wendfold\Tests\Support\MotorcycleInputDouble;

/**
 * Save and Cancel must land on the member edit screen without a double view=.
 *
 * Covers the Joomla 6.1+ getRedirectUrlToList hook and the Joomla 6.0
 * left-to-right view_list . getRedirectToListAppend() expression order.
 *
 * @since  1.0.0
 */
final class MotorcycleRedirectTest extends TestCase
{
	private const CONTROLLER = 'com_wendfold/admin/src/Controller/MotorcycleController.php';

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function setUpBeforeClass(): void
	{
		require_once __DIR__ . '/support/MotorcycleControllerDoubles.php';
		require_once dirname(__DIR__) . '/' . self::CONTROLLER;
	}

	/**
	 * The member-edit redirect URL has a single view=member and the profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberEditRedirectUrlIsASingleCleanRoute(): void
	{
		$url = MotorcycleController::memberEditRedirectUrl(42);

		self::assertSame(
			'index.php?option=com_wendfold&view=member&layout=edit&id=42',
			$url
		);
		self::assertSame(1, substr_count($url, 'view='));
		self::assertStringNotContainsString('view=members', $url);
	}

	/**
	 * The 6.1+ hook returns the clean member-edit URL and does not append view=.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGetRedirectUrlToListReturnsTheCleanMemberEditUrl(): void
	{
		$controller = self::controllerWithProfileId(42);
		$url = self::invoke($controller, 'getRedirectUrlToList');

		self::assertSame(MotorcycleController::memberEditRedirectUrl(42), $url);
		self::assertSame(1, substr_count($url, 'view='));
		self::assertStringNotContainsString('view=members', $url);
	}

	/**
	 * Joomla 6.0 evaluates view_list before getRedirectToListAppend().
	 *
	 * prepareMemberEditListRedirect() (from save/cancel) must retarget
	 * view_list first; setting it inside the append is too late.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testJoomla60ExpressionOrderUsesPreparedViewList(): void
	{
		$controller = self::controllerWithProfileId(42);
		self::invoke($controller, 'prepareMemberEditListRedirect');

		$viewList = (new ReflectionClass($controller))->getProperty('view_list');

		// Exact 6.0 FormController order: read view_list, then call append.
		$url = 'index.php?option=com_wendfold&view='
			. $viewList->getValue($controller)
			. self::invoke($controller, 'getRedirectToListAppend');

		self::assertSame(MotorcycleController::memberEditRedirectUrl(42), $url);
		self::assertSame(1, substr_count($url, 'view='));
		self::assertStringNotContainsString('view=members', $url);
		self::assertStringNotContainsString('view=', self::invoke($controller, 'getRedirectToListAppend'));
	}

	/**
	 * Without prepare, the 6.0 left-to-right order keeps view=members.
	 *
	 * Locks the bug that setting view_list only inside the append cannot fix.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testJoomla60ExpressionOrderWithoutPrepareKeepsMembersView(): void
	{
		$controller = self::controllerWithProfileId(42);
		$viewList = (new ReflectionClass($controller))->getProperty('view_list');

		$url = 'index.php?option=com_wendfold&view='
			. $viewList->getValue($controller)
			. self::invoke($controller, 'getRedirectToListAppend');

		self::assertSame(
			'index.php?option=com_wendfold&view=members&layout=edit&id=42',
			$url
		);
	}

	/**
	 * save() and cancel() prepare the member-edit list redirect first.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSaveAndCancelPrepareTheMemberEditListRedirect(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/' . self::CONTROLLER);

		self::assertMatchesRegularExpression(
			'/function\s+save\s*\([^)]*\)[^{]*\{[^}]*prepareMemberEditListRedirect\s*\(\s*\)/s',
			$source
		);
		self::assertMatchesRegularExpression(
			'/function\s+cancel\s*\([^)]*\)[^{]*\{[^}]*prepareMemberEditListRedirect\s*\(\s*\)/s',
			$source
		);
		self::assertStringContainsString('function getRedirectUrlToList()', $source);
		self::assertStringContainsString('function getRedirectToListAppend()', $source);
		self::assertStringNotContainsString("return '&view=member", $source);
	}

	/**
	 * @param   int  $profileId  Profile id exposed on the request.
	 *
	 * @return  MotorcycleController
	 *
	 * @since   1.0.0
	 */
	private static function controllerWithProfileId(int $profileId): MotorcycleController
	{
		$controller = (new ReflectionClass(MotorcycleController::class))->newInstanceWithoutConstructor();
		$app = new MotorcycleAppDouble(new MotorcycleInputDouble($profileId));

		(new ReflectionClass($controller))->getProperty('app')->setValue($controller, $app);
		(new ReflectionClass($controller))->getProperty('view_list')->setValue($controller, 'members');
		(new ReflectionClass($controller))->getProperty('option')->setValue($controller, 'com_wendfold');

		return $controller;
	}

	/**
	 * @param   object  $controller  Controller instance.
	 * @param   string  $method      Protected method name.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	private static function invoke(object $controller, string $method): mixed
	{
		return (new ReflectionMethod($controller, $method))->invoke($controller);
	}
}
