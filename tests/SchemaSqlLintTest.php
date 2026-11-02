<?php

/**
 * Wendfold - SQL lint used by the schema checks.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The SQL lint rejects banned constructs and accepts the component SQL tree.
 *
 * @since  1.0.0
 */
final class SchemaSqlLintTest extends TestCase
{
	/**
	 * The lint script rejects its own banned samples.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLinterSelfTest(): void
	{
		$result = $this->runLint(['--self-test']);

		$this->assertSame(0, $result['exit'], $result['output']);
	}

	/**
	 * The component SQL tree passes the lint.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testComponentSqlIsAccepted(): void
	{
		$result = $this->runLint(['com_wendfold/admin/sql']);

		$this->assertSame(0, $result['exit'], $result['output']);
		$this->assertStringContainsString('accepted', $result['output']);
	}

	/**
	 * Run the SQL lint script.
	 *
	 * @param   array  $args  Arguments passed to the lint script.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function runLint(array $args): array
	{
		$root = dirname(__DIR__);
		$command = array_merge(['python3', $root . '/scripts/ci/schema_lint.py'], $args);
		$pipes = [];
		$process = proc_open(
			$command,
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
