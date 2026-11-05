<?php

/**
 * Wendfold package installer script.
 *
 * Responsibilities:
 * - enforce minimum Joomla and PHP versions for the suite installer
 * - enable companion plugins on a fresh suite install
 * - on update, enable only companion plugins that were not installed before
 * - never change the enabled state of a companion plugin that was already present
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\DI\ServiceProviderInterface;
use Joomla\DI\Container;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Installs, updates, and removes the Wendfold suite package.
 *
 * @since  1.0.0
 */
class Pkg_WendfoldInstallerScript implements InstallerScriptInterface
{
	/**
	 * Oldest Joomla version this package will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $minimumJoomla = '6.0.0';

	/**
	 * Oldest PHP version this package will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $minimumPhp = '8.3.0';

	/**
	 * Oldest MySQL version this package will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MYSQL = '8.0.13';

	/**
	 * Oldest MariaDB version this package will install on.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MIN_MARIADB = '10.4';

	/**
	 * Companion plugins already present before this install or update.
	 *
	 * Keys are self::companionExtensionKey() values (type, folder, element).
	 *
	 * @var    array<string, true>
	 * @since  1.0.0
	 */
	protected array $installedCompanionsBefore = [];

	/**
	 * Companion plugins shipped with this package.
	 *
	 * Each entry matches a row identity in #__extensions (type, folder, element).
	 *
	 * @return  list<array{type: string, folder: string, element: string}>
	 *
	 * @since   1.0.0
	 */
	public static function companionDefinitions(): array
	{
		return [
			['type' => 'plugin', 'folder' => 'user', 'element' => 'wendfold'],
			['type' => 'plugin', 'folder' => 'task', 'element' => 'wendfold'],
			['type' => 'plugin', 'folder' => 'privacy', 'element' => 'wendfold'],
		];
	}

	/**
	 * Stable key for one companion plugin row in #__extensions.
	 *
	 * @param   string  $type     Extension type.
	 * @param   string  $folder   Plugin folder.
	 * @param   string  $element  Extension element.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function companionExtensionKey(string $type, string $folder, string $element): string
	{
		return $type . "\0" . $folder . "\0" . $element;
	}

	/**
	 * Companion plugins that were not installed before this install or update.
	 *
	 * Only these should be enabled. Plugins already present keep their enabled
	 * state, including ones an administrator deliberately disabled.
	 *
	 * @param   array<string, true>                                         $installedBefore  Keys from companionExtensionKey().
	 * @param   list<array{type: string, folder: string, element: string}>  $companions       Companion definitions.
	 *
	 * @return  list<array{type: string, folder: string, element: string}>
	 *
	 * @since   1.0.0
	 */
	public static function companionsMissingFrom(array $installedBefore, array $companions): array
	{
		$missing = [];

		foreach ($companions as $plugin)
		{
			$key = self::companionExtensionKey($plugin['type'], $plugin['folder'], $plugin['element']);

			if (!isset($installedBefore[$key]))
			{
				$missing[] = $plugin;
			}
		}

		return $missing;
	}

