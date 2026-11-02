<?php

/**
 * Wendfold - Stored event alias.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventAlias;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventAlias.php';

/**
 * Alias generation, uniqueness, reserved words, and all-digit values.
 *
 * @since  1.0.0
 */
final class EventAliasTest extends TestCase
{
	private const DATE = '2026-10-25-04-19-00';

	/**
	 * A blank alias is generated from the title.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBlankAliasComesFromTheTitle(): void
	{
		$alias = $this->build('', 'Saturday Morning Ride', 0, []);

		$this->assertSame('saturday-morning-ride', $alias);
	}

	/**
	 * A typed alias is normalized, and the title is left unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedAliasIsNormalizedAndIgnoresTheTitle(): void
	{
		$alias = $this->build('My Custom Alias!', 'Saturday Morning Ride', 0, []);

		$this->assertSame('my-custom-alias', $alias);
	}

	/**
	 * A title with no letters or digits falls back to the date-time string.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmptySlugFallsBackToTheDateTime(): void
	{
		$alias = $this->build('', '***', 0, []);

		$this->assertSame(self::DATE, $alias);
	}

	/**
	 * An edit keeps the stored alias when the title changes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEditKeepsTheStoredAliasWhenTheTitleChanges(): void
	{
		$alias = $this->build('kept-slug', 'A Different Title', 4, ['a-different-title']);

		$this->assertSame('kept-slug', $alias);
	}

	/**
	 * Clearing the alias on an edit generates a new one from the title.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testClearingTheAliasOnEditRegeneratesFromTheTitle(): void
	{
		$alias = $this->build('', 'A Different Title', 4, ['kept-slug']);

		$this->assertSame('a-different-title', $alias);
	}

	/**
	 * A taken alias gets the next dash suffix.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSuffixWhenTheAliasIsTaken(): void
	{
		$alias = $this->build('', 'Chapter Ride', 0, ['chapter-ride', 'chapter-ride-2']);

		$this->assertSame('chapter-ride-3', $alias);
	}

	/**
	 * An alias held by another row, including a trashed row, still gets a suffix.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSuffixWhenATrashedRowHoldsTheAlias(): void
	{
		// The table loads every alias, including unpublished and trashed rows.
		// A taken value here is that list, so a trashed row still forces a suffix.
		$alias = $this->build('', 'Chapter Ride', 0, ['chapter-ride']);

		$this->assertSame('chapter-ride-2', $alias);
	}

	/**
	 * A row keeps its alias when no other row uses it.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOwnAliasIsKeptWhenOtherRowsDoNotUseIt(): void
	{
		$alias = $this->build('chapter-ride', 'Chapter Ride', 8, ['chapter-ride-2']);

		$this->assertSame('chapter-ride', $alias);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function reservedProvider(): array
	{
		$cases = [];

		foreach (EventAlias::RESERVED as $word)
		{
			$cases[$word] = [$word];
		}

		return $cases;
	}

	#[DataProvider('reservedProvider')]
	/**
	 * A reserved view name gets a dash suffix on a new row.
	 *
	 * @param   string  $word  Reserved view name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReservedWordGetsASuffixOnANewRow(string $word): void
	{
		$alias = $this->build($word, 'Title', 0, []);

		$this->assertSame($word . '-2', $alias);
	}

	#[DataProvider('reservedProvider')]
	/**
	 * A reserved view name gets the row id appended on an existing row.
	 *
	 * @param   string  $word  Reserved view name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReservedWordGetsTheIdOnAnExistingRow(string $word): void
	{
		$alias = $this->build($word, 'Title', 15, []);

		$this->assertSame($word . '-15', $alias);
	}

	/**
	 * A typed reserved word is refused when the id suffix is already stored.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedReservedWordIsRefusedWhenTheIdSuffixIsTaken(): void
	{
		$alias = EventAlias::build('trip', 'Trip', 15, ['trip-15'], self::DATE, self::urlSafe(...));

		$this->assertNull($alias);
		$this->assertSame(EventAlias::ERROR_UNIQUE, EventAlias::error());
	}

	/**
	 * Save as copy keeps incrementing when the reserved id suffix is taken.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSaveAsCopyIncrementsWhenTheReservedIdSuffixIsTaken(): void
	{
		$alias = $this->build('trip', 'Trip', 15, ['trip-15'], true);

		$this->assertSame('trip-16', $alias);
	}

	/**
	 * An all-digit alias gets a dash suffix on a new row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllDigitAliasGetsASuffixOnANewRow(): void
	{
		$alias = $this->build('123', '123', 0, []);

		$this->assertSame('123-2', $alias);
	}

	/**
	 * An all-digit alias gets the row id appended on an existing row.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAllDigitAliasGetsTheIdOnAnExistingRow(): void
	{
		$alias = $this->build('123', 'Numbers', 9, []);

		$this->assertSame('123-9', $alias);
	}

	/**
	 * A typed all-digit alias is refused when the id suffix is already stored.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedAllDigitAliasIsRefusedWhenTheIdSuffixIsTaken(): void
	{
		$alias = EventAlias::build('123', 'Numbers', 9, ['123-9'], self::DATE, self::urlSafe(...));

		$this->assertNull($alias);
		$this->assertSame(EventAlias::ERROR_UNIQUE, EventAlias::error());
	}

	/**
	 * Save as copy keeps incrementing when the all-digit id suffix is taken.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSaveAsCopyIncrementsWhenTheAllDigitIdSuffixIsTaken(): void
	{
		$alias = $this->build('123', 'Numbers', 9, ['123-9'], true);

		$this->assertSame('123-10', $alias);
	}

	/**
	 * A typed alias that another row holds, including a trashed row, is refused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedAliasThatCollidesIsRefused(): void
	{
		$alias = EventAlias::build('chapter-ride', 'Other Title', 3, ['chapter-ride'], self::DATE, self::urlSafe(...));

		$this->assertNull($alias);
		$this->assertSame(EventAlias::ERROR_UNIQUE, EventAlias::error());
	}

	/**
	 * Save as copy suffixes a typed alias that another row already holds.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSaveAsCopySuffixesACollidingAlias(): void
	{
		$alias = $this->build('chapter-ride', 'Chapter Ride', 0, ['chapter-ride', 'chapter-ride-2'], true);

		$this->assertSame('chapter-ride-3', $alias);
		$this->assertNull(EventAlias::error());
	}

	/**
	 * A typed alias longer than the column is refused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedAliasLongerThanTheColumnIsRefused(): void
	{
		$alias = EventAlias::build(str_repeat('d', 401), 'Title', 0, [], self::DATE, self::urlSafe(...));

		$this->assertNull($alias);
		$this->assertSame(EventAlias::ERROR_LENGTH, EventAlias::error());
	}

	/**
	 * A typed alias of the column width is kept.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTypedAliasAtTheColumnWidthIsKept(): void
	{
		$alias = $this->build(str_repeat('d', 400), 'Title', 0, []);

		$this->assertSame(str_repeat('d', 400), $alias);
	}

	/**
	 * A generated alias with no hyphen is shortened so the suffix still fits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGeneratedAliasKeepsTheSuffixInsideTheColumn(): void
	{
		$title = str_repeat('b', 420);
		$alias = $this->build('', $title, 0, [str_repeat('b', 400)]);

		$this->assertSame(str_repeat('b', 398) . '-2', $alias);
		$this->assertSame(400, mb_strlen($alias, 'UTF-8'));
	}

	/**
	 * A growing numeric suffix shrinks the base so the stored alias still fits.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSuffixGrowthStaysInsideTheColumn(): void
	{
		$base = str_repeat('c', 398);
		$taken = [$base];

		for ($i = 2; $i <= 9; $i++)
		{
			$taken[] = $base . '-' . $i;
		}

		$alias = $this->build('', $base, 0, $taken);

		$this->assertSame(str_repeat('c', 397) . '-10', $alias);
		$this->assertSame(400, mb_strlen($alias, 'UTF-8'));
	}

	/**
	 * A long generated alias is cut on a hyphen.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGeneratedAliasTruncatesOnAHyphen(): void
	{
		$token = str_repeat('a', 50);
		$title = implode('-', array_fill(0, 12, $token));
		$alias = $this->build('', $title, 0, []);
		$expected = str_repeat('a', 50) . str_repeat('-' . str_repeat('a', 50), 6);

		$this->assertSame($expected, $alias);
		$this->assertSame(356, mb_strlen($alias, 'UTF-8'));
	}

	/**
	 * Multibyte characters count as one character each, not one byte.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGeneratedMultibyteAliasCountsCharacters(): void
	{
		$title = str_repeat('é', 450);
		$alias = EventAlias::build(
			'',
			$title,
			0,
			[],
			self::DATE,
			static fn (string $value): string => $value
		);

		$this->assertSame(str_repeat('é', 400), $alias);
		$this->assertSame(400, mb_strlen((string) $alias, 'UTF-8'));
		$this->assertGreaterThan(400, \strlen((string) $alias));
	}

	/**
	 * A reserved word that already has a dash suffix is allowed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDashSuffixOfAReservedWordIsAllowed(): void
	{
		$alias = $this->build('trip-2', 'Trip', 0, []);

		$this->assertSame('trip-2', $alias);
	}

	/**
	 * Build an alias with the ASCII normalizer.
	 *
	 * @param   string   $alias       Submitted alias.
	 * @param   string   $title       Event title.
	 * @param   integer  $id          Row id, or 0 for a new row.
	 * @param   array    $taken       Aliases already stored on other rows.
	 * @param   boolean  $saveAsCopy  True when the save is a copy.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function build(string $alias, string $title, int $id, array $taken, bool $saveAsCopy = false): string
	{
		$built = EventAlias::build($alias, $title, $id, $taken, self::DATE, self::urlSafe(...), $saveAsCopy);
		$this->assertIsString($built);

		return $built;
	}

	/**
	 * ASCII stand-in for ApplicationHelper::stringURLSafe.
	 *
	 * @param   string  $value  Raw title or alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function urlSafe(string $value): string
	{
		$value = str_replace('-', ' ', $value);
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

		return trim($value, '-');
	}
}
