<?php

/**
 * Wendfold - GPX file checks.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\GpxFile;

/**
 * Locks the GPX extension, content, and stored-path rules.
 */
final class GpxFileTest extends TestCase
{
    public function testNonGpxNamesAreRefused(): void
    {
        self::assertFalse(GpxFile::hasGpxExtension(''));
        self::assertFalse(GpxFile::hasGpxExtension('route.txt'));
        self::assertFalse(GpxFile::hasGpxExtension('route.gpx.txt'));
        self::assertFalse(GpxFile::hasGpxExtension('route.gpx.php'));
        self::assertFalse(GpxFile::hasGpxExtension('route.GPX.exe'));
        self::assertTrue(GpxFile::hasGpxExtension('route.gpx'));
        self::assertTrue(GpxFile::hasGpxExtension('route.GPX'));
        self::assertTrue(GpxFile::hasGpxExtension('C:\\fakepath\\Route.Gpx'));
    }

    public function testBadXmlIsRefused(): void
    {
        $dir = sys_get_temp_dir() . '/wendfold-gpx-' . bin2hex(random_bytes(4));
        mkdir($dir);

        try {
            $bad = $dir . '/bad.xml';
            $html = $dir . '/page.gpx';
            $broken = $dir . '/broken.gpx';
            $good = $dir . '/good.gpx';
            $namespaced = $dir . '/ns.gpx';

            file_put_contents($bad, '<trk></trk>');
            file_put_contents($html, '<html><body>no</body></html>');
            file_put_contents($broken, '<gpx>');
            file_put_contents($good, '<?xml version="1.0"?><gpx version="1.1"></gpx>');
            file_put_contents(
                $namespaced,
                '<?xml version="1.0"?><gpx xmlns="http://www.topografix.com/GPX/1/1" version="1.1"></gpx>'
            );

            self::assertFalse(GpxFile::isGpxDocument($bad));
            self::assertFalse(GpxFile::isGpxDocument($html));
            self::assertFalse(GpxFile::isGpxDocument($broken));
            self::assertFalse(GpxFile::isGpxDocument($dir . '/missing.gpx'));
            self::assertTrue(GpxFile::isGpxDocument($good));
            self::assertTrue(GpxFile::isGpxDocument($namespaced));
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }

            rmdir($dir);
        }
    }

    public function testStoredPathMustBeARegularGpxFile(): void
    {
        $root = sys_get_temp_dir() . '/wendfold-root-' . bin2hex(random_bytes(4));
        $gpxDir = $root . '/media/com_wendfold/gpx';
        mkdir($gpxDir, 0777, true);
        mkdir($gpxDir . '/not-a-file.gpx');
        file_put_contents($gpxDir . '/route.GPX', '<gpx></gpx>');
        file_put_contents($gpxDir . '/notes.txt', 'no');
        file_put_contents($root . '/outside.gpx', '<gpx></gpx>');

        try {
            self::assertSame(
                realpath($gpxDir . '/route.GPX'),
                GpxFile::resolveStoredPath('media/com_wendfold/gpx/route.GPX', $root)
            );
            self::assertNull(GpxFile::resolveStoredPath('media/com_wendfold/gpx/notes.txt', $root));
            self::assertNull(GpxFile::resolveStoredPath('media/com_wendfold/gpx/not-a-file.gpx', $root));
            self::assertNull(GpxFile::resolveStoredPath('media/com_wendfold/gpx/../outside.gpx', $root));
            self::assertNull(GpxFile::resolveStoredPath('outside.gpx', $root));
            self::assertNull(GpxFile::resolveStoredPath('media/com_wendfold/gpx/missing.gpx', $root));
        } finally {
            @unlink($gpxDir . '/route.GPX');
            @unlink($gpxDir . '/notes.txt');
            @unlink($root . '/outside.gpx');
            @rmdir($gpxDir . '/not-a-file.gpx');
            @rmdir($gpxDir);
            @rmdir($root . '/media/com_wendfold');
            @rmdir($root . '/media');
            @rmdir($root);
        }
    }

    public function testNullBytePathIsRefused(): void
    {
        $root = sys_get_temp_dir() . '/wendfold-root-' . bin2hex(random_bytes(4));
        $gpxDir = $root . '/media/com_wendfold/gpx';
        mkdir($gpxDir, 0777, true);
        file_put_contents($gpxDir . '/route.gpx', '<gpx></gpx>');

        try {
            self::assertNull(GpxFile::resolveStoredPath("media/com_wendfold/gpx/route.gpx\0", $root));
            self::assertNull(GpxFile::resolveStoredPath("media/com_wendfold/gpx/\0route.gpx", $root));
            self::assertNull(GpxFile::resolveStoredPath('media/com_wendfold/gpx/route.gpx', $root . "\0"));
        } finally {
            @unlink($gpxDir . '/route.gpx');
            @rmdir($gpxDir);
            @rmdir($root . '/media/com_wendfold');
            @rmdir($root . '/media');
            @rmdir($root);
        }
    }
}
