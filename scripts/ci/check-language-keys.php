<?php

/**
 * Wendfold - fail when a used en-GB language key is missing.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

require __DIR__ . '/LanguageKeyScanner.php';

$root = dirname(__DIR__, 2);
$scanner = new LanguageKeyScanner($root);
$missing = $scanner->missingKeys();
$duplicates = $scanner->duplicateKeys();
$failed = false;

if ($missing !== [])
{
	$failed = true;
	fwrite(STDERR, "Missing en-GB language keys (" . count($missing) . "):\n");

	foreach ($missing as $key)
	{
		fwrite(STDERR, "  {$key}\n");
	}
}

if ($duplicates !== [])
{
	$failed = true;
	fwrite(STDERR, "Duplicate en-GB language keys (" . count($duplicates) . "):\n");

	foreach ($duplicates as $entry)
	{
		fwrite(STDERR, "  {$entry}\n");
	}
}

if ($failed)
{
	exit(1);
}

$used = count($scanner->usedKeys());
echo "Language keys OK: {$used} used package keys are defined in en-GB ini files with no duplicates.\n";
exit(0);
