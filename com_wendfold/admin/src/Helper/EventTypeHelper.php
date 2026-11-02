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

use Joomla\CMS\Language\Text;

/**
 * Event type labels for admin and site display.
 *
 * Chapters name types in #__wendfold_event_types. Titles may be language
 * keys or custom text. Labels always come through LookupTitle; this helper
 * is the single call site for event-type wording so reports and templates
 * do not keep private 1-6 maps.
 *
 * @since  1.0.0
 */
final class EventTypeHelper
{
	/**
	 * Language key used when the type title is missing.
	 *
	 * @since  1.0.0
	 */
	public const FALLBACK_LABEL_KEY = 'COM_WENDFOLD_EVENT_BEHAVIOR_GENERAL';

	/**
	 * Language keys this helper may return via Text::_().
	 *
	 * Keep these defined in both admin and site en-GB ini files.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public static function languageKeys(): array
	{
		return [
			self::FALLBACK_LABEL_KEY,
		];
	}

	/**
	 * Translated label for a stored event type title.
	 *
	 * Empty or missing titles fall back to the general behavior label so
	 * overnight and chapter-defined types never render as "Unknown".
	 *
	 * @param   string|null  $title  event_types.title or a joined alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function label(?string $title): string
	{
		$label = LookupTitle::display((string) $title);

		return $label !== '' ? $label : Text::_(self::FALLBACK_LABEL_KEY);
	}

	/**
	 * Admin badge CSS class for the type behavior.
	 *
	 * @param   integer  $behavior  event_types.behavior.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function adminBadgeClass(int $behavior): string
	{
		return EventTypeBehavior::adminBadgeClass($behavior);
	}

	/**
	 * Site badge CSS class for the type behavior.
	 *
	 * @param   integer  $behavior  event_types.behavior.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function siteBadgeClass(int $behavior): string
	{
		return EventTypeBehavior::siteBadgeClass($behavior);
	}
}
