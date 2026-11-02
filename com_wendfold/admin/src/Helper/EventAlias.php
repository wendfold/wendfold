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
 * Builds a stored event alias.
 *
 * A blank alias comes from the title. ApplicationHelper::stringURLSafe (passed
 * in by the caller) normalizes it, and an empty result falls back to a
 * date-time string. A generated alias, and a save-as-copy, takes the next
 * dash suffix (-2, -3, ...) when another row already has it. The caller
 * passes aliases already stored on other rows, including unpublished and
 * trashed rows. A typed alias that collides is refused.
 *
 * Reserved view names and all-digit aliases get the row id appended when the
 * row already has one, otherwise the same dash suffix. A generated alias is
 * shortened so the stored value is at most 400 characters.
 *
 * @since  1.0.0
 */
final class EventAlias
{
	/**
	 * Column width of #__wendfold_events.alias.
	 *
	 * @var  integer
	 *
	 * @since  1.0.0
	 */
	public const MAX_LENGTH = 400;

	/**
	 * A typed alias is already stored on another row.
	 *
	 * @var  string
	 *
	 * @since  1.0.0
	 */
	public const ERROR_UNIQUE = 'unique';

	/**
	 * A typed alias is longer than the column.
	 *
	 * @var  string
	 *
	 * @since  1.0.0
	 */
	public const ERROR_LENGTH = 'length';

	/**
	 * View names an event alias must not use on its own.
	 *
	 * @var  list<string>
	 *
	 * @since  1.0.0
	 */
	public const RESERVED = [
		'trip',
		'rcattendance',
		'meetingattendance',
		'volunteerboard',
	];

	/**
	 * Reason for the last refused alias, or null after a successful build.
	 *
	 * @var  string|null
	 *
	 * @since  1.0.0
	 */
	private static ?string $error = null;

	/**
	 * Reason for the last refused alias.
	 *
	 * @return  string|null  ERROR_UNIQUE, ERROR_LENGTH, or null.
	 *
	 * @since   1.0.0
	 */
	public static function error(): ?string
	{
		return self::$error;
	}

	/**
	 * Return the alias to store, or null when a typed alias is refused.
	 *
	 * @param   string                    $alias         Submitted alias. Blank means generate from the title.
	 * @param   string                    $title         Event title.
	 * @param   integer                   $id            Current row id, or 0 for a new row.
	 * @param   array<int, string>        $takenAliases  Aliases on every other row.
	 * @param   string                    $dateFallback  Date-time string used when the slug is empty. Y-m-d-H-i-s.
	 * @param   callable(string): string  $urlSafe       Normalizer. Core passes ApplicationHelper::stringURLSafe.
	 * @param   boolean                   $saveAsCopy    True when this save is a copy, which may take a suffix.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function build(
		string $alias,
		string $title,
		int $id,
		array $takenAliases,
		string $dateFallback,
		callable $urlSafe,
		bool $saveAsCopy = false
	): ?string {
		self::$error = null;
		$submitted = trim($alias);
		$generated = $submitted === '' || $saveAsCopy;
		$source = $submitted !== '' ? $submitted : trim($title);
		$safe = trim((string) $urlSafe($source));

		if (self::isBlankSlug($safe))
		{
			$safe = trim($dateFallback);
		}

		if (self::isBlankSlug($safe))
		{
			$safe = 'event';
		}

		$candidate = self::unblock($safe, $id);
		$taken = [];

		foreach ($takenAliases as $existing)
		{
			$taken[(string) $existing] = true;
		}

		if (!$generated)
		{
			if (self::length($candidate) > self::MAX_LENGTH)
			{
				self::$error = self::ERROR_LENGTH;

				return null;
			}

			if (isset($taken[$candidate]))
			{
				self::$error = self::ERROR_UNIQUE;

				return null;
			}

			return $candidate;
		}

		$candidate = self::limitLength($candidate);
		$guard = 0;

		while (isset($taken[$candidate]) && $guard < 10000)
		{
			$next = self::limitLength(self::increment($candidate));

			if ($next === '' || $next === $candidate)
			{
				break;
			}

			$candidate = $next;
			$guard++;
		}

		if ($candidate === '')
		{
			$candidate = 'event';
		}

		return $candidate;
	}

	/**
	 * Whether the slug has no letters or digits.
	 *
	 * @param   string  $alias  URL-safe text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function isBlankSlug(string $alias): bool
	{
		return trim(str_replace('-', '', $alias)) === '';
	}

	/**
	 * Whether the alias is a reserved view name or a pure number.
	 *
	 * @param   string  $alias  URL-safe text.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function isBlocked(string $alias): bool
	{
		if (preg_match('/^\d+$/', $alias) === 1)
		{
			return true;
		}

		return \in_array(strtolower($alias), self::RESERVED, true);
	}

	/**
	 * Move a reserved or all-digit alias off that exact value.
	 *
	 * @param   string   $alias  URL-safe text.
	 * @param   integer  $id     Current row id, or 0 for a new row.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function unblock(string $alias, int $id): string
	{
		if (!self::isBlocked($alias))
		{
			return $alias;
		}

		if ($id > 0)
		{
			return $alias . '-' . $id;
		}

		return self::increment($alias);
	}

	/**
	 * Next dash suffix, matching Joomla StringHelper::increment(..., 'dash').
	 *
	 * @param   string  $alias  Current candidate.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function increment(string $alias): string
	{
		if (preg_match('/-(\d+)$/', $alias, $matches) === 1)
		{
			$next = (int) $matches[1] + 1;

			return (string) preg_replace('/-(\d+)$/', '-' . $next, $alias, 1);
		}

		return $alias . '-2';
	}

	/**
	 * Character length, so a multibyte slug is measured the way VARCHAR is.
	 *
	 * @param   string  $alias  Candidate alias.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function length(string $alias): int
	{
		return mb_strlen($alias, 'UTF-8');
	}

	/**
	 * Shorten a generated alias to the column width, keeping a dash suffix.
	 *
	 * The base is cut on a hyphen when one sits inside the remaining room.
	 *
	 * @param   string  $alias  Candidate alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function limitLength(string $alias): string
	{
		if (self::length($alias) <= self::MAX_LENGTH)
		{
			return $alias;
		}

		$base = $alias;
		$suffix = '';

		if (preg_match('/^(.*)-(\d+)$/u', $alias, $matches) === 1)
		{
			$base = $matches[1];
			$suffix = '-' . $matches[2];
		}

		$room = self::MAX_LENGTH - self::length($suffix);

		if ($room < 1)
		{
			return self::cut($alias, self::MAX_LENGTH);
		}

		return self::cut($base, $room) . $suffix;
	}

	/**
	 * Cut text to a character limit, preferring a hyphen boundary.
	 *
	 * @param   string   $value  Text to cut.
	 * @param   integer  $max    Maximum characters.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function cut(string $value, int $max): string
	{
		if ($max < 1)
		{
			return '';
		}

		if (self::length($value) <= $max)
		{
			return $value;
		}

		$cut = mb_substr($value, 0, $max, 'UTF-8');
		$hyphen = mb_strrpos($cut, '-', 0, 'UTF-8');

		if ($hyphen !== false && $hyphen > 0)
		{
			$cut = mb_substr($cut, 0, $hyphen, 'UTF-8');
		}

		return rtrim($cut, '-');
	}
}
