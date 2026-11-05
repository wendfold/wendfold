<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSWebApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Wendfold admin brand mark helpers (toolbar title + media paths).
 *
 * @since  1.0.0
 */
final class AdminBrand
{
	/**
	 * Relative media path for the mono mark SVG (16px grid, currentColor).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MARK_SVG = 'media/com_wendfold/images/wendfold-mark.svg';

	/**
	 * Relative media path for the white 24px mark PNG (dark sidebar img).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MARK_PNG = 'media/com_wendfold/images/wendfold-mark-24-white.png';

	/**
	 * Relative media path for the fixed-colour extension icon SVG.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ICON_SVG = 'media/com_wendfold/images/wendfold-icon.svg';

	/**
	 * Relative media path for the 128px extension icon PNG.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const ICON_PNG = 'media/com_wendfold/images/wendfold-icon-128.png';

	/**
	 * Brand media file basenames shipped under media/com_wendfold/images/.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public static function mediaFilenames(): array
	{
		return [
			'wendfold-mark.svg',
			'wendfold-mark-24-white.png',
			'wendfold-icon.svg',
			'wendfold-icon-128.png',
		];
	}

	/**
	 * Register and use the admin brand stylesheet via the WebAssetManager.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function ensureAssets(): void
	{
		$app = Factory::getApplication();

		if (!$app instanceof CMSWebApplicationInterface)
		{
			return;
		}

		$wa = $app->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_wendfold');
		$wa->useStyle('com_wendfold.admin');
	}

	/**
	 * Alt text for the mark when it is not decorative.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function markAlt(): string
	{
		return Text::_('COM_WENDFOLD_BRAND_MARK_ALT');
	}

	/**
	 * Decorative inline mark SVG for Atum toolbar titles (follows currentColor).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function decorativeMarkHtml(): string
	{
		$svg = self::loadMarkSvg();

		if ($svg === '')
		{
			return '';
		}

		// Decorative beside visible "Wendfold" text: drop accessible name.
		$svg = preg_replace('/\saria-label="[^"]*"/', '', $svg) ?? $svg;
		$svg = preg_replace('/\srole="[^"]*"/', '', $svg) ?? $svg;
		$svg = preg_replace('/<title>.*?<\/title>/', '', $svg) ?? $svg;

		return '<span class="wendfold-mark" aria-hidden="true">' . $svg . '</span>';
	}

	/**
	 * Replace the Font Awesome glyph in a toolbar title with the inline mark.
	 *
	 * @param   string  $titleHtml  HTML from joomla.toolbar.title
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function injectMarkIntoToolbarTitle(string $titleHtml): string
	{
		$mark = self::decorativeMarkHtml();

		if ($mark === '' || $titleHtml === '')
		{
			return $titleHtml;
		}

		$replaced = preg_replace(
			'#<span class="icon-[^"]*"[^>]*>\s*</span>#',
			$mark,
			$titleHtml,
			1
		);

		return is_string($replaced) ? $replaced : $titleHtml;
	}

	/**
	 * Public URL for the white sidebar mark PNG.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function sidebarMarkUrl(): string
	{
		return Uri::root(true) . '/' . self::MARK_PNG;
	}

	/**
	 * Absolute filesystem path candidates for a shipped brand media file.
	 *
	 * @param   string  $filename  Basename under images/
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public static function mediaPathCandidates(string $filename): array
	{
		$filename = ltrim($filename, '/');
		$candidates = [];

		if (defined('JPATH_ROOT'))
		{
			$candidates[] = JPATH_ROOT . '/media/com_wendfold/images/' . $filename;
		}

		// Extension package tree (repo or installed administrator/components path).
		$candidates[] = dirname(__DIR__, 3) . '/media/images/' . $filename;

		return $candidates;
	}

	/**
	 * Load the mark SVG markup from media (or the extension package tree).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function loadMarkSvg(): string
	{
		foreach (self::mediaPathCandidates('wendfold-mark.svg') as $path)
		{
			if (is_readable($path))
			{
				$contents = file_get_contents($path);

				return is_string($contents) ? trim($contents) : '';
			}
		}

		return '';
	}
}
