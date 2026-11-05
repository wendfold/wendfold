<?php

/**
 * Wendfold - chapter group nested-set inheritance under the configured parent.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ComponentOptions.php';

/**
 * Installer-created chapter groups nest under Registered and inherit it.
 *
 * The installer inserts groups through Table\Usergroup and rebuilds the tree.
 * These assertions lock the parent_id seed and the nested-set inheritance
 * Joomla uses for permissions once lft and rgt are non-zero.
 *
 * @since  1.0.0
 */
final class InstallerUserGroupTreeTest extends TestCase
{
	/**
	 * Every chapter seed group sits under Registered and inherits it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testChapterGroupsInheritConfiguredRegisteredParent(): void
	{
		$registeredId = 2;
		$groups = [
			['id' => 1, 'parent_id' => 0, 'title' => 'Public'],
			['id' => $registeredId, 'parent_id' => 1, 'title' => 'Registered'],
		];
		$nextId = 10;
		$idsByParam = [];

		foreach (ComponentOptions::groups() as $seed)
		{
			$parentId = $registeredId;

			if ($seed['parent_param'] !== '')
			{
				$parentId = $idsByParam[$seed['parent_param']];
			}
			else
			{
				$this->assertSame('Registered', $seed['parent_title']);
			}

			$idsByParam[$seed['param']] = $nextId;
			$groups[] = [
				'id'        => $nextId,
				'parent_id' => $parentId,
				'title'     => $seed['title'],
			];
			$nextId++;
		}

		$placed = ComponentOptions::nestedPositions($groups);

		foreach ($idsByParam as $param => $groupId)
		{
			$this->assertArrayHasKey($groupId, $placed);
			$this->assertGreaterThan(0, $placed[$groupId]['lft']);
			$this->assertGreaterThan($placed[$groupId]['lft'], $placed[$groupId]['rgt']);
			$this->assertGreaterThan($placed[$registeredId]['lft'], $placed[$groupId]['lft']);
			$this->assertLessThan($placed[$registeredId]['rgt'], $placed[$groupId]['rgt']);

			$inherited = ComponentOptions::groupsForUser($placed, [$groupId]);

			$this->assertContains(
				$registeredId,
				$inherited,
				$param . ' must inherit Registered through nested-set bounds'
			);
			$this->assertContains(1, $inherited);
		}

		$memberId = $idsByParam['group_member'];
		$memberOnly = ComponentOptions::groupsForUser($placed, [$memberId]);

		$this->assertNotContains($idsByParam['group_officer'], $memberOnly);
		$this->assertNotContains($idsByParam['group_webmaster'], $memberOnly);
	}
}
