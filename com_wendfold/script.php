<?php

/**
 * Wendfold  -  Chapter Management System
 * Installer Script
 *
 * Runs automatically on:
 *   - install   : fresh installation
 *   - update    : upgrading from a previous version
 *   - uninstall : removing the extension
 *
 * Responsibilities:
 *   - Create Wendfold user groups nested under Joomla's
 *     native 'Registered' group
 *   - On uninstall, remove only the groups this component created
 *   - Display status messages to the administrator
 *
 * Schema changes belong in admin/sql (install, uninstall, and
 * updates/mysql). This script does not alter tables.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access  -  Joomla must bootstrap before this runs
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\DI\ServiceProviderInterface;
use Joomla\DI\Container;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Table\Usergroup;
use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\SchemaGuard;
use Wendfold\Component\Wendfold\Administrator\Helper\UninstallCleanup;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;

/**
 * Wendfold Installer Script Class
 *
 * Joomla looks for a class named <ComponentName>InstallerScript
 * in this file. The name must match exactly.
 *
 * @since  1.0.0
 */
class Com_WendfoldInstallerScript implements InstallerScriptInterface
{
	/**
	 * Minimum Joomla version required to install Wendfold.
	 * Prevents installation on Joomla 3/4/5 where PSR-4 structure
	 * may behave differently.
	 *
	 * @var string
	 */
	protected string $minimumJoomla = '6.0.0';

	/**
	 * Minimum PHP version required.
	 *
	 * @var string
	 */
	protected string $minimumPhp = '8.3.0';

	/*
	 * =========================================================
	 * Joomla Installer Lifecycle Methods
	 * =========================================================
	 */

	/**
	 * Runs before any installation action.
	 * Use this to check requirements and abort early if needed.
	 *
	 * @param   string            $type    Type of action: install|update|uninstall
	 * @param   InstallerAdapter  $adapter The installer adapter
	 *
	 * @return  boolean  Return false to abort the installation
	 */
	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		// Check minimum PHP version
		if (version_compare(PHP_VERSION, $this->minimumPhp, '<'))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_WENDFOLD_INSTALL_REQUIRES_PHP', $this->minimumPhp, PHP_VERSION), 'error');

