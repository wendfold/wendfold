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

/**
 * Strip recipient addresses from mailer and exception text before logging.
 *
 * @since  1.0.0
 */
final class MailerErrorRedactor
{
	/**
	 * Quoted local-part: "..." with linear escaped-pair matching.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const QUOTED_LOCAL = '"(?:[^"\\\\]++|\\\\.)*+"';

	/**
	 * Unquoted local-part (atom / dot-atom characters, plus Unicode).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const ATOM_LOCAL = '[^\s<>@"(),:;\[\]\\\\/]++';

	/**
	 * IPv4 address literal domain, e.g. [192.0.2.1].
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const IPV4_LITERAL = '\[(?:\d{1,3}\.){3}\d{1,3}\]';

	/**
	 * Hostname: one or more labels (dotless hosts included).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const HOST = '[^\s<>@"(),:;\[\]\/?#.]++(?:\.[^\s<>@"(),:;\[\]\/?#.]++)*+';

	/**
	 * Domain: IPv4 literal or hostname labels.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const DOMAIN = '(?:' . self::IPV4_LITERAL . '|' . self::HOST . ')';

	/**
	 * local@domain with quoted or atom local-part.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const ADDR = '(?:' . self::QUOTED_LOCAL . '|' . self::ATOM_LOCAL . ')@' . self::DOMAIN;

	/**
	 * Angle-bracketed address, then bare address.
	 *
	 * Possessive quantifiers keep matching linear-time. Trailing punctuation
	 * and ordinary '@' / '@mention' text are left alone.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const PATTERN = '~(?:<' . self::ADDR . '>|' . self::ADDR . ')~u';

	/**
	 * Replace every email address (including <a@b.c> forms) with [redacted].
	 *
	 * @param   string  $text  Mailer ErrorInfo, exception message, or similar.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function redact(string $text): string
	{
		$redacted = preg_replace(self::PATTERN, '[redacted]', $text);

		return \is_string($redacted) ? $redacted : $text;
	}

	/**
	 * Context prefix plus an optional redacted detail suffix.
	 *
	 * @param   string  $prefix     Traceable context (profile/event/blast ids).
	 * @param   string  $rawDetail  Raw mailer or exception text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function append(string $prefix, string $rawDetail): string
	{
		$detail = self::redact($rawDetail);

		if ($detail === '')
		{
			return $prefix;
		}

		return $prefix . ': ' . $detail;
	}
}
