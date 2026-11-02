<?php

/**
 * Wendfold - local check-in QR codes.
 *
 * Renders a check-in URL as a 200 by 200 pixel image on this server.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds a QR image from the bundled Kazuhiko Arase generator.
 *
 * @since  1.0.0
 */
abstract class QrCodeHelper
{
	/**
	 * Pixel width and height of the check-in image.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	public const IMAGE_SIZE = 200;

	/**
	 * Quiet-zone width in modules.
	 *
	 * @var    int
	 * @since  1.0.0
	 */
	private const QUIET_ZONE = 4;

	/**
	 * Data URI for an img src and a download link.
	 *
	 * @param   string  $text  Payload encoded in the symbol. For check-in this is the absolute check-in URL.
	 * @param   int     $size  Image edge length in pixels.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function dataUri(string $text, int $size = self::IMAGE_SIZE): string
	{
		if (self::canWritePng())
		{
			return 'data:image/png;base64,' . base64_encode(self::png($text, $size));
		}

		return 'data:image/svg+xml;base64,' . base64_encode(self::svg($text, $size));
	}

	/**
	 * File extension that matches dataUri() for this runtime.
	 *
	 * @return  string  png when GD is available, otherwise svg
	 *
	 * @since   1.0.0
	 */
	public static function fileExtension(): string
	{
		return self::canWritePng() ? 'png' : 'svg';
	}

	/**
	 * PNG bytes, exactly $size by $size.
	 *
	 * @param   string  $text  Payload encoded in the symbol.
	 * @param   int     $size  Image edge length in pixels.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function png(string $text, int $size = self::IMAGE_SIZE): string
	{
		if (!self::canWritePng())
		{
			throw new \RuntimeException('GD is required to encode a PNG QR code.');
		}

		$size = max(1, $size);
		$qr = self::encode($text);
		$modules = $qr->getModuleCount();
		$dimension = $modules + (self::QUIET_ZONE * 2);
		$cell = $size / $dimension;

		$image = imagecreatetruecolor($size, $size);

		if ($image === false)
		{
			throw new \RuntimeException('QR code image allocation failed.');
		}

		$white = imagecolorallocate($image, 255, 255, 255);
		$black = imagecolorallocate($image, 0, 0, 0);
		imagefilledrectangle($image, 0, 0, $size - 1, $size - 1, $white);

		for ($row = 0; $row < $modules; $row++)
		{
			for ($col = 0; $col < $modules; $col++)
			{
				if (!$qr->isDark($row, $col))
				{
					continue;
				}

				$x1 = (int) floor(($col + self::QUIET_ZONE) * $cell);
				$y1 = (int) floor(($row + self::QUIET_ZONE) * $cell);
				$x2 = min($size - 1, (int) ceil(($col + self::QUIET_ZONE + 1) * $cell) - 1);
				$y2 = min($size - 1, (int) ceil(($row + self::QUIET_ZONE + 1) * $cell) - 1);

				imagefilledrectangle($image, $x1, $y1, max($x1, $x2), max($y1, $y2), $black);
			}
		}

		ob_start();
		$written = imagepng($image);
		$png = ob_get_clean();
		unset($image);

		if ($written !== true || !is_string($png) || $png === '')
		{
			throw new \RuntimeException('QR code PNG encoding failed.');
		}

		return $png;
	}

	/**
	 * SVG document, displayed at $size by $size.
	 *
	 * @param   string  $text  Payload encoded in the symbol.
	 * @param   int     $size  Image edge length in pixels.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function svg(string $text, int $size = self::IMAGE_SIZE): string
	{
		$size = max(1, $size);
		$qr = self::encode($text);
		$modules = $qr->getModuleCount();
		$border = self::QUIET_ZONE;
		$dimension = $modules + ($border * 2);

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
			. '" viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges">'
			. '<rect width="100%" height="100%" fill="#ffffff"/>';

		for ($row = 0; $row < $modules; $row++)
		{
			for ($col = 0; $col < $modules; $col++)
			{
				if (!$qr->isDark($row, $col))
				{
					continue;
				}

				$x = $col + $border;
				$y = $row + $border;
				$svg .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1" fill="#000000"/>';
			}
		}

		return $svg . '</svg>';
	}

	/**
	 * Encode text at medium error correction, which matches a printed check-in code.
	 *
	 * The symbol contents are the given string. Medium correction changes the
	 * pattern, not the payload.
	 *
	 * @param   string  $text  Payload.
	 *
	 * @return  \QRCode
	 *
	 * @since   1.0.0
	 */
	private static function encode(string $text): \QRCode
	{
		self::loadLibrary();

		return \QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
	}

	/**
	 * Load the vendored single-file generator once.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private static function loadLibrary(): void
	{
		if (class_exists('QRCode', false))
		{
			return;
		}

		$path = dirname(__DIR__, 2) . '/vendor/qrcode-generator/qrcode.php';

		if (!is_file($path))
		{
			throw new \RuntimeException('QR code library is missing.');
		}

		require_once $path;
	}

	/**
	 * GD can write a PNG on this runtime.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function canWritePng(): bool
	{
		return function_exists('imagecreatetruecolor') && function_exists('imagepng');
	}
}
