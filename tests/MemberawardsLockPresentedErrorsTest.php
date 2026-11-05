<?php

/**
 * Wendfold - lock / presented DB-failure messages on MemberawardsModel.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * setLocked and setPresented must surface their own language keys on DB failure.
 *
 * @since  1.0.0
 */
final class MemberawardsLockPresentedErrorsTest extends TestCase
{
	/**
	 * setLocked catch uses ERR_LOCK_FAILED, not the grant string.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSetLockedFailureSurfacesLockFailedMessage(): void
	{
		$body = $this->methodBody('setLocked');
		$this->assertStringContainsString(
			"COM_WENDFOLD_MEMBERAWARDS_ERR_LOCK_FAILED",
			$body
		);
		$this->assertStringNotContainsString(
			"COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED",
			$body
		);
		$this->assertIniKey(
			'COM_WENDFOLD_MEMBERAWARDS_ERR_LOCK_FAILED',
			'Could not update the lock.'
		);
	}

	/**
	 * setPresented catch uses ERR_PRESENTED_FAILED, not the grant string.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSetPresentedFailureSurfacesPresentedFailedMessage(): void
	{
		$body = $this->methodBody('setPresented');
		$this->assertStringContainsString(
			"COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_FAILED",
			$body
		);
		$this->assertStringNotContainsString(
			"COM_WENDFOLD_MEMBERAWARDS_ERR_GRANT_FAILED",
			$body
		);
		$this->assertIniKey(
			'COM_WENDFOLD_MEMBERAWARDS_ERR_PRESENTED_FAILED',
			'Could not save the presented date.'
		);
	}

	/**
	 * Source of one MemberawardsModel method through its closing brace.
	 *
	 * @param   string  $name  Method name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function methodBody(string $name): string
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/MemberawardsModel.php'
		);
		$this->assertIsString($source);
		$pattern = '/public function ' . preg_quote($name, '/')
			. '\b.*?(?=\n\t(?:public|protected|private) function |\n\}\n?\z)/s';

		if (preg_match($pattern, $source, $match) !== 1)
		{
			$this->fail('Could not isolate MemberawardsModel::' . $name);
		}

		return $match[0];
	}

	/**
	 * Admin en-GB ini defines the expected key and value once.
	 *
	 * @param   string  $key    Language key.
	 * @param   string  $value  Expected text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function assertIniKey(string $key, string $value): void
	{
		$ini = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$this->assertIsString($ini);
		$line = $key . '="' . $value . '"';
		$this->assertStringContainsString($line, $ini);
		$this->assertSame(1, substr_count($ini, $key . '='));
	}
}
