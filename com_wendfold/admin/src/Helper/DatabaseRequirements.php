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

\defined('_JEXEC') or die;

/**
 * Database type and version rules for install and update preflight.
 *
 * Wendfold ships MySQL SQL only. PostgreSQL is refused. MySQL older than
 * 8.0.13 and MariaDB older than 10.4 are refused. Call these checks before
 * any install SQL runs. Implementation lives in SchemaGuard so the
 * installer-layout smoke test can stage that single helper beside script.php.
 *
 * @since  1.0.0
 */
final class DatabaseRequirements
{
	/**
	 * Oldest MySQL version Wendfold will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MYSQL = SchemaGuard::MIN_MYSQL;

	/**
	 * Oldest MariaDB version Wendfold will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MARIADB = SchemaGuard::MIN_MARIADB;

	/**
	 * Whether the Joomla database server type is MySQL or MariaDB.
	 *
	 * @param   string  $serverType  Value of DatabaseDriver::getServerType().
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function serverIsSupported(string $serverType): bool
	{
		return SchemaGuard::serverIsSupported($serverType);
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
		return SchemaGuard::minimumVersion($isMariaDb);
	}

	/**
	 * Strip the legacy MariaDB "5.5.5-" version-string prefix when present.
	 *
	 * @param   string  $version  Value of DatabaseDriver::getVersion().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function normalizeVersion(string $version): string
	{
		return SchemaGuard::normalizeVersion($version);
	}

	/**
	 * Version string suitable for installer messages.
	 *
	 * @param   string  $version  Value of DatabaseDriver::getVersion().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function displayVersion(string $version): string
	{
		return SchemaGuard::displayVersion($version);
	}

	/**
	 * Whether the connected MySQL or MariaDB version meets the floor.
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
		return SchemaGuard::versionIsSupported($version, $isMariaDb);
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
		return SchemaGuard::databaseIsSupported($serverType, $version, $isMariaDb);
	}

	/**
	 * Whether the connected engine is MariaDB.
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
		return SchemaGuard::isMariaDbEngine($serverType, $version);
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
		return SchemaGuard::serverLabel($serverType, $version, $isMariaDb);
	}
}
