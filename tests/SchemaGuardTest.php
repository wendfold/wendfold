<?php

/**
 * Wendfold - Install preflight schema guard.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\SchemaGuard;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/SchemaGuard.php';

/**
 * MySQL and MariaDB are accepted. PostgreSQL and a pre-release schema are not.
 *
 * @since  1.0.0
 */
final class SchemaGuardTest extends TestCase
{
	/**
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function serverProvider(): array
	{
		return [
			'mysql' => ['mysql', true],
			'mariadb' => ['mariadb', true],
			'mysql uppercase' => ['MySQL', true],
			'postgresql' => ['postgresql', false],
			'postgres' => ['postgres', false],
			'empty' => ['', false],
		];
	}

	/**
	 * Each supported server type is accepted, and PostgreSQL is refused.
	 *
	 * @param   string   $serverType  Database server type.
	 * @param   boolean  $supported   Whether that type is accepted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('serverProvider')]
	public function testServerSupport(string $serverType, bool $supported): void
	{
		$this->assertSame($supported, SchemaGuard::serverIsSupported($serverType));
	}

	/**
	 * Version floors and the MariaDB 5.5.5- prefix are handled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testVersionSupportAndLegacyPrefix(): void
	{
		$this->assertTrue(SchemaGuard::versionIsSupported('8.0.13', false));
		$this->assertFalse(SchemaGuard::versionIsSupported('8.0.12', false));
		$this->assertTrue(SchemaGuard::versionIsSupported('10.4.0', true));
		$this->assertFalse(SchemaGuard::versionIsSupported('10.3.39', true));
		$this->assertTrue(SchemaGuard::databaseIsSupported('mysql', '8.4.0', false));
		$this->assertFalse(SchemaGuard::databaseIsSupported('postgresql', '16.0', false));
		$this->assertSame('10.4.14-MariaDB', SchemaGuard::normalizeVersion('5.5.5-10.4.14-MariaDB'));
		$this->assertSame('10.4.14', SchemaGuard::displayVersion('5.5.5-10.4.14-MariaDB'));
		$this->assertTrue(SchemaGuard::versionIsSupported('5.5.5-10.4.14-MariaDB', true));
		$this->assertFalse(SchemaGuard::versionIsSupported('5.5.5-10.3.39-MariaDB', true));
	}

	/**
	 * Server labels normalize MariaDB version text and PostgreSQL aliases.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testServerLabelNormalization(): void
	{
		$this->assertSame(
			'MariaDB 10.3.39',
			SchemaGuard::serverLabel('mysql', '5.5.5-10.3.39-MariaDB', true)
		);
		$this->assertSame('PostgreSQL 16.0', SchemaGuard::serverLabel('postgresql', '16.0', false));
		$this->assertSame('PostgreSQL 14.0', SchemaGuard::serverLabel('pgsql', '14.0', false));
		$this->assertSame('PostgreSQL', SchemaGuard::serverLabel('postgres', '', false));
		$this->assertSame('SQLite 3.45.0', SchemaGuard::serverLabel('sqlite', '3.45.0', false));
		$this->assertSame('SQLite', SchemaGuard::serverLabel('sqlite', '', false));
		$this->assertSame(
			'Microsoft SQL Server 15.0',
			SchemaGuard::serverLabel('mssql', '15.0', false)
		);
		$this->assertSame(
			'Microsoft SQL Server 16.0',
			SchemaGuard::serverLabel('sqlsrv', '16.0', false)
		);
		$this->assertSame(
			'Microsoft SQL Server',
			SchemaGuard::serverLabel('mssql', '', false)
		);
	}

	/**
	 * A pre-release schema is profiles without the metrics table.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrereleaseSchemaIsProfilesWithoutMetricEntries(): void
	{
		$this->assertTrue(SchemaGuard::isPrereleaseSchema(true, false));
		$this->assertFalse(SchemaGuard::isPrereleaseSchema(true, true));
		$this->assertFalse(SchemaGuard::isPrereleaseSchema(false, false));
		$this->assertFalse(SchemaGuard::isPrereleaseSchema(false, true));
		$this->assertTrue(SchemaGuard::isPrereleaseSchema(true, true, true));
		$this->assertTrue(SchemaGuard::isPrereleaseSchema(false, false, true));
	}
}
