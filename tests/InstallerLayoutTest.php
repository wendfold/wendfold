<?php

/**
 * Wendfold - installer script layouts.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The installed copy keeps script.php beside src/Helper. Uninstall must load.
 *
 * @since  1.0.0
 */
final class InstallerLayoutTest extends TestCase
{
	/**
	 * Package and installed layouts both load, and uninstall runs.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInstalledLayoutUninstallAndPackagePreflight(): void
	{
		$result = $this->runCheck();

		$this->assertSame(0, $result['exit'], $result['output']);
		$this->assertStringContainsString('installed-layout uninstall ok', $result['output']);
		$this->assertStringContainsString('installed-layout preflight ok', $result['output']);
		$this->assertStringContainsString('package-layout preflight ok', $result['output']);
		$this->assertStringContainsString('package-layout uninstall ok', $result['output']);
	}

	/**
	 * Run the installer layout check.
	 *
	 * @return  array{exit: int, output: string}
	 *
	 * @since   1.0.0
	 */
	private function runCheck(): array
	{
		$root = dirname(__DIR__);
		$pipes = [];
		$process = proc_open(
			['php', $root . '/scripts/ci/installer-layout-check.php'],
			[
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			],
			$pipes,
			$root
		);

		$this->assertIsResource($process);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);

		return [
			'exit' => proc_close($process),
			'output' => (string) $stdout . (string) $stderr,
		];
	}
}
