<?php

/**
 * Wendfold - metric_key must match ^[a-z0-9_]+$ with no silent rewrite.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Uppercase and mixed-case keys are rejected, not lowercased.
 *
 * Mirrors MetricdefinitionTable::check key rules without booting Joomla Table.
 *
 * @since  1.0.0
 */
final class MetricdefinitionKeyTest extends TestCase
{
	/**
	 * Same acceptance rule as MetricdefinitionTable::check.
	 *
	 * @param   string  $key  Candidate key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function isAcceptableKey(string $key): bool
	{
		return preg_match('/^[a-z0-9_]{1,50}$/', $key) === 1;
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUppercaseKeyIsRejectedAndTableDoesNotLowercase(): void
	{
		$raw = 'Q90Upper';
		$trimmed = trim($raw);

		$this->assertFalse(self::isAcceptableKey($trimmed));

		$source = file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/src/Table/MetricdefinitionTable.php');
		$this->assertIsString($source);
		$this->assertStringNotContainsString('strtolower(trim((string) $this->metric_key))', $source);
		$this->assertStringContainsString("preg_match('/^[a-z0-9_]{1,50}$/', \$key)", $source);
		$this->assertStringContainsString('COM_WENDFOLD_METRIC_KEY_INVALID', $source);
	}

	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testValidLowercaseKeyIsAccepted(): void
	{
		$this->assertTrue(self::isAcceptableKey('distance_ytd'));
		$this->assertTrue(self::isAcceptableKey('a'));
		$this->assertFalse(self::isAcceptableKey('Distance'));
		$this->assertFalse(self::isAcceptableKey('has-dash'));
	}
}
