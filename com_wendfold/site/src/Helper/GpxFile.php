<?php

/**
 * Wendfold - GPX file checks.
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
 * Checks a stored or uploaded GPX path before it is kept or sent.
 *
 * @since  1.0.0
 */
abstract class GpxFile
{
	/**
	 * Whether the file name ends in .gpx, ignoring case.
	 *
	 * @param   string  $name  Original upload name or stored relative path.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function hasGpxExtension(string $name): bool
	{
		$base = basename(str_replace('\\', '/', $name));

		return $base !== '' && preg_match('/\.gpx$/i', $base) === 1;
	}

	/**
	 * Whether the file is XML with a gpx root element.
	 *
	 * External entities stay unloaded: LIBXML_NONET is set and LIBXML_NOENT is not.
	 *
	 * @param   string  $path  Absolute path to the uploaded file.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isGpxDocument(string $path): bool
	{
		if ($path === '' || !is_file($path))
		{
			return false;
		}

		$xml = file_get_contents($path);

		if ($xml === false || $xml === '')
		{
			return false;
		}

		$errors = libxml_use_internal_errors(true);
		libxml_set_external_entity_loader(
			static function () {
				return null;
			}
		);

		$document = new \DOMDocument;
		$loaded = $document->loadXML($xml, LIBXML_NONET);

		libxml_clear_errors();
		libxml_use_internal_errors($errors);
		libxml_set_external_entity_loader(null);

		if ($loaded !== true || $document->documentElement === null)
		{
			return false;
		}

		return $document->documentElement->localName === 'gpx';
	}

	/**
	 * Absolute path of a regular .gpx file stored under media/com_wendfold/gpx.
	 *
	 * Anything else, including a directory or a path outside that directory, is refused.
	 *
	 * @param   string  $relative  Value stored on the event.
	 * @param   string  $root      Filesystem root that contains the media directory.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function resolveStoredPath(string $relative, string $root): ?string
	{
		$relative = ltrim(str_replace('\\', '/', $relative), '/');

		if (str_contains($relative, "\0")
			|| str_contains($root, "\0")
			|| !str_starts_with($relative, 'media/com_wendfold/gpx/')
			|| str_contains($relative, '..'))
		{
			return null;
		}

		if (!self::hasGpxExtension($relative))
		{
			return null;
		}

		$base = realpath($root . '/media/com_wendfold/gpx');
		$path = realpath($root . '/' . $relative);

		if ($base === false || $path === false || !is_file($path) || !self::hasGpxExtension($path))
		{
			return null;
		}

		if (!str_starts_with($path, $base . DIRECTORY_SEPARATOR))
		{
			return null;
		}

		return $path;
	}
}