			return false;
		}

		// Check minimum Joomla version
		if (version_compare(JVERSION, $this->minimumJoomla, '<'))
		{
			Factory::getApplication()->enqueueMessage(Text::sprintf('COM_WENDFOLD_INSTALL_REQUIRES_JOOMLA', $this->minimumJoomla, JVERSION), 'error');

			return false;
		}

		if ($type === 'install' || $type === 'update')
		{
			$this->loadSchemaGuard();
			$db = $this->database();
			$serverType = $db->getServerType();

			if (!$this->databaseMeetsRequirements($db, $serverType))
			{
				return false;
			}

			$profiles = $this->wendfoldTableExists($db, '#__wendfold_profiles');
			$metricEntries = $this->wendfoldTableExists($db, '#__wendfold_metric_entries');
			$rides = $this->wendfoldTableExists($db, '#__wendfold_rides');

			if (SchemaGuard::isPrereleaseSchema($profiles, $metricEntries, $rides))
			{
				Factory::getApplication()->enqueueMessage(Text::_('COM_WENDFOLD_INSTALL_PRERELEASE_SCHEMA'), 'error');

				return false;
			}
		}

		return true;
	}

	/**
	 * Load the schema guard from the layout this script is running in.
	 *
	 * The install ZIP keeps the helper at admin/src/Helper. The installed
	 * copy keeps script.php beside src/Helper. Uninstall does not call this.
	 * SchemaGuard is self-contained so the layout smoke test can stage it alone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadSchemaGuard(): void
	{
		$this->loadInstallerHelper('SchemaGuard.php', 'Wendfold schema guard could not be loaded.');
	}

	/**
	 * Database driver from the DI container.
	 *
	 * @return  DatabaseInterface
	 *
	 * @since   1.0.0
	 */
	private function database(): DatabaseInterface
	{
		return Factory::getContainer()->get(DatabaseInterface::class);
	}

	/**
	 * Write a warning to the shared com_wendfold log file.
	 *
	 * Soft-loads WendfoldLog so the installer-layout smoke harness (which
	 * stages only SchemaGuard and ComponentOptions) can still run.
	 *
	 * @param   string  $message  Detail text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function logWarning(string $message): void
	{
		try
		{
			if (!\class_exists(WendfoldLog::class, false))
			{
				foreach ([
					__DIR__ . '/admin/src/Helper/WendfoldLog.php',
					__DIR__ . '/src/Helper/WendfoldLog.php',
				] as $path)
				{
					if (is_file($path))
					{
						require_once $path;
						break;
					}
				}
			}

			if (\class_exists(WendfoldLog::class, false))
			{
				WendfoldLog::add($message, Log::WARNING);
			}
		}
		catch (\Throwable)
		{
			// Logging must never replace the original failure path.
		}
	}

	/**
	 * Refuse unsupported database types and versions before any install SQL.
	 *
	 * When getVersion() is unavailable (installer-layout smoke stub), only the
	 * server type is checked. Production Joomla drivers always expose a version.
	 *
	 * @param   object  $db          Database driver.
	 * @param   string  $serverType  Value of getServerType().
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function databaseMeetsRequirements(object $db, string $serverType): bool
	{
		$version = '';

		if (\method_exists($db, 'getVersion'))
		{
			$version = (string) $db->getVersion();
		}

		$isMariaDb = SchemaGuard::isMariaDbEngine($serverType, $version);

		if ($version === '')
		{
			if (SchemaGuard::serverIsSupported($serverType))
			{
				return true;
			}
		}
		elseif (SchemaGuard::databaseIsSupported($serverType, $version, $isMariaDb))
		{
			return true;
		}

		$label = SchemaGuard::serverLabel($serverType, $version, $isMariaDb);
		$messageKey = SchemaGuard::serverIsSupported($serverType)
			? 'COM_WENDFOLD_INSTALL_REQUIRES_MYSQL_VERSION'
			: 'COM_WENDFOLD_INSTALL_REQUIRES_MYSQL_TYPE';
		Factory::getApplication()->enqueueMessage(
			Text::sprintf($messageKey, $label),
			'error'
		);

		return false;
	}

	/**
	 * Load uninstall cleanup helpers for scheduler tasks and upload trees.
	 *
	 * Returns false when the helper is absent (installer-layout smoke test
	 * stages only SchemaGuard and ComponentOptions beside script.php).
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function loadUninstallCleanup(): bool
	{
		if (class_exists(UninstallCleanup::class, false))
		{
			return true;
		}

		$candidates = [
			__DIR__ . '/admin/src/Helper/UninstallCleanup.php',
			__DIR__ . '/src/Helper/UninstallCleanup.php',
		];

		foreach ($candidates as $path)
		{
			if (is_file($path))
			{
				require_once $path;

				return class_exists(UninstallCleanup::class, false);
			}
		}

		return false;
	}

	/**
	 * Load one installer helper from the package or installed layout.
	 *
	 * @param   string  $fileName     Helper file name under src/Helper.
	 * @param   string  $missingText  Exception text when the file is absent.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadInstallerHelper(string $fileName, string $missingText): void
	{
		$candidates = [
			__DIR__ . '/admin/src/Helper/' . $fileName,
			__DIR__ . '/src/Helper/' . $fileName,
		];

		foreach ($candidates as $path)
		{
			if (is_file($path))
			{
				require_once $path;

				return;
			}
		}

		throw new \RuntimeException($missingText);
	}

	/**
	 * Load the options helper from the layout this script is running in.
	 *
	 * Joomla autoloads it after install. The installer check and the package
	 * extract load the file directly. The package path and the installed path
	 * are both accepted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadComponentOptions(): void
	{
		if (class_exists(ComponentOptions::class, false))
		{
			return;
		}

		$candidates = [
			__DIR__ . '/admin/src/Helper/ComponentOptions.php',
			__DIR__ . '/src/Helper/ComponentOptions.php',
		];

		foreach ($candidates as $path)
		{
			if (is_file($path))
			{
				require_once $path;

				return;
			}
		}

		throw new \RuntimeException('Wendfold component options could not be loaded.');
	}

	/**
	 * Whether a Wendfold table is already in this database.
	 *
	 * @param   DatabaseInterface  $db     Database driver.
	 * @param   string             $table  Joomla table name, including the #__ prefix.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function wendfoldTableExists(DatabaseInterface $db, string $table): bool
	{
		$name = $db->replacePrefix($table);
		$query = $db->createQuery()
			->select('COUNT(*)')
			->from('information_schema.tables')
			->where('table_schema = DATABASE()')
			->where('table_name = ' . $db->quote($name));

		return (int) $db->setQuery($query)->loadResult() > 0;
	}

	/**
	 * Runs after a fresh installation completes.
	 * Creates user groups.
	 *
	 * @param   InstallerAdapter  $adapter The installer adapter
	 *
	 * @return  boolean
	 */
	public function install(InstallerAdapter $adapter): bool
	{
		$this->ensureMediaFolders();
		$groups = $this->ensureUserGroups();
		$this->rebuildUserGroups();
		$this->storeGroupParams($groups['resolved'], $groups['created'], $groups['existing']);
		$this->showWelcomeMessage();

		return true;
	}

	/**
	 * Runs after an upgrade (re-install) completes.
	 * Ensures groups exist without duplicating them.
	 *
	 * @param   InstallerAdapter  $adapter The installer adapter
	 *
	 * @return  boolean
	 */
	public function update(InstallerAdapter $adapter): bool
	{
		$this->ensureMediaFolders();
		$groups = $this->ensureUserGroups();
		$this->rebuildUserGroups();
		$this->storeGroupParams($groups['resolved'], $groups['created'], $groups['existing']);

		Factory::getApplication()->enqueueMessage(Text::_('COM_WENDFOLD_INSTALL_UPDATED_SUCCESS'), 'success');

		return true;
	}

	/**
	 * Runs after the extension is uninstalled.
	 * Removes Wendfold user groups if they are empty.
	 *
	 * @param   InstallerAdapter  $adapter The installer adapter
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function uninstall(InstallerAdapter $adapter): bool
	{
		$this->loadComponentOptions();

		if ($this->loadUninstallCleanup())
		{
			$this->removeSchedulerTasksOnUninstall();
			$this->removeOwnedUploadsOnUninstall();
		}

		$this->removeUserGroups();

		Factory::getApplication()->enqueueMessage(Text::_('COM_WENDFOLD_INSTALL_UNINSTALLED_NOTICE'), 'notice');

		return true;
	}

	/**
	 * Delete Wendfold scheduler task rows left after the task plugin is removed.
	 *
	 * Idempotent: safe when the table or matching rows are already gone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function removeSchedulerTasksOnUninstall(): void
	{
		try
		{
			UninstallCleanup::removeSchedulerTasks($this->database());
		}
		catch (\Throwable $exception)
		{
			$this->logWarning('Scheduler task cleanup failed: ' . $exception->getMessage());
		}
	}

	/**
	 * Delete images/wendfold/ when the opt-in uninstall option is enabled.
	 *
	 * Params are read while the extension row is still present. Shared Media
	 * Manager folders outside that tree are never touched.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function removeOwnedUploadsOnUninstall(): void
	{
		$params = $this->storedGroupParams();

		if (!UninstallCleanup::removeUploadedFilesEnabled($params))
		{
			return;
		}

		$tree = UninstallCleanup::uploadTreePath(JPATH_ROOT);

		if (!UninstallCleanup::removeUploadTree($tree))
		{
			Factory::getApplication()->enqueueMessage(
				Text::_('COM_WENDFOLD_INSTALL_UPLOAD_TREE_REMOVE_FAILED'),
				'warning'
			);
		}
	}

	/**
	 * Runs after any installation action completes (install, update, or uninstall).
	 *
	 * @param   string            $type    Type of action
	 * @param   InstallerAdapter  $adapter The installer adapter
	 *
	 * @return  boolean
	 */
	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		if ($type === 'install' || $type === 'update')
		{
			$this->syncAdminMenu();
			$this->ensureEmailBlastPermission();
			$this->ensureActivityLogPermission();
			$this->storeMissingGroupIds($this->foundGroupIds());
		}

		return true;
	}

	/*
	 * =========================================================
	 * Private Helper Methods
	 * =========================================================
	 */

	/**
	 * Create any missing chapter groups and return the ids to store.
	 *
	 * A saved id that still exists is used, and its title is not changed.
	 * The seed title is used only when that id is empty or gone, and only
	 * for an exact match under the expected parent.
	 *
	 * @return  array{resolved: array<string, int>, created: array<int, int>, existing: array<int, int>}
	 *
	 * @since   1.0.0
	 */
	private function ensureUserGroups(): array
	{
		return $this->resolveChapterGroups(true);
	}

	/**
	 * Ids of the chapter groups that already exist.
	 *
	 * A saved id that still exists wins. Otherwise the seed title under the
	 * expected parent is used. This does not create groups.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	private function foundGroupIds(): array
	{
		return $this->resolveChapterGroups(false)['resolved'];
	}

	/**
	 * Choose each chapter group id, creating groups only when asked.
	 *
	 * @param   boolean  $create  True on install and update. False only looks up ids.
	 *
	 * @return  array{resolved: array<string, int>, created: array<int, int>, existing: array<int, int>}
	 *
	 * @since   1.0.0
	 */
	private function resolveChapterGroups(bool $create): array
	{
		$this->loadComponentOptions();
		$stored = $this->storedGroupParams();
		$existing = $this->loadUserGroupsById();
		$storedCreated = ComponentOptions::parseCreatedGroupIds($stored[ComponentOptions::CREATED_GROUP_IDS] ?? null);
		$resolved = [];
		$inserted = [];
		$owned = [];
		$app = Factory::getApplication();

		foreach ($storedCreated as $createdId)
		{
			if (isset($existing[$createdId]))
			{
				$owned[] = $createdId;
			}
		}

		foreach (ComponentOptions::groups() as $group)
		{
			$param = $group['param'];
			$title = $group['title'];
			$savedId = ComponentOptions::storedGroupId($stored, $param);
			$savedExists = $savedId > 0 && isset($existing[$savedId]);
			$parentId = ComponentOptions::seedParentId($group, $resolved, $existing, $owned);
			$titleMatchId = 0;

			if ($parentId > 0)
			{
				$titleMatchId = ComponentOptions::matchingGroupId($existing, $title, $parentId);
			}

			$choice = ComponentOptions::resolveGroupChoice($savedId, $savedExists, $titleMatchId);

			if ($choice['create'])
			{
				if (!$create || $parentId < 1)
				{
					if ($create)
					{
						$app->enqueueMessage(
							sprintf('Cannot create user group "%s": parent group was not found.', $title),
							'error'
						);
					}

					continue;
				}

				$newId = $this->insertUserGroup($parentId, $title);

				if ($newId < 1)
				{
					continue;
				}

				$existing[$newId] = [
					'id'        => $newId,
					'parent_id' => $parentId,
					'title'     => $title,
				];
				$resolved[$param] = $newId;
				$inserted[] = $newId;
				$owned[] = $newId;
				$app->enqueueMessage(sprintf('Created user group: %s', $title), 'success');

				continue;
			}

			$resolved[$param] = $choice['id'];

			if ($create)
			{
				$keptTitle = $title;

				if ($savedExists)
				{
					$keptTitle = $existing[$savedId]['title'];
				}

				$app->enqueueMessage(
					sprintf('User group "%s" already exists - skipped.', $keptTitle),
					'notice'
				);
			}
		}

		$created = ComponentOptions::createdIdsToRemember(
			$storedCreated,
			$existing,
			$inserted
		);

		return [
			'resolved' => $resolved,
			'created'  => $created,
			'existing' => array_keys($existing),
		];
	}

	/**
	 * Every user group, keyed by id.
	 *
	 * @return  array<int, array{id: int, parent_id: int, title: string}>
	 *
	 * @since   1.0.0
	 */
	private function loadUserGroupsById(): array
	{
		$db = $this->database();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('parent_id'),
					$db->quoteName('title'),
				]
			)
			->from($db->quoteName('#__usergroups'));
		$rows = $db->setQuery($query)->loadObjectList();

		if (!\is_array($rows))
		{
			return [];
		}

		$idColumn = 'id';
		$parentColumn = 'parent_id';
		$titleColumn = 'title';
		$groups = [];

		foreach ($rows as $row)
		{
			if (!\is_object($row))
			{
				continue;
			}

			$id = (int) $row->$idColumn;

			if ($id < 1)
			{
				continue;
			}

			$groups[$id] = [
				'id'        => $id,
				'parent_id' => (int) $row->$parentColumn,
				'title'     => (string) ($row->$titleColumn ?? ''),
			];
		}

		return $groups;
	}

	/**
	 * Insert one user group and return its id, or zero on failure.
	 *
	 * @param   integer  $parentId  Parent group id.
	 * @param   string   $title     Group title.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function insertUserGroup(int $parentId, string $title): int
	{
		try
		{
			$table = new Usergroup($this->database());
			$parentColumn = 'parent_id';
			$titleColumn = 'title';
			$idColumn = 'id';
			$table->$parentColumn = $parentId;
			$table->$titleColumn = $title;

			if ($table->check() && $table->store())
			{
				return (int) $table->$idColumn;
			}

			Factory::getApplication()->enqueueMessage(
				sprintf('Failed to create user group "%s". %s', $title, $table->getError()),
				'error'
			);
		}
		catch (\Throwable $exception)
		{
			Factory::getApplication()->enqueueMessage(
				sprintf('Failed to create user group "%s": %s', $title, $exception->getMessage()),
				'error'
			);
		}

		return 0;
	}

	/**
	 * Repair lft and rgt for every user group.
	 *
	 * Joomla derives those values from parent_id. Running this again
	 * leaves a correct tree unchanged. Install and update both call it
	 * so rows saved with zeros are repaired.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function rebuildUserGroups(): void
	{
		try
		{
			$table = new Usergroup($this->database());
			$table->rebuild();
		}
		catch (\Throwable $exception)
		{
			$this->logWarning('User group tree rebuild failed: ' . $exception->getMessage());
		}
	}

	/**
	 * Write group ids into component params when those options are empty.
	 *
	 * The created-group record is left as it is.
	 *
	 * @param   array  $resolved  Param name => usergroup id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function storeMissingGroupIds(array $resolved): void
	{
		if ($resolved === [])
		{
			return;
		}

		$this->storeGroupParams(
			$resolved,
			$this->recordedCreatedGroupIds(),
			array_keys($this->loadUserGroupsById())
		);
	}

	/**
	 * Save group options and the ids Wendfold created.
	 *
	 * A saved id is replaced when that group is no longer in #__usergroups.
	 *
	 * @param   array       $resolved     Param name => usergroup id.
	 * @param   array       $createdIds   Ids to record as created by Wendfold.
	 * @param   array|null  $existingIds  Ids present in #__usergroups.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function storeGroupParams(array $resolved, array $createdIds, ?array $existingIds = null): void
	{
		$this->loadComponentOptions();
		$extension = $this->loadComponentExtension();

		if ($extension === null)
		{
			return;
		}

		$paramsColumn = 'params';
		$idColumn = 'extension_id';
		$current = (string) ($extension->$paramsColumn ?? '');
		$withCreated = ComponentOptions::mergeCreatedGroupIds($current, $createdIds);
		$base = $withCreated ?? $current;
		$withGroups = ComponentOptions::mergeGroupParams($base, $resolved, $existingIds);
		$updated = $withGroups ?? $withCreated;

		if ($updated === null)
		{
			return;
		}

		$this->writeComponentParams((int) $extension->$idColumn, $updated);
	}

	/**
	 * Created group ids already stored on the component.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	private function recordedCreatedGroupIds(): array
	{
		$this->loadComponentOptions();
		$stored = $this->storedGroupParams();

		return ComponentOptions::parseCreatedGroupIds($stored[ComponentOptions::CREATED_GROUP_IDS] ?? null);
	}

	/**
	 * The component extension row, or null when it is not installed yet.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function loadComponentExtension(): ?object
	{
		$db = $this->database();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('extension_id'),
					$db->quoteName('params'),
				]
			)
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_wendfold'));
		$extension = $db->setQuery($query)->loadObject();

		if (!\is_object($extension))
		{
			return null;
		}

		return $extension;
	}

	/**
	 * Replace the component params JSON.
	 *
	 * @param   integer  $extensionId  #__extensions primary key.
	 * @param   string   $params       Encoded params.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function writeComponentParams(int $extensionId, string $params): void
	{
		$db = $this->database();
		$update = $db->createQuery()
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($params))
			->where($db->quoteName('extension_id') . ' = ' . $extensionId);

		$db->setQuery($update)->execute();
	}

	/**
	 * Sync the administrator sidebar menu items created from the manifest.
	 *
	 * Joomla creates admin menu entries in #__menu on install. This method
	 * keeps the remaining submenu icons consistent after install or update.
	 *
	 * The component root menu item (img / menu_image for the W-fold mark) is
	 * not updated here: Joomla's ComponentAdapter::_buildAdminMenus applies
	 * the manifest on fresh install and on overwrite reinstall.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function syncAdminMenu(): void
	{
		$db = $this->database();

		try
		{
			// Find the parent admin menu item for this component.
			$query = $db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('client_id') . ' = 1')
				->where($db->quoteName('menutype') . ' = ' . $db->quote('main'))
				->where($db->quoteName('link') . ' = ' . $db->quote('index.php?option=com_wendfold&view=members'));

			$db->setQuery($query);
			$parentId = (int) $db->loadResult();

			if ($parentId <= 0)
			{
				// Fallback: locate any admin menu entry for this component and use its parent.
				$query = $db->createQuery()
					->select($db->quoteName(['id', 'parent_id']))
					->from($db->quoteName('#__menu'))
					->where($db->quoteName('client_id') . ' = 1')
					->where($db->quoteName('menutype') . ' = ' . $db->quote('main'))
					->where($db->quoteName('link') . ' LIKE ' . $db->quote('index.php?option=com_wendfold%'))
					->order($db->quoteName('id') . ' ASC');

				$db->setQuery($query);
				$row = $db->loadObject();

				// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column
				if ($row && (int) $row->parent_id > 0)
				{
					// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column
					$parentId = (int) $row->parent_id;
				}
			}

			if ($parentId <= 0)
			{
				return;
			}

			// Ensure remaining submenu icons are consistent.
			$iconMap = [
				'members'    => 'class:icon-users',
				'events'     => 'class:icon-calendar',
				'locations'  => 'class:icon-map',
				'metricdefinitions' => 'class:icon-chart',
				'awarddefinitions' => 'class:icon-star',
				'activities' => 'class:icon-list',
				'stats'      => 'class:icon-chart-bar',
				'dues'       => 'class:icon-credit-card',
				'officers'   => 'class:icon-user',
				'sync'       => 'class:icon-refresh',
				'emailblast' => 'class:icon-envelope',
			];

			foreach ($iconMap as $view => $img)
			{
				$query = $db->createQuery()
					->update($db->quoteName('#__menu'))
					->set($db->quoteName('img') . ' = ' . $db->quote($img))
					->where($db->quoteName('client_id') . ' = 1')
					->where($db->quoteName('menutype') . ' = ' . $db->quote('main'))
					->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
					->where($db->quoteName('link') . ' LIKE ' . $db->quote('%view=' . $db->escape($view) . '%'));

				$db->setQuery($query);
				$db->execute();
			}
		}
		catch (\Throwable $e)
		{
			$this->logWarning('Admin menu sync failed: ' . $e->getMessage());
		}
	}

	/**
	 * Allow members to log their own activities when that action is unset.
	 *
	 * Explicit Allow or Deny values are left alone on later updates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function ensureActivityLogPermission(): void
	{
		$groupId = $this->getGroupId('Chapter Member');

		if (!$groupId)
		{
			return;
		}

		try
		{
			$db = $this->database();
			$asset = new \Joomla\CMS\Table\Asset($db);

			if (!$asset->loadByName('com_wendfold'))
			{
				return;
			}

			$rules = json_decode((string) $asset->rules, true);

			if (!is_array($rules))
			{
				$rules = [];
			}

			if (!isset($rules['wendfold.logactivity']) || !is_array($rules['wendfold.logactivity']))
			{
				$rules['wendfold.logactivity'] = [];
			}

			$key = (string) $groupId;

			if (array_key_exists($key, $rules['wendfold.logactivity']))
			{
				return;
			}

			$rules['wendfold.logactivity'][$key] = 1;
			$asset->rules = json_encode($rules);

			if (!$asset->store())
			{
				$this->logWarning('Unable to store wendfold.logactivity default rules.');
			}
		}
		catch (\Throwable $e)
		{
			$this->logWarning('Activity log permission default failed: ' . $e->getMessage());
		}
	}

	/**
	 * Allow wendfold.emailblast for Super Users and Administrators when that
	 * action has not already been configured on the component asset.
	 *
	 * Explicit Allow or Deny values are left alone on later updates.
	 *
	 * @return void
	 */
	private function ensureEmailBlastPermission(): void
	{
		$db = $this->database();
		$groupIds = [];

		foreach (['Super Users', 'Administrator'] as $title)
		{
			$groupId = $this->getGroupId($title);

			if ($groupId)
			{
				$groupIds[] = $groupId;
			}
		}

		if ($groupIds === [])
		{
			return;
		}

		try
		{
			$asset = new \Joomla\CMS\Table\Asset($db);

			if (!$asset->loadByName('com_wendfold'))
			{
				return;
			}

			$rules = json_decode((string) $asset->rules, true);

			if (!is_array($rules))
			{
				$rules = [];
			}

			if (!isset($rules['wendfold.emailblast']) || !is_array($rules['wendfold.emailblast']))
			{
				$rules['wendfold.emailblast'] = [];
			}

			$changed = false;

			foreach ($groupIds as $groupId)
			{
				$key = (string) $groupId;

				if (!array_key_exists($key, $rules['wendfold.emailblast']))
				{
					$rules['wendfold.emailblast'][$key] = 1;
					$changed = true;
				}
			}

			if (!$changed)
			{
				return;
			}

			$asset->rules = json_encode($rules);

			if (!$asset->store())
			{
				$this->logWarning('Unable to store wendfold.emailblast default rules.');
			}
		}
		catch (\Throwable $e)
		{
			$this->logWarning('Email blast permission default failed: ' . $e->getMessage());
		}
	}

	/**
	 * Ensure the canonical images/wendfold media folders exist.
	 *
	 * Front-end uploads and Media fields share this tree. Older images/profiles
	 * and images/locations folders are left alone when they already exist.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function ensureMediaFolders(): void
	{
		$folders = [
			JPATH_ROOT . '/images/wendfold/profiles',
			JPATH_ROOT . '/images/wendfold/locations',
		];

		if ($this->loadUninstallCleanup())
		{
			$folders = UninstallCleanup::mediaFolderPaths(JPATH_ROOT);
		}

		foreach ($folders as $path)
		{
			if (is_dir($path))
			{
				continue;
			}

			// Shared hosts sometimes emit PHP warnings (which can break the installer JSON response).
			// Use a warning-safe mkdir so installs do not fail when the filesystem is locked down.
			if (!$this->safeMkdir($path))
			{
				// Non-fatal: admin can create folders manually if filesystem is restricted.
				Factory::getApplication()->enqueueMessage(
					'Unable to create folder: ' . $path,
					'warning'
				);
			}
		}
	}

	/**
	 * Create a folder without allowing PHP warnings/notices to leak into output.
	 * This prevents Joomla installer AJAX responses from being corrupted.
	 *
	 * @param   string  $path  Absolute folder path to create.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function safeMkdir(string $path): bool
	{
		set_error_handler(
			static function () {
				return true;
			}
		);

		$ok = @mkdir($path, 0755, true);
		restore_error_handler();

		return $ok || is_dir($path);
	}

	/**
	 * Removes user groups Wendfold created, when they are empty.
	 *
	 * Option values are not a delete list. Core ids 1-9, the root group,
	 * and Public are never removed. A created group that still has members,
	 * or a nested group Wendfold did not create, is left in place. Children
	 * are removed before parents.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function removeUserGroups(): void
	{
		$this->loadComponentOptions();
		$created = $this->recordedCreatedGroupIds();

		if ($created === [])
		{
			return;
		}

		$groups = $this->loadUserGroupsById();
		$memberCounts = $this->memberCounts($created);

		if ($memberCounts === null)
		{
			return;
		}

		$deleteIds = ComponentOptions::groupIdsToDelete($created, $groups, $memberCounts);
		$this->rebuildUserGroups();
		$app = Factory::getApplication();
		$deleting = array_fill_keys($deleteIds, true);

		foreach ($created as $groupId)
		{
			if (!isset($groups[$groupId]) || isset($deleting[$groupId]))
			{
				continue;
			}

			if ($this->groupDeleteIsBlocked($groups[$groupId]))
			{
				continue;
			}

			$title = $groups[$groupId]['title'];
			$count = (int) ($memberCounts[$groupId] ?? 0);

			if ($count > 0)
			{
				$app->enqueueMessage(
					sprintf(
						'User group "%s" has %d member(s) and was NOT removed. Reassign members before uninstalling if a full removal is desired.',
						$title,
						$count
					),
					'warning'
				);

				continue;
			}

			$app->enqueueMessage(
				sprintf('User group "%s" was not removed because another group is still nested under it.', $title),
				'warning'
			);
		}

		foreach ($deleteIds as $groupId)
		{
			if ($this->groupDeleteIsBlocked($groups[$groupId] ?? []))
			{
				continue;
			}

			$title = (string) ($groups[$groupId]['title'] ?? '');

			try
			{
				$table = new Usergroup($this->database());
				$table->delete($groupId);
				$app->enqueueMessage(sprintf('Removed user group: %s', $title), 'success');
			}
			catch (\Throwable $exception)
			{
				$app->enqueueMessage(
					sprintf('Failed to remove user group "%s": %s', $title, $exception->getMessage()),
					'error'
				);
			}
		}

		$this->rebuildUserGroups();
	}

	/**
	 * Whether this row is a core, root, or Public group.
	 *
	 * @param   array  $group  id, parent_id, and title. Empty when the row is missing.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function groupDeleteIsBlocked(array $group): bool
	{
		$id = (int) ($group['id'] ?? 0);

		if ($id <= ComponentOptions::CORE_GROUP_ID_MAX || (int) ($group['parent_id'] ?? 0) === 0)
		{
			return true;
		}

		return strcasecmp((string) ($group['title'] ?? ''), 'Public') === 0;
	}

	/**
	 * Member counts for the given groups, or null when the count cannot be read.
	 *
	 * A missing id means that group has no members.
	 *
	 * @param   array  $groupIds  Group ids to count.
	 *
	 * @return  array<int, int>|null
	 *
	 * @since   1.0.0
	 */
	private function memberCounts(array $groupIds): ?array
	{
		$ids = [];

		foreach ($groupIds as $groupId)
		{
			$groupId = (int) $groupId;

			if ($groupId > 0)
			{
				$ids[$groupId] = $groupId;
			}
		}

		if ($ids === [])
		{
			return [];
		}

		$db = $this->database();
		$query = $db->createQuery()
			->select(
				[
					$db->quoteName('group_id'),
					'COUNT(*) AS ' . $db->quoteName('member_count'),
				]
			)
			->from($db->quoteName('#__user_usergroup_map'))
			->where($db->quoteName('group_id') . ' IN (' . implode(',', $ids) . ')')
			->group($db->quoteName('group_id'));

		try
		{
			$rows = $db->setQuery($query)->loadObjectList();
		}
		catch (\Throwable $exception)
		{
			$this->logWarning('User group member count failed: ' . $exception->getMessage());

			return null;
		}

		if (!\is_array($rows))
		{
			return null;
		}

		$groupColumn = 'group_id';
		$countColumn = 'member_count';
		$counts = [];

		foreach ($rows as $row)
		{
			if (!\is_object($row))
			{
				continue;
			}

			$groupId = (int) $row->$groupColumn;

			if ($groupId < 1)
			{
				continue;
			}

			$counts[$groupId] = (int) $row->$countColumn;
		}

		return $counts;
	}

	/**
	 * Group ids currently saved on the component.
	 *
	 * @return  array<string, mixed>
	 */
	private function storedGroupParams(): array
	{
		$db = $this->database();
		$query = $db->createQuery()
			->select($db->quoteName('params'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_wendfold'));
		$params = json_decode((string) $db->setQuery($query)->loadResult(), true);

		return is_array($params) ? $params : [];
	}

	/**
	 * Returns the database ID of a user group by title.
	 *
	 * @param   string  $title  The group title to look up
	 *
	 * @return  integer|null  The group ID, or null if not found
	 *
	 * @since   1.0.0
	 */
	private function getGroupId(string $title): ?int
	{
		$db = $this->database();

		$query = $db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__usergroups'))
			->where($db->quoteName('title') . ' = ' . $db->quote($title));

		$result = $db->setQuery($query)->loadResult();

		return $result ? (int) $result : null;
	}

	/**
	 * Displays a welcome message after fresh installation.
	 *
	 * @return  void
	 */
	private function showWelcomeMessage(): void
	{
		Factory::getApplication()->enqueueMessage(Text::_('COM_WENDFOLD_INSTALL_GROUPS_READY'), 'success');
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
				return new Com_WendfoldInstallerScript;
			}
		);
	}
};
