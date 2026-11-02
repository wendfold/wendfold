<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Install-time database checks shared by the component installer.
 *
 * The installer script calls these before Joomla runs the SQL files.
 * PostgreSQL and unsupported MySQL or MariaDB versions are refused.
 * A pre-release database that still has the old profile table and not
 * the participation-metric table is refused so CREATE TABLE IF NOT
 * EXISTS cannot leave that database on the old shape.
 *
 * This file is self-contained so the installer-layout smoke test can stage
 * it alone beside script.php.
 *
 * @since  1.0.0
 */
final class SchemaGuard
{
	/**
	 * Oldest MySQL version Wendfold will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MYSQL = '8.0.13';

	/**
	 * Oldest MariaDB version Wendfold will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MARIADB = '10.4';

	/**
	 * Whether the Joomla database server type is MySQL or MariaDB.
	 *
	 * Joomla reports both engines as "mysql". A MariaDB driver that reports
	 * "mariadb" is accepted as well. PostgreSQL ("postgresql") is not.
	 *
	 * @param   string  $serverType  Value of DatabaseDriver::getServerType().
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function serverIsSupported(string $serverType): bool
	{
		$serverType = strtolower($serverType);

		return $serverType === 'mysql' || $serverType === 'mariadb';
	}

	/**
	 * Minimum version string for the connected engine.
	 *
	 * @param   boolean  $isMariaDb  True when the driver reports MariaDB.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function minimumVersion(bool $isMariaDb): string
	{
		return $isMariaDb ? self::MIN_MARIADB : self::MIN_MYSQL;
	}

	/**
	 * Strip the legacy MariaDB "5.5.5-" version-string prefix when present.
	 *
	 * Older MariaDB builds reported versions such as "5.5.5-10.4.14-MariaDB".
	 * version_compare must see the real 10.x value.
	 *
	 * @param   string  $version  Value of DatabaseDriver::getVersion().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function normalizeVersion(string $version): string
	{
		$version = trim($version);

		if (preg_match('/^5\.5\.5-(.+)$/', $version, $matches) === 1)
		{
			return (string) $matches[1];
		}

		return $version;
	}

	/**
	 * Version string suitable for installer messages.
	 *
	 * Strips the legacy "5.5.5-" prefix and a trailing "-MariaDB" (or similar)
	 * engine tag so labels read as "MariaDB 10.3.39", not the raw driver text.
	 *
	 * @param   string  $version  Value of DatabaseDriver::getVersion().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function displayVersion(string $version): string
	{
		$version = self::normalizeVersion($version);

		if ($version === '')
		{
			return '';
		}

		if (preg_match('/^(\d+(?:\.\d+)*)/', $version, $matches) === 1)
		{
			return (string) $matches[1];
		}

		return $version;
	}

	/**
	 * Whether the connected MySQL or MariaDB version meets Wendfold's floor.
	 *
	 * @param   string   $version    Value of DatabaseDriver::getVersion().
	 * @param   boolean  $isMariaDb  True when the driver reports MariaDB.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function versionIsSupported(string $version, bool $isMariaDb): bool
	{
		$version = self::normalizeVersion($version);

		if ($version === '')
		{
			return false;
		}

		return version_compare($version, self::minimumVersion($isMariaDb), '>=');
	}

	/**
	 * Whether the connected database may receive Wendfold install SQL.
	 *
	 * @param   string   $serverType  Value of DatabaseDriver::getServerType().
	 * @param   string   $version     Value of DatabaseDriver::getVersion().
	 * @param   boolean  $isMariaDb   True when the driver reports MariaDB.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function databaseIsSupported(string $serverType, string $version, bool $isMariaDb): bool
	{
		if (!self::serverIsSupported($serverType))
		{
			return false;
		}

		return self::versionIsSupported($version, $isMariaDb);
	}

	/**
	 * Whether the connected engine is MariaDB.
	 *
	 * Prefers an explicit server type of "mariadb", then the version string
	 * Joomla returns for MariaDB builds (often containing "MariaDB").
	 *
	 * @param   string  $serverType  Value of DatabaseDriver::getServerType().
	 * @param   string  $version     Value of DatabaseDriver::getVersion().
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isMariaDbEngine(string $serverType, string $version): bool
	{
		if (strtolower(trim($serverType)) === 'mariadb')
		{
			return true;
		}

		return stripos($version, 'mariadb') !== false;
	}

	/**
	 * Short label for the refused or connected database in installer messages.
	 *
	 * @param   string   $serverType  Value of DatabaseDriver::getServerType().
	 * @param   string   $version     Value of DatabaseDriver::getVersion().
	 * @param   boolean  $isMariaDb   True when the driver reports MariaDB.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function serverLabel(string $serverType, string $version, bool $isMariaDb): string
	{
		$serverType = strtolower(trim($serverType));
		$displayVersion = self::displayVersion($version);

		if (\in_array($serverType, ['postgresql', 'postgres', 'pgsql'], true))
		{
			return $displayVersion !== '' ? 'PostgreSQL ' . $displayVersion : 'PostgreSQL';
		}

		if (\in_array($serverType, ['mssql', 'sqlsrv'], true))
		{
			return $displayVersion !== '' ? 'Microsoft SQL Server ' . $displayVersion : 'Microsoft SQL Server';
		}

		if ($serverType === 'sqlite')
		{
			return $displayVersion !== '' ? 'SQLite ' . $displayVersion : 'SQLite';
		}

		if (!self::serverIsSupported($serverType))
		{
			return $serverType !== '' ? $serverType : 'unknown';
		}

		$engine = $isMariaDb || $serverType === 'mariadb' ? 'MariaDB' : 'MySQL';

		return $displayVersion !== '' ? $engine . ' ' . $displayVersion : $engine;
	}

	/**
	 * Whether the connected database is a pre-release Wendfold schema.
	 *
	 * @param   boolean  $profilesExist       #__wendfold_profiles exists.
	 * @param   boolean  $metricEntriesExist  #__wendfold_metric_entries exists.
	 * @param   boolean  $ridesTableExists    #__wendfold_rides exists.
	 *
	 * @return  boolean  True when install or update must abort.
	 *
	 * @since   1.0.0
	 */
	public static function isPrereleaseSchema(bool $profilesExist, bool $metricEntriesExist, bool $ridesTableExists = false): bool
	{
		if ($ridesTableExists)
		{
			return true;
		}

		return $profilesExist && !$metricEntriesExist;
	}
}