	/**
	 * Reject the install when PHP or Joomla is older than this package allows.
	 *
	 * @param   string            $type     Installer route: install, update, discover_install, or uninstall.
	 * @param   InstallerAdapter  $adapter  Package installer adapter.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				'Wendfold requires PHP ' . $this->minimumPhp . ' or newer. Current PHP: ' . PHP_VERSION . '.',
				'error'
			);

			return false;
		}

		if (version_compare(JVERSION, $this->minimumJoomla, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				'Wendfold requires Joomla ' . $this->minimumJoomla . ' or newer. Current Joomla: ' . JVERSION . '.',
				'error'
			);

			return false;
		}

		if ($type === 'install' || $type === 'update')
		{
			if (!$this->assertSupportedDatabase())
			{
				return false;
			}
		}

		$this->captureExistingPluginStates();

		return true;
	}

	/**
	 * Refuse PostgreSQL and MySQL or MariaDB below the supported floors.
	 *
	 * Runs before any child extension SQL. The rules live on this class so
	 * package preflight does not depend on a constituent ZIP or root helper.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	protected function assertSupportedDatabase(): bool
	{
		$db = $this->database();
		$serverType = $db->getServerType();
		$version = (string) $db->getVersion();
		$isMariaDb = self::isMariaDbEngine($serverType, $version);

		if (self::databaseIsSupported($serverType, $version, $isMariaDb))
		{
			return true;
		}

		$label = self::serverLabel($serverType, $version, $isMariaDb);
		$messageKey = self::serverIsSupported($serverType)
			? 'PKG_WENDFOLD_INSTALL_REQUIRES_MYSQL_VERSION'
			: 'PKG_WENDFOLD_INSTALL_REQUIRES_MYSQL_TYPE';
		Factory::getApplication()->enqueueMessage(
			Text::sprintf($messageKey, $label),
			'error'
		);

		return false;
	}

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
	 * Enable companion plugins that were not already installed, and report the result.
	 *
	 * @param   InstallerAdapter  $adapter  Package installer adapter.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function install(InstallerAdapter $adapter): bool
	{
		$companions = self::companionDefinitions();
		$enabledPlugins = [];
		$preservedPlugins = [];

		foreach (self::companionsMissingFrom($this->installedCompanionsBefore, $companions) as $plugin)
		{
			if ($this->setPluginEnabled($plugin['folder'], true))
			{
				$enabledPlugins[] = $this->getPluginDisplayName($plugin['folder']);
			}
		}

		foreach ($companions as $plugin)
		{
			$key = self::companionExtensionKey($plugin['type'], $plugin['folder'], $plugin['element']);

			if (isset($this->installedCompanionsBefore[$key]))
			{
				$preservedPlugins[] = $this->getPluginDisplayName($plugin['folder']);
			}
		}

		$message = 'Wendfold suite installed successfully.';

		if ($enabledPlugins !== [])
		{
			$message .= ' Enabled companion plugins: ' . implode(', ', $enabledPlugins) . '.';
		}

		if ($preservedPlugins !== [])
		{
			$message .= ' Preserved existing plugin states for: ' . implode(', ', $preservedPlugins) . '.';
		}

		Factory::getApplication()->enqueueMessage($message, 'success');

		return true;
	}

	/**
	 * Report a successful package update.
	 *
	 * Newly added companion plugins are enabled in postflight. Plugins that
	 * were already installed keep their enabled or disabled state.
	 *
	 * @param   InstallerAdapter  $adapter  Package installer adapter.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function update(InstallerAdapter $adapter): bool
	{
		Factory::getApplication()->enqueueMessage(
			'Wendfold suite package updated.',
			'success'
		);

		return true;
	}

	/**
	 * Report package removal after Joomla has removed the child extensions.
	 *
	 * @param   InstallerAdapter  $adapter  Package installer adapter.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function uninstall(InstallerAdapter $adapter): bool
	{
		Factory::getApplication()->enqueueMessage(
			'Wendfold suite package removed. Joomla handled child extension removal through the package manifest.',
			'notice'
		);

		return true;
	}

	/**
	 * After an update, enable companions that were missing before this run.
	 *
	 * @param   string            $type     Installer route: install, update, discover_install, or uninstall.
	 * @param   InstallerAdapter  $adapter  Package installer adapter.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($type === 'update')
		{
			$this->enableNewlyInstalledCompanions();
		}

		return true;
	}

	/**
	 * Remember which companion plugins were already installed (type, folder, element).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function captureExistingPluginStates(): void
	{
		$this->installedCompanionsBefore = [];

		foreach (self::companionDefinitions() as $plugin)
		{
			if ($this->getPluginEnabledState($plugin['folder']) === null)
			{
				continue;
			}

			$key = self::companionExtensionKey($plugin['type'], $plugin['folder'], $plugin['element']);
			$this->installedCompanionsBefore[$key] = true;
		}
	}

	/**
	 * Enable companion plugins that were not present before this update.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function enableNewlyInstalledCompanions(): void
	{
		$enabledPlugins = [];

		foreach (self::companionsMissingFrom($this->installedCompanionsBefore, self::companionDefinitions()) as $plugin)
		{
			if ($this->setPluginEnabled($plugin['folder'], true))
			{
				$enabledPlugins[] = $this->getPluginDisplayName($plugin['folder']);
			}
		}

		if ($enabledPlugins === [])
		{
			return;
		}

		Factory::getApplication()->enqueueMessage(
			'Enabled newly added companion plugins: ' . implode(', ', $enabledPlugins) . '.',
			'success'
		);
	}

	/**
	 * Read the enabled flag for one companion plugin.
	 *
	 * @param   string  $group  Plugin folder: user, task, or privacy.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	protected function getPluginEnabledState(string $group): ?int
	{
		$db = $this->database();
		$type = 'plugin';
		$element = 'wendfold';
		$query = $db->createQuery()
			->select($db->quoteName('enabled'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = :type')
			->where($db->quoteName('folder') . ' = :folder')
			->where($db->quoteName('element') . ' = :element')
			->bind(':type', $type)
			->bind(':folder', $group)
			->bind(':element', $element);

		$db->setQuery($query);
		$result = $db->loadResult();

		if ($result === null)
		{
			return null;
		}

		return (int) $result;
	}

	/**
	 * Build the administrator label for one companion plugin.
	 *
	 * @param   string  $group  Plugin folder: user, task, or privacy.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function getPluginDisplayName(string $group): string
	{
		return match ($group) {
			'user' => 'User - Wendfold',
			'task' => 'Task - Wendfold',
			'privacy' => 'Privacy - Wendfold',
			default => $group,
		};
	}

	/**
	 * Set the enabled flag for one companion plugin.
	 *
	 * @param   string   $group    Plugin folder: user, task, or privacy.
	 * @param   boolean  $enabled  True enables the plugin.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	protected function setPluginEnabled(string $group, bool $enabled): bool
	{
		$db = $this->database();
		$enabledFlag = $enabled ? 1 : 0;
		$type = 'plugin';
		$element = 'wendfold';
		$query = $db->createQuery()
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = :enabled')
			->where($db->quoteName('type') . ' = :type')
			->where($db->quoteName('folder') . ' = :folder')
			->where($db->quoteName('element') . ' = :element')
			->bind(':enabled', $enabledFlag, ParameterType::INTEGER)
			->bind(':type', $type)
			->bind(':folder', $group)
			->bind(':element', $element);

		$db->setQuery($query);
		$db->execute();

		return $db->getAffectedRows() > 0;
	}

	/**
	 * Database driver from the application DI container.
	 *
	 * @return  DatabaseInterface
	 *
	 * @since   1.0.0
	 */
	protected function database(): DatabaseInterface
	{
		return Factory::getContainer()->get(DatabaseInterface::class);
	}
}

return new class implements ServiceProviderInterface {
	/**
	 * Register the installer script in the DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			InstallerScriptInterface::class,
			static function (Container $container) {
				return new Pkg_WendfoldInstallerScript;
			}
		);
	}
};
