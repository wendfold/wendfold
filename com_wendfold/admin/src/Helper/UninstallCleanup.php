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

use Joomla\Database\DatabaseInterface;

/**
 * Idempotent uninstall cleanup for scheduler tasks and owned upload trees.
 *
 * Chapter groups are removed through Table\Usergroup elsewhere. Media under
 * images/profiles and images/locations is never deleted: those folders may
 * be shared. Member photos under images/wendfold/ are kept unless the
 * remove_uploaded_files option is on.
 *
 * @since  1.0.0
 */
final class UninstallCleanup
{
	/**
	 * Component param that opts in to deleting images/wendfold/ on uninstall.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const REMOVE_UPLOADED_FILES = 'remove_uploaded_files';

	/**
	 * Relative upload tree under the site root.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const UPLOAD_TREE = 'images/wendfold';

	/**
	 * Media folders created on install, relative to the site root.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public static function mediaFolders(): array
	{
		return [
			'images/wendfold/profiles',
			'images/wendfold/locations',
		];
	}

	/**
	 * Absolute paths for the install-time media folders.
	 *
	 * @param   string  $siteRoot  Site root (JPATH_ROOT).
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public static function mediaFolderPaths(string $siteRoot): array
	{
		$root = rtrim($siteRoot, '/\\');
		$paths = [];

		foreach (self::mediaFolders() as $relative)
		{
			$paths[] = $root . '/' . $relative;
		}

		return $paths;
	}

	/**
	 * Absolute path of the owned upload tree, or empty when the root is blank.
	 *
	 * @param   string  $siteRoot  Site root (JPATH_ROOT).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function uploadTreePath(string $siteRoot): string
	{
		$root = rtrim($siteRoot, '/\\');

		if ($root === '')
		{
			return '';
		}

		return $root . '/' . self::UPLOAD_TREE;
	}

	/**
	 * Whether component params opt in to deleting images/wendfold/.
	 *
	 * Missing or non-truthy values keep uploads. Only "1", 1, true, and "true"
	 * (case-insensitive) enable removal.
	 *
	 * @param   array  $params  Decoded component params.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function removeUploadedFilesEnabled(array $params): bool
	{
		$value = $params[self::REMOVE_UPLOADED_FILES] ?? 0;

		if ($value === true || $value === 1 || $value === '1')
		{
			return true;
		}

		return \is_string($value) && strcasecmp($value, 'true') === 0;
	}

	/**
	 * SQL LIKE pattern matching every Wendfold task-plugin routine type.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function schedulerTaskTypeLike(): string
	{
		return 'wendfold.%';
	}

	/**
	 * Delete #__scheduler_tasks rows for Wendfold task routines.
	 *
	 * Idempotent: a missing table or zero matching rows is not an error.
	 *
	 * @param   DatabaseInterface  $db  Site database.
	 *
	 * @return  integer  Rows deleted, or 0 when nothing was removed.
	 *
	 * @since   1.0.0
	 */
	public static function removeSchedulerTasks(DatabaseInterface $db): int
	{
		try
		{
			if (!self::tableExists($db, '#__scheduler_tasks'))
			{
				return 0;
			}

			$pattern = self::schedulerTaskTypeLike();
			$query = $db->createQuery()
				->delete($db->quoteName('#__scheduler_tasks'))
				->where($db->quoteName('type') . ' LIKE ' . $db->quote($pattern));
			$db->setQuery($query)->execute();

			return max(0, (int) $db->getAffectedRows());
		}
		catch (\Throwable)
		{
			return 0;
		}
	}

	/**
	 * Delete the owned upload tree when it exists.
	 *
	 * Idempotent: a missing path succeeds. Only paths under images/wendfold
	 * are accepted so a bad site root cannot escape that tree.
	 *
	 * @param   string  $uploadTreePath  Absolute path to images/wendfold.
	 *
	 * @return  boolean  True when the path is gone afterwards.
	 *
	 * @since   1.0.0
	 */
	public static function removeUploadTree(string $uploadTreePath): bool
	{
		$path = self::normalizeOwnedUploadPath($uploadTreePath);

		if ($path === '')
		{
			return true;
		}

		if (!file_exists($path))
		{
			return true;
		}

		if (is_file($path) || is_link($path))
		{
			return @unlink($path);
		}

		if (!is_dir($path))
		{
			return true;
		}

		return self::deleteDirectory($path);
	}

	/**
	 * Reject paths that are not the images/wendfold tree.
	 *
	 * @param   string  $uploadTreePath  Candidate absolute path.
	 *
	 * @return  string  Normalized path, or empty when unsafe.
	 *
	 * @since   1.0.0
	 */
	public static function normalizeOwnedUploadPath(string $uploadTreePath): string
	{
		$path = str_replace('\\', '/', trim($uploadTreePath));
		$path = rtrim($path, '/');

		if ($path === '' || str_contains($path, "\0"))
		{
			return '';
		}

		if (!str_ends_with($path, '/' . self::UPLOAD_TREE) && $path !== self::UPLOAD_TREE)
		{
			return '';
		}

		return $path;
	}

	/**
	 * Whether a table exists in the current MySQL schema.
	 *
	 * @param   DatabaseInterface  $db     Site database.
	 * @param   string             $table  Joomla table name with #__ prefix.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function tableExists(DatabaseInterface $db, string $table): bool
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
	 * Recursively delete a directory.
	 *
	 * @param   string  $directory  Absolute directory path.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function deleteDirectory(string $directory): bool
	{
		$items = @scandir($directory);

		if ($items === false)
		{
			return !is_dir($directory);
		}

		foreach ($items as $item)
		{
			if ($item === '.' || $item === '..')
			{
				continue;
			}

			$path = $directory . '/' . $item;

			if (is_dir($path) && !is_link($path))
			{
				if (!self::deleteDirectory($path))
				{
					return false;
				}

				continue;
			}

			if (!@unlink($path) && file_exists($path))
			{
				return false;
			}
		}

		return @rmdir($directory) || !is_dir($directory);
	}
}
