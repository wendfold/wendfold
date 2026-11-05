<?php

/**
 * Wendfold - install database type and version floors.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\DatabaseRequirements;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/SchemaGuard.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/DatabaseRequirements.php';
require_once dirname(__DIR__) . '/pkg_wendfold/script.php';

/**
 * PostgreSQL and old MySQL or MariaDB versions are refused before SQL runs.
 *
 * @since  1.0.0
 */
final class DatabaseRequirementsTest extends TestCase
{
	/**
	 * @return array<string, array{0: string, 1: string, 2: bool, 3: bool, 4: string}>
	 */
	public static function agreementProvider(): array
	{
		return [
			'postgresql' => ['postgresql', '16.0', false, false, 'PostgreSQL 16.0'],
			'postgres alias' => ['postgres', '15.1', false, false, 'PostgreSQL 15.1'],
			'pgsql alias' => ['pgsql', '14.0', false, false, 'PostgreSQL 14.0'],
			'mysql below floor' => ['mysql', '8.0.12', false, false, 'MySQL 8.0.12'],
			'mysql floor' => ['mysql', '8.0.13', false, true, 'MySQL 8.0.13'],
			'mariadb below floor' => ['mysql', '10.3.39-MariaDB', true, false, 'MariaDB 10.3.39'],
			'mariadb floor' => ['mysql', '10.4.0-MariaDB', true, true, 'MariaDB 10.4.0'],
			'mariadb 5.5.5 prefix below' => ['mysql', '5.5.5-10.3.39-MariaDB', true, false, 'MariaDB 10.3.39'],
			'mariadb 5.5.5 prefix floor' => ['mysql', '5.5.5-10.4.14-MariaDB', true, true, 'MariaDB 10.4.14'],
			'mariadb 5.5.5 prefix above' => ['mysql', '5.5.5-10.11.6-MariaDB', true, true, 'MariaDB 10.11.6'],
			'sqlite' => ['sqlite', '3.45.0', false, false, 'SQLite 3.45.0'],
			'mssql' => ['mssql', '15.0', false, false, 'Microsoft SQL Server 15.0'],
			'sqlsrv' => ['sqlsrv', '16.0', false, false, 'Microsoft SQL Server 16.0'],
		];
	}

