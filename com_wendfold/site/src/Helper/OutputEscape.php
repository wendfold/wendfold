<?php

/**
 * Wendfold - HTML escaping for untrusted text.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Escapes text before it is placed in HTML.
 *
 * Confirm prompts are plain text in a data-confirm attribute. A member name
 * or a translated sentence is escaped for that attribute, then read back as
 * text. A welcome sentence that already contains markup escapes only the
 * untrusted values, then fills the trusted format.
 *
 * @since  1.0.0
 */
abstract class OutputEscape
{
	/**
	 * Escape a string for HTML text or a quoted attribute.
	 *
	 * Matches HtmlView::escape(): ENT_QUOTES and UTF-8. A quote in the value
	 * cannot end the attribute, so it cannot become script.
	 *
	 * @param   string  $value  Untrusted text.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function html(string $value): string
	{
		return htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Whether filtered HTML has anything visible after empty-editor markup.
	 *
	 * Editors often save `<p></p>`, `<p>&nbsp;</p>`, or `<br>` when cleared.
	 * Those are not content. An img, iframe, or video tag counts as content.
	 *
	 * @param   string  $html  Filtered HTML (for example event description).
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function hasVisibleContent(string $html): bool
	{
		if (preg_match('/<(?:img|iframe|video)\b/i', $html) === 1)
		{
			return true;
		}

		return self::plainTextFromHtml($html) !== '';
	}

	/**
	 * Build a plain-text teaser from filtered HTML and escape it once.
	 *
	 * Block tags become spaces before tags are stripped so adjacent words are
	 * not glued together. Entities are decoded, NBSP is treated as whitespace,
	 * then the result is truncated on character boundaries so an entity is
	 * never cut mid-sequence. Empty editor markup yields an empty string.
	 * The return value is ready to echo.
	 *
	 * @param   string   $html       Filtered HTML (for example event description).
	 * @param   integer  $maxLength  Maximum plain-text length before an ellipsis.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function htmlExcerpt(string $html, int $maxLength): string
	{
		if ($maxLength < 1)
		{
			return '';
		}

		$plain = self::plainTextFromHtml($html);

		if ($plain === '')
		{
			return '';
		}

		if (mb_strlen($plain) > $maxLength)
		{
			$plain = mb_substr($plain, 0, $maxLength) . '...';
		}

		return self::html($plain);
	}

	/**
	 * Plain text from HTML: space block tags, strip tags, decode, collapse.
	 *
	 * Opening and closing block-level and line-break tags are replaced with a
	 * space first so strip_tags does not run words together across elements.
	 * Inline tags are left for strip_tags and do not insert spaces. NBSP
	 * (U+00A0) and other Unicode whitespace become ordinary spaces so trim()
	 * can clear empty-editor leftovers.
	 *
	 * @param   string  $html  Filtered HTML.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function plainTextFromHtml(string $html): string
	{
		// Case-insensitive; attributes and self-closing forms allowed.
		$html = preg_replace(
			'#</?(?:p|div|br|li|ul|ol|h[1-6]|blockquote|pre|tr|td|th|table|section|article|header|footer|hr|dd|dt|dl)\b[^>]*>#iu',
			' ',
			$html
		) ?? $html;

		$plain = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
		$plain = str_replace("\u{00A0}", ' ', $plain);
		$plain = preg_replace('/\s+/u', ' ', $plain) ?? '';

		return trim($plain);
	}

	/**
	 * Escape a confirm prompt for a data-confirm attribute.
	 *
	 * The message is plain text. Placeholders are filled first, then the whole
	 * sentence is escaped, so a name or an apostrophe in a translation stays
	 * inside the attribute.
	 *
	 * @param   string  $message    Prompt, or a sprintf format when values follow.
	 * @param   string  ...$values  Values for the format. Not escaped separately.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function confirm(string $message, string ...$values): string
	{
		if ($values !== [])
		{
			$message = sprintf($message, ...$values);
		}

		return self::html($message);
	}

	/**
	 * Escape each value, then fill a trusted HTML format.
	 *
	 * Use this when the format comes from a language string that contains
	 * markup around the placeholders. The values cannot add tags.
	 *
	 * @param   string  $format     Trusted sprintf format that may contain HTML.
	 * @param   string  ...$values  Untrusted placeholder values.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function sprintfHtml(string $format, string ...$values): string
	{
		$escaped = array_map(self::html(...), $values);

		return sprintf($format, ...$escaped);
	}

	/**
	 * Render a short label with an accessible expansion (title + visually-hidden).
	 *
	 * Used for Departure/Return so Kick Stands Up/Down is not hover-only.
	 * Callers pass Text::_() results; each string is escaped exactly once.
	 *
	 * @param   string  $label         Visible label (e.g. Departure).
	 * @param   string  $description   Tooltip / accessible expansion text.
	 *
	 * @return  string  Escaped HTML for the labelled term.
	 *
	 * @since   1.0.0
	 */
	public static function termWithTooltip(string $label, string $description): string
	{
		$labelHtml = htmlspecialchars($label, \ENT_QUOTES, 'UTF-8');
		$descriptionHtml = htmlspecialchars($description, \ENT_QUOTES, 'UTF-8');

		return '<span title="' . $descriptionHtml . '">'
			. $labelHtml
			. '<span class="visually-hidden"> (' . $descriptionHtml . ')</span>'
			. '</span>';
	}

	/**
	 * Render a short label with tooltip, then an escaped trailing value.
	 *
	 * Callers pass Text::_() results and a raw value; each is escaped once.
	 *
	 * @param   string  $label         Visible label (e.g. Departure).
	 * @param   string  $description   Tooltip / accessible expansion text.
	 * @param   string  $value         Trailing value (e.g. a time).
	 *
	 * @return  string  Escaped HTML.
	 *
	 * @since   1.0.0
	 */
	public static function termWithTooltipAndValue(string $label, string $description, string $value): string
	{
		$html = self::termWithTooltip($label, $description);

		if ($value !== '')
		{
			$html .= ' ' . htmlspecialchars($value, \ENT_QUOTES, 'UTF-8');
		}

		return $html;
	}
}
