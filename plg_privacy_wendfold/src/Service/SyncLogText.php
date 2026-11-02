<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  Privacy.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Plugin\Privacy\Wendfold\Service;

\defined('_JEXEC') or die;

/**
 * Sync-log attribution and exact whole-token rewriting.
 *
 * The sync log has no profile_id column. A row is attributed to a member only
 * by performed_by or an exact whole-token match of that member's unique
 * member_number. Names never attribute a row. Substring overlaps (A122 inside
 * A1220) do not match.
 *
 * @since  1.0.0
 */
final class SyncLogText
{
	/**
	 * Whether a sync-log row is attributed to the member.
	 *
	 * Attribution uses the actor user id or an exact whole-token match of the
	 * unique member number. Names never attribute a row.
	 *
	 * @param   integer  $performedBy   sync_log.performed_by.
	 * @param   integer  $userId        Joomla user id of the member.
	 * @param   string   $detail        sync_log.log_detail.
	 * @param   string   $memberNumber  Unique member id, or empty when unknown.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isAttributedToMember(
		int $performedBy,
		int $userId,
		string $detail,
		string $memberNumber
	): bool
	{
		if ($performedBy === $userId)
		{
			return true;
		}

		$memberNumber = trim($memberNumber);

		if ($memberNumber === '')
		{
			return false;
		}

		return self::containsExactToken($detail, $memberNumber);
	}

	/**
	 * Whether haystack contains the token as a whole token.
	 *
	 * @param   string  $haystack  Free-text log detail.
	 * @param   string  $token     Non-empty member token.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function containsExactToken(string $haystack, string $token): bool
	{
		$token = trim($token);

		if ($token === '' || $haystack === '')
		{
			return false;
		}

		$pattern = self::patternFor($token);

		if ($pattern === null)
		{
			return false;
		}

		return preg_match($pattern, $haystack) === 1;
	}

	/**
	 * Whether any token appears as a whole token in haystack.
	 *
	 * @param   string        $haystack  Free-text log detail.
	 * @param   list<string>  $tokens    Member tokens.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function containsAnyExactToken(string $haystack, array $tokens): bool
	{
		foreach ($tokens as $token)
		{
			if (self::containsExactToken($haystack, (string) $token))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace whole-token occurrences. Longer tokens are applied first.
	 *
	 * @param   string        $haystack     Free-text log detail.
	 * @param   list<string>  $tokens       Member tokens.
	 * @param   string        $replacement  Replacement text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function replaceExactTokens(string $haystack, array $tokens, string $replacement): string
	{
		$unique = [];

		foreach ($tokens as $token)
		{
			$token = trim((string) $token);

			if ($token === '')
			{
				continue;
			}

			$unique[$token] = $token;
		}

		if ($unique === [])
		{
			return $haystack;
		}

		$ordered = array_values($unique);
		usort(
			$ordered,
			static fn (string $left, string $right): int => \strlen($right) <=> \strlen($left)
		);

		$result = $haystack;

		foreach ($ordered as $token)
		{
			$pattern = self::patternFor($token);

			if ($pattern === null)
			{
				continue;
			}

			$replaced = preg_replace($pattern, $replacement, $result);

			if (\is_string($replaced))
			{
				$result = $replaced;
			}
		}

		return $result;
	}

	/**
	 * Build a case-insensitive whole-token regex, or null when invalid.
	 *
	 * @param   string  $token  Non-empty token.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	private static function patternFor(string $token): ?string
	{
		$quoted = preg_quote($token, '/');
		$pattern = '/(?<![\p{L}\p{N}_])' . $quoted . '(?![\p{L}\p{N}_])/iu';

		if (@preg_match($pattern, '') === false)
		{
			return null;
		}

		return $pattern;
	}
}
