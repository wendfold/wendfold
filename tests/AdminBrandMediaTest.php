<?php

/**
 * Wendfold - admin brand mark media and wiring.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\AdminBrand;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AdminBrand.php';

/**
 * Guarantees the W-fold mark ships in media, the manifest, and admin views.
 *
 * @since  1.0.0
 */
final class AdminBrandMediaTest extends TestCase
{
	/**
	 * Expected SHA-256 digests for the approved brand assets (byte-for-byte).
	 *
	 * @var    array<string, string>
	 * @since  1.0.0
	 */
	private const DIGESTS = [
		'wendfold-mark.svg'            => '25d93f20ba7ce322a2ef25f5184b644c54ab215d3e1b9375b1a830eb073c1cfe',
		'wendfold-mark-24-white.png'   => '56e711ee420a041de39a3437acbeaf394c62f8b774e1508685cd44e43e19d124',
		'wendfold-icon.svg'            => '081d113c28c443f2074799c60adb5a27eb3a67740f5b0239e0264308a918af84',
		'wendfold-icon-128.png'        => 'c38ba468678acd28d93761a2a2a8565d635a292366570e059610592aabf008c5',
	];

	/**
	 * Manifest lists the images media folder and the four brand files exist.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testManifestShipsBrandMediaFiles(): void
	{
		$root = dirname(__DIR__);
		$manifest = (string) file_get_contents($root . '/com_wendfold/wendfold.xml');

		self::assertStringContainsString('<folder>images</folder>', $manifest);
		self::assertStringContainsString('wendfold-mark-24-white.png', $manifest);
		self::assertStringContainsString('img="class:wendfold"', $manifest);

		foreach (AdminBrand::mediaFilenames() as $filename)
		{
			$path = $root . '/com_wendfold/media/images/' . $filename;
			self::assertFileExists($path, $filename . ' must exist under media/images');
			self::assertSame(
				self::DIGESTS[$filename],
				hash_file('sha256', $path),
				$filename . ' must match the approved digest'
			);
		}
	}

	/**
	 * Admin extension implements BootableExtensionInterface so Joomla calls boot().
	 *
	 * Without that interface, ExtensionManagerTrait skips boot() and the mark
	 * never reaches toolbar titles or WebAssetManager.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminComponentIsBootableAndRegistersBrand(): void
	{
		$root = dirname(__DIR__);
		$component = (string) file_get_contents(
			$root . '/com_wendfold/admin/src/Extension/WendfoldComponent.php'
		);
		$helper = (string) file_get_contents(
			$root . '/com_wendfold/admin/src/Helper/AdminBrand.php'
		);
		$assets = (string) file_get_contents(
			$root . '/com_wendfold/media/joomla.asset.json'
		);

		self::assertMatchesRegularExpression(
			'/implements\s+[^{]*\bBootableExtensionInterface\b/',
			$component,
			'WendfoldComponent must implement BootableExtensionInterface so boot() runs'
		);
		self::assertMatchesRegularExpression(
			'/function\s+boot\s*\(\s*ContainerInterface\s+\$container\s*\)/',
			$component,
			'boot() must use the BootableExtensionInterface ContainerInterface signature'
		);
		self::assertStringContainsString('use Joomla\CMS\Extension\BootableExtensionInterface;', $component);
		self::assertStringContainsString('AdminBrand::ensureAssets', $component);
		self::assertStringContainsString('AdminBrand::injectMarkIntoToolbarTitle', $component);
		self::assertStringContainsString('isClient(\'administrator\')', $component);
		self::assertStringContainsString('decorativeMarkHtml', $helper);
		self::assertStringContainsString('COM_WENDFOLD_BRAND_MARK_ALT', $helper);
		self::assertStringContainsString('wendfold-mark.svg', $helper);
		self::assertStringContainsString('com_wendfold.admin', $assets);
		self::assertStringContainsString('com_wendfold/admin.css', $assets);

		$markHtml = AdminBrand::decorativeMarkHtml();
		self::assertStringContainsString('wendfold-mark', $markHtml);
		self::assertStringContainsString('<svg', $markHtml);
		self::assertStringContainsString('aria-hidden="true"', $markHtml);

		$injected = AdminBrand::injectMarkIntoToolbarTitle(
			'<h1 class="page-title"><span class="icon-users icon-fw" aria-hidden="true"></span> Wendfold - Members</h1>'
		);
		self::assertStringContainsString('wendfold-mark', $injected);
		self::assertStringNotContainsString('icon-users', $injected);
	}
}
