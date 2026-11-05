<?php

/**
 * Wendfold - locks local patches in vendored leaflet-gpx.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The vendored leaflet-gpx file keeps its local security patches.
 *
 * @since  1.0.0
 */
final class LeafletGpxVendorPatchTest extends TestCase
{
	/**
	 * Waypoint popup HTML escapes name and description.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testWaypointPopupEscapesNameAndDescription(): void
	{
		$source = $this->vendorSource();
		$popup  = '"<b>" + this._escape_html(name) + "</b>"'
			. " + (desc.length > 0 ? '<br>' + this._escape_html(desc) : '')";

		$this->assertStringContainsString('_escape_html: function(value)', $source);
		$this->assertStringContainsString('value === null || value === undefined', $source);
		$this->assertStringContainsString($popup, $source);
		$this->assertStringNotContainsString('"<b>" + name + "</b>"', $source);
		$this->assertStringNotContainsString("'<br>' + desc", $source);
		$this->assertSame(1, substr_count($source, 'marker.bindPopup'));
		$this->assertStringNotContainsString('innerHTML', $source);

		$helperAt = strpos($source, '_escape_html: function(value)');
		$previous = $helperAt;
		$replacements = [
			".replace(/&/g, '&amp;')",
			".replace(/</g, '&lt;')",
			".replace(/>/g, '&gt;')",
			'.replace(/"/g, \'&quot;\')',
			".replace(/'/g, '&#39;')",
		];

		$this->assertNotFalse($helperAt);

		foreach ($replacements as $replacement)
		{
			$at = strpos($source, $replacement, $helperAt);

			$this->assertNotFalse($at, $replacement);
			$this->assertGreaterThan($previous, $at);

			$previous = $at;
		}
	}

	/**
	 * Duration quote replacement is global, so the incomplete-sanitization
	 * pattern cannot return on re-vendoring.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDurationStringReplacesQuotesGlobally(): void
	{
		$source = $this->vendorSource();

		$this->assertStringContainsString(".replace(/'/g, ':').replace(/\"/g, '')", $source);
		$this->assertStringNotContainsString(".replace(\"'\",':')", $source);
		$this->assertStringNotContainsString('.replace(\'"\',\'\')', $source);
	}

	/**
	 * Read the vendored leaflet-gpx script.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function vendorSource(): string
	{
		$path = dirname(__DIR__) . '/com_wendfold/media/vendor/leaflet-gpx/gpx.js';

		$this->assertFileExists($path);

		$source = file_get_contents($path);

		if ($source === false)
		{
			$this->fail('Could not read vendored leaflet-gpx.');
		}

		return $source;
	}
}
