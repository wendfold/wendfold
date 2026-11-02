<?php

/**
 * Wendfold - companion plugin enable selection for package install and update.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/pkg_wendfold/script.php';

/**
 * Locks which companion plugins the package installer enables.
 *
 * @since  1.0.0
 */
final class CompanionPluginEnableTest extends TestCase
{
	/**
	 * A companion that was not installed before an update is selected for enable.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNewlyAddedPluginIsEnabledOnUpdate(): void
	{
		$companions = \Pkg_WendfoldInstallerScript::companionDefinitions();
		$before = [
			\Pkg_WendfoldInstallerScript::companionExtensionKey('plugin', 'user', 'wendfold') => true,
			\Pkg_WendfoldInstallerScript::companionExtensionKey('plugin', 'task', 'wendfold') => true,
		];

		$missing = \Pkg_WendfoldInstallerScript::companionsMissingFrom($before, $companions);

		$this->assertCount(1, $missing);
		$this->assertSame('privacy', $missing[0]['folder']);
		$this->assertSame('plugin', $missing[0]['type']);
		$this->assertSame('wendfold', $missing[0]['element']);
	}

	/**
	 * An already-installed disabled companion is not selected for enable on update.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testExistingDisabledPluginStaysDisabledOnUpdate(): void
	{
		$companions = \Pkg_WendfoldInstallerScript::companionDefinitions();
		$before = [];

		foreach ($companions as $plugin)
		{
			$key = \Pkg_WendfoldInstallerScript::companionExtensionKey(
				$plugin['type'],
				$plugin['folder'],
				$plugin['element']
			);
			$before[$key] = true;
		}

		$this->assertSame([], \Pkg_WendfoldInstallerScript::companionsMissingFrom($before, $companions));
	}

	/**
	 * An already-installed enabled companion is not selected for enable on update.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testExistingEnabledPluginIsUntouchedOnUpdate(): void
	{
		$user = \Pkg_WendfoldInstallerScript::companionExtensionKey('plugin', 'user', 'wendfold');
		$task = \Pkg_WendfoldInstallerScript::companionExtensionKey('plugin', 'task', 'wendfold');
		$privacy = \Pkg_WendfoldInstallerScript::companionExtensionKey('plugin', 'privacy', 'wendfold');

		$before = [
			$user => true,
			$task => true,
			$privacy => true,
		];

		$missing = \Pkg_WendfoldInstallerScript::companionsMissingFrom(
			$before,
			\Pkg_WendfoldInstallerScript::companionDefinitions()
		);

		$this->assertSame([], $missing);
		$this->assertArrayHasKey($user, $before);
		$this->assertArrayHasKey($task, $before);
		$this->assertArrayHasKey($privacy, $before);
	}

	/**
	 * A fresh install still selects every companion that was not already present.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFreshInstallEnablesAllMissingCompanions(): void
	{
		$companions = \Pkg_WendfoldInstallerScript::companionDefinitions();

		$missing = \Pkg_WendfoldInstallerScript::companionsMissingFrom([], $companions);

		$this->assertSame($companions, $missing);
		$this->assertSame(['user', 'task', 'privacy'], array_column($missing, 'folder'));
	}

	/**
	 * Update enables new companions in postflight; install keeps enabling in install().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPackageScriptWiresUpdateEnableThroughPostflight(): void
	{
		$source = file_get_contents(dirname(__DIR__) . '/pkg_wendfold/script.php');

		$this->assertNotFalse($source);
		$this->assertStringContainsString('enableNewlyInstalledCompanions', $source);
		$this->assertMatchesRegularExpression(
			'/function\s+postflight\s*\([^)]*\)[^{]*\{[^}]*enableNewlyInstalledCompanions/s',
			$source
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function\s+update\s*\([^)]*\)[^{]*\{[^}]*enableNewlyInstalledCompanions/s',
			$source
		);
		$this->assertMatchesRegularExpression(
			'/function\s+install\s*\([^)]*\)[^{]*\{[\s\S]*companionsMissingFrom/s',
			$source
		);
		$this->assertStringContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $source);
		$this->assertStringNotContainsString('Factory::getDbo', $source);
	}
}
