<?php

/**
 * Wendfold - 1.0.0 install and uninstall SQL table lists must match.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Uninstall drops exactly the tables the install baseline creates.
 *
 * @since  1.0.0
 */
final class InstallUninstallSqlTablesTest extends TestCase
{
	/**
	 * Uninstall DROP TABLE names match install CREATE TABLE names, no extras.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUninstallDropsExactlyInstallTables(): void
	{
		$root = dirname(__DIR__) . '/com_wendfold/admin/sql';
		$install = (string) file_get_contents($root . '/install.mysql.utf8.sql');
		$uninstall = (string) file_get_contents($root . '/uninstall.mysql.utf8.sql');

		$created = $this->tableNames($install, '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?#__wendfold_([a-z0-9_]+)`?/i');
		$dropped = $this->tableNames($uninstall, '/DROP\s+TABLE\s+IF\s+EXISTS\s+`?#__wendfold_([a-z0-9_]+)`?/i');

		$this->assertNotSame([], $created);
		$this->assertSame(
			$created,
			$dropped,
			'uninstall.mysql.utf8.sql must drop exactly the tables install.mysql.utf8.sql creates'
		);
		$this->assertNotContains('milestone_awards', $dropped);
		$this->assertNotContains('milestone_definitions', $dropped);
		$this->assertNotContains('rides', $dropped);
		$this->assertNotContains('miles', $dropped);
		$this->assertSame(
			0,
			preg_match_all('/DROP\s+VIEW\s+IF\s+EXISTS/i', $uninstall)
		);
	}

	/**
	 * Collect #__wendfold_* table suffixes in file order, unique and sorted for compare.
	 *
	 * @param   string  $sql      SQL file contents.
	 * @param   string  $pattern  Regex with one capture group for the table suffix.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function tableNames(string $sql, string $pattern): array
	{
		preg_match_all($pattern, $sql, $matches);
		$names = array_values(array_unique($matches[1] ?? []));
		sort($names);

		return $names;
	}
}
