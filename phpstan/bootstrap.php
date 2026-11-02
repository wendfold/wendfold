<?php

/**
 * PHPStan bootstrap: loads a stock Joomla 6.2 tree so Joomla classes are typed.
 *
 * Set JOOMLA_ROOT to an unpacked Joomla 6.2.0 Full Package. CI downloads it and
 * checks its SHA-256 (see .github/workflows/ci.yml). phpstan-baseline.neon was
 * generated against that tree, so running without it is refused rather than
 * producing results that do not match the baseline.
 *
 * Joomla is not booted. Only its autoloaders are registered, plus the
 * namespaces of core components, plugins and modules read from their manifests.
 *
 * @copyright  Copyright (C) 2026 Adan Lozano
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

$joomlaRoot = getenv('JOOMLA_ROOT');

if ($joomlaRoot === false || $joomlaRoot === '' || !is_file($joomlaRoot . '/includes/defines.php'))
{
	fwrite(STDERR, "PHPStan needs JOOMLA_ROOT set to an unpacked Joomla 6.2.0 Full Package.\n");
	exit(1);
}

$joomlaRoot = rtrim(realpath($joomlaRoot), '/');

\defined('_JEXEC') or \define('_JEXEC', 1);
\defined('JPATH_BASE') or \define('JPATH_BASE', $joomlaRoot);

require_once $joomlaRoot . '/includes/defines.php';

if (!class_exists('JLoader', false))
{
	require_once JPATH_LIBRARIES . '/loader.php';
}

JLoader::setup();

require JPATH_LIBRARIES . '/vendor/autoload.php';

// Core extension namespaces, as Joomla's namespace map would list them.
$manifests = array_merge(
	glob($joomlaRoot . '/administrator/components/com_*/*.xml') ?: [],
	glob($joomlaRoot . '/plugins/*/*/*.xml') ?: [],
	glob($joomlaRoot . '/administrator/modules/*/*.xml') ?: [],
	glob($joomlaRoot . '/modules/*/*.xml') ?: []
);

foreach ($manifests as $xml)
{
	$manifest = @simplexml_load_file($xml);

	if ($manifest === false || !isset($manifest->namespace))
	{
		continue;
	}

	$namespace = trim((string) $manifest->namespace);
	$dir       = \dirname($xml);
	$path      = (string) ($manifest->namespace['path'] ?? 'src');

	if (str_contains($xml, '/administrator/components/'))
	{
		JLoader::registerNamespace($namespace . '\\Administrator', $dir . '/' . $path);

		$site = str_replace('/administrator/components/', '/components/', $dir);

		if (is_dir($site . '/' . $path))
		{
			JLoader::registerNamespace($namespace . '\\Site', $site . '/' . $path);
		}

		if (is_dir($joomlaRoot . '/api/components/' . basename($dir) . '/' . $path))
		{
			JLoader::registerNamespace($namespace . '\\Api', $joomlaRoot . '/api/components/' . basename($dir) . '/' . $path);
		}
	}
	elseif (is_dir($dir . '/' . $path))
	{
		JLoader::registerNamespace($namespace, $dir . '/' . $path);
	}
}

\defined('JVERSION') or \define('JVERSION', (new \Joomla\CMS\Version())->getShortVersion());