	/**
	 * Package script checks and the component helper agree on every case.
	 *
	 * @param   string   $serverType  Database server type.
	 * @param   string   $version     Driver version string.
	 * @param   boolean  $isMariaDb   Whether the engine is MariaDB.
	 * @param   boolean  $supported   Whether that database is accepted.
	 * @param   string   $label       Expected installer server label.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	#[DataProvider('agreementProvider')]
	public function testPackageAndComponentChecksAgree(
		string $serverType,
		string $version,
		bool $isMariaDb,
		bool $supported,
		string $label
	): void
	{
		$detectedMaria = DatabaseRequirements::isMariaDbEngine($serverType, $version);
		$packageMaria = \Pkg_WendfoldInstallerScript::isMariaDbEngine($serverType, $version);

		$this->assertSame($isMariaDb, $detectedMaria);
		$this->assertSame($detectedMaria, $packageMaria);

		$component = DatabaseRequirements::databaseIsSupported($serverType, $version, $detectedMaria);
		$package = \Pkg_WendfoldInstallerScript::databaseIsSupported($serverType, $version, $packageMaria);

		$this->assertSame($supported, $component);
		$this->assertSame($component, $package);
		$this->assertSame(
			DatabaseRequirements::normalizeVersion($version),
			\Pkg_WendfoldInstallerScript::normalizeVersion($version)
		);
		$this->assertSame(
			DatabaseRequirements::displayVersion($version),
			\Pkg_WendfoldInstallerScript::displayVersion($version)
		);
		$this->assertSame($label, DatabaseRequirements::serverLabel($serverType, $version, $detectedMaria));
		$this->assertSame(
			$label,
			\Pkg_WendfoldInstallerScript::serverLabel($serverType, $version, $packageMaria)
		);
		$this->assertSame(
			DatabaseRequirements::MIN_MYSQL,
			\Pkg_WendfoldInstallerScript::MIN_MYSQL
		);
		$this->assertSame(
			DatabaseRequirements::MIN_MARIADB,
			\Pkg_WendfoldInstallerScript::MIN_MARIADB
		);
	}

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
			'pgsql' => ['pgsql', false],
			'sqlite' => ['sqlite', false],
			'mssql' => ['mssql', false],
			'sqlsrv' => ['sqlsrv', false],
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
		$this->assertSame($supported, DatabaseRequirements::serverIsSupported($serverType));
		$this->assertSame($supported, \Pkg_WendfoldInstallerScript::serverIsSupported($serverType));
	}

	/**
	 * MySQL 8.0.13+ and MariaDB 10.4+ pass; older versions fail.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testVersionFloors(): void
	{
		$this->assertTrue(DatabaseRequirements::versionIsSupported('8.0.13', false));
		$this->assertFalse(DatabaseRequirements::versionIsSupported('8.0.12', false));
		$this->assertTrue(DatabaseRequirements::versionIsSupported('10.4', true));
		$this->assertFalse(DatabaseRequirements::versionIsSupported('10.3.39', true));
		$this->assertTrue(
			DatabaseRequirements::versionIsSupported('5.5.5-10.4.14-MariaDB', true)
		);
		$this->assertFalse(
			DatabaseRequirements::versionIsSupported('5.5.5-10.3.39-MariaDB', true)
		);
	}

	/**
	 * PostgreSQL is refused even when the version string looks new enough.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPostgreSqlIsRefusedRegardlessOfVersion(): void
	{
		$this->assertFalse(
			DatabaseRequirements::databaseIsSupported('postgresql', '16.0', false)
		);
		$this->assertSame(
			'PostgreSQL 16.0',
			DatabaseRequirements::serverLabel('postgresql', '16.0', false)
		);
	}

	/**
	 * Installer labels normalize engine names and MariaDB version strings.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testServerLabelNormalization(): void
	{
		$this->assertSame(
			'MySQL 8.0.12',
			DatabaseRequirements::serverLabel('mysql', '8.0.12', false)
		);
		$this->assertSame(
			'MariaDB 10.3.39',
			DatabaseRequirements::serverLabel('mysql', '10.3.39', true)
		);
		$this->assertSame(
			'MariaDB 10.3.39',
			DatabaseRequirements::serverLabel('mysql', '5.5.5-10.3.39-MariaDB', true)
		);
		$this->assertSame(
			'PostgreSQL',
			DatabaseRequirements::serverLabel('postgresql', '', false)
		);
		$this->assertSame(
			'PostgreSQL 14.0',
			DatabaseRequirements::serverLabel('pgsql', '14.0', false)
		);
		$this->assertSame(
			'SQLite 3.45.0',
			DatabaseRequirements::serverLabel('sqlite', '3.45.0', false)
		);
		$this->assertSame(
			'Microsoft SQL Server 15.0',
			DatabaseRequirements::serverLabel('mssql', '15.0', false)
		);
		$this->assertSame(
			'Microsoft SQL Server 16.0',
			DatabaseRequirements::serverLabel('sqlsrv', '16.0', false)
		);
		$this->assertSame(
			'10.3.39',
			DatabaseRequirements::displayVersion('5.5.5-10.3.39-MariaDB')
		);
		$this->assertSame(
			\Pkg_WendfoldInstallerScript::serverLabel('mysql', '5.5.5-10.3.39-MariaDB', true),
			DatabaseRequirements::serverLabel('mysql', '5.5.5-10.3.39-MariaDB', true)
		);
	}

	/**
	 * Refusal language keys name the floors and distinguish type from version.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRefusalLanguageKeysDistinguishTypeAndVersion(): void
	{
		$component = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.sys.ini'
		);
		$package = (string) file_get_contents(
			dirname(__DIR__) . '/pkg_wendfold/language/en-GB/pkg_wendfold.sys.ini'
		);

		foreach ([$component, $package] as $ini)
		{
			$this->assertStringContainsString(
				'Wendfold requires MySQL 8.0.13+ or MariaDB 10.4+. %s is not supported.',
				$ini
			);
			$this->assertStringContainsString(
				'Wendfold requires MySQL 8.0.13+ or MariaDB 10.4+; this site runs %s.',
				$ini
			);
			$this->assertStringNotContainsString(
				'This site uses %s. PostgreSQL is not supported.',
				$ini
			);
		}
	}

	/**
	 * Minimum constants match the documented floors.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMinimumConstants(): void
	{
		$this->assertSame('8.0.13', DatabaseRequirements::MIN_MYSQL);
		$this->assertSame('10.4', DatabaseRequirements::MIN_MARIADB);
		$this->assertSame('8.0.13', DatabaseRequirements::minimumVersion(false));
		$this->assertSame('10.4', DatabaseRequirements::minimumVersion(true));
	}
}
