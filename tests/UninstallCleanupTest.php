<?php

/**
 * Wendfold - uninstall cleanup for scheduler tasks and upload trees.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\UninstallCleanup;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/UninstallCleanup.php';

/**
 * Uninstall removes owned scheduler tasks and optional upload trees safely.
 *
 * @since  1.0.0
 */
final class UninstallCleanupTest extends TestCase
{
	/**
	 * Install creates the canonical images/wendfold tree only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMediaFoldersUseWendfoldTree(): void
	{
		$this->assertSame(
			[
				'images/wendfold/profiles',
				'images/wendfold/locations',
			],
			UninstallCleanup::mediaFolders()
		);
		$this->assertSame(
			[
				'/site/images/wendfold/profiles',
				'/site/images/wendfold/locations',
			],
			UninstallCleanup::mediaFolderPaths('/site')
		);
		$this->assertSame(
			'/site/images/wendfold',
			UninstallCleanup::uploadTreePath('/site')
		);
	}

	/**
	 * Uploaded files stay unless the opt-in option is enabled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemoveUploadedFilesDefaultsOff(): void
	{
		$this->assertFalse(UninstallCleanup::removeUploadedFilesEnabled([]));
		$this->assertFalse(UninstallCleanup::removeUploadedFilesEnabled(['remove_uploaded_files' => 0]));
		$this->assertFalse(UninstallCleanup::removeUploadedFilesEnabled(['remove_uploaded_files' => '0']));
		$this->assertTrue(UninstallCleanup::removeUploadedFilesEnabled(['remove_uploaded_files' => 1]));
		$this->assertTrue(UninstallCleanup::removeUploadedFilesEnabled(['remove_uploaded_files' => '1']));
		$this->assertTrue(UninstallCleanup::removeUploadedFilesEnabled(['remove_uploaded_files' => true]));
	}

	/**
	 * Scheduler cleanup targets every Wendfold task-plugin routine type.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSchedulerTaskTypeLikeMatchesPluginRoutines(): void
	{
		$pattern = UninstallCleanup::schedulerTaskTypeLike();

		$this->assertSame('wendfold.%', $pattern);

		foreach ([
				'wendfold.lapsed_cleanup',
				'wendfold.reminder_emails',
				'wendfold.event_reminders',
				'wendfold.award_recompute',
				'wendfold.milestone_check',
			] as $type
		)
		{
			$this->assertTrue(str_starts_with($type, 'wendfold.'));
		}

		$this->assertFalse(str_starts_with('plg_task_something.else', 'wendfold.'));
	}

	/**
	 * Only the images/wendfold tree is accepted for deletion.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNormalizeOwnedUploadPathRejectsForeignTrees(): void
	{
		$this->assertSame(
			'/var/www/images/wendfold',
			UninstallCleanup::normalizeOwnedUploadPath('/var/www/images/wendfold/')
		);
		$this->assertSame('', UninstallCleanup::normalizeOwnedUploadPath('/var/www/images/profiles'));
		$this->assertSame('', UninstallCleanup::normalizeOwnedUploadPath('/var/www/images/locations'));
		$this->assertSame('', UninstallCleanup::normalizeOwnedUploadPath('/tmp'));
		$this->assertSame('', UninstallCleanup::normalizeOwnedUploadPath(''));
	}

	/**
	 * Removing the upload tree is idempotent and leaves sibling folders alone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemoveUploadTreeIsIdempotent(): void
	{
		$root = sys_get_temp_dir() . '/wendfold-uninstall-' . bin2hex(random_bytes(4));
		$tree = $root . '/images/wendfold';
		$profiles = $tree . '/profiles';
		$shared = $root . '/images/profiles';

		$this->assertTrue(mkdir($profiles, 0777, true));
		$this->assertTrue(mkdir($shared, 0777, true));
		$this->assertNotFalse(file_put_contents($profiles . '/1.jpg', 'x'));
		$this->assertNotFalse(file_put_contents($shared . '/keep.jpg', 'y'));

		try
		{
			$this->assertTrue(UninstallCleanup::removeUploadTree($tree));
			$this->assertDirectoryDoesNotExist($tree);
			$this->assertDirectoryExists($shared);
			$this->assertFileExists($shared . '/keep.jpg');

			$this->assertTrue(UninstallCleanup::removeUploadTree($tree));
			$this->assertTrue(UninstallCleanup::removeUploadTree($shared));
			$this->assertDirectoryExists($shared);
		}
		finally
		{
			$this->removeTree($root);
		}
	}

	/**
	 * Delete a temporary directory tree used by this test.
	 *
	 * @param   string  $directory  Absolute directory path.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function removeTree(string $directory): void
	{
		if (!is_dir($directory))
		{
			return;
		}

		$items = scandir($directory);

		if ($items === false)
		{
			return;
		}

		foreach ($items as $item)
		{
			if ($item === '.' || $item === '..')
			{
				continue;
			}

			$path = $directory . '/' . $item;

			if (is_dir($path))
			{
				$this->removeTree($path);
				continue;
			}

			unlink($path);
		}

		rmdir($directory);
	}
}
