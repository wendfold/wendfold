<?php

/**
 * Wendfold - scan used language keys against en-GB ini files.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

/**
 * Finds package language keys used in PHP, XML and layouts, and compares them
 * to the en-GB ini files shipped with the component, plugins and package.
 *
 * @since  1.0.0
 */
final class LanguageKeyScanner
{
	/**
	 * AdminController / ListController plural suffixes.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private const ADMIN_PLURAL_SUFFIXES = [
		'_N_ITEMS_PUBLISHED',
		'_N_ITEMS_UNPUBLISHED',
		'_N_ITEMS_ARCHIVED',
		'_N_ITEMS_TRASHED',
		'_N_ITEMS_DELETED',
		'_N_ITEMS_CHECKED_IN',
		'_N_ITEMS_FAILED_PUBLISHING',
		'_N_ITEMS_FAILED_UNPUBLISHING',
		'_N_ITEMS_FAILED_ARCHIVING',
		'_N_ITEMS_FAILED_TRASHING',
		'_N_ITEMS_FAILED_DELETING',
		'_NO_ITEM_SELECTED',
	];

	/**
	 * FormController message suffixes.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private const FORM_SUFFIXES = [
		'_SAVE_SUCCESS',
		'_SUBMIT_SAVE_SUCCESS',
	];

	/**
	 * Extension roots scanned for used keys, relative to the repository root.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private const SCAN_ROOTS = [
		'com_wendfold',
		'plg_user_wendfold',
		'plg_task_wendfold',
		'plg_privacy_wendfold',
		'pkg_wendfold',
	];

	/**
	 * en-GB ini files relative to the repository root.
	 *
	 * @var    array<string, string>
	 * @since  1.0.0
	 */
	private const INI_FILES = [
		'admin'           => 'com_wendfold/admin/language/en-GB/com_wendfold.ini',
		'admin_sys'       => 'com_wendfold/admin/language/en-GB/com_wendfold.sys.ini',
		'site'            => 'com_wendfold/language/en-GB/com_wendfold.ini',
		'plg_user'        => 'plg_user_wendfold/language/en-GB/plg_user_wendfold.ini',
		'plg_user_sys'    => 'plg_user_wendfold/language/en-GB/plg_user_wendfold.sys.ini',
		'plg_task'        => 'plg_task_wendfold/language/en-GB/plg_task_wendfold.ini',
		'plg_task_sys'    => 'plg_task_wendfold/language/en-GB/plg_task_wendfold.sys.ini',
		'plg_privacy'     => 'plg_privacy_wendfold/language/en-GB/plg_privacy_wendfold.ini',
		'plg_privacy_sys' => 'plg_privacy_wendfold/language/en-GB/plg_privacy_wendfold.sys.ini',
		'pkg_sys'         => 'pkg_wendfold/language/en-GB/pkg_wendfold.sys.ini',
	];

	/**
	 * Repository root.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $root;

	/**
	 * @param   string  $root  Repository root path.
	 *
	 * @since   1.0.0
	 */
	public function __construct(string $root)
	{
		$this->root = rtrim($root, '/');
	}

	/**
	 * Keys that are used but missing from every en-GB ini that should define them.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function missingKeys(): array
	{
		$defined = $this->definedKeys();
		$missing = [];

		foreach ($this->usedKeys() as $key)
		{
			if (!isset($defined[$key]))
			{
				$missing[] = $key;
			}
		}

		sort($missing);

		return $missing;
	}

	/**
	 * Duplicate KEY= definitions within a single en-GB ini file.
	 *
	 * @return  list<string>  Entries like "path: KEY (lines a, b)".
	 *
	 * @since   1.0.0
	 */
	public function duplicateKeys(): array
	{
		$duplicates = [];

		foreach (self::INI_FILES as $relative)
		{
			$path = $this->root . '/' . $relative;

			if (!is_file($path))
			{
				continue;
			}

			$linesByKey = [];
			$lineNumber = 0;

			foreach (preg_split("/\R/", (string) file_get_contents($path)) ?: [] as $line)
			{
				$lineNumber++;
				$line = trim($line);

				if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#'))
				{
					continue;
				}

				if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=/', $line, $match) !== 1)
				{
					continue;
				}

				$linesByKey[$match[1]][] = $lineNumber;
			}

			foreach ($linesByKey as $key => $lines)
			{
				if (\count($lines) < 2)
				{
					continue;
				}

				$duplicates[] = $relative . ': ' . $key . ' (lines ' . implode(', ', $lines) . ')';
			}
		}

		sort($duplicates);

		return $duplicates;
	}

	/**
	 * Package keys referenced from source, including plural _1 variants and
	 * controller text_prefix concatenations.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function usedKeys(): array
	{
		$keys = [];

		foreach ($this->scanSourceFiles() as $path)
		{
			$contents = (string) file_get_contents($path);
			$this->collectLiteralKeys($contents, $keys);
			$this->collectXmlAttributeKeys($contents, $keys);
		}

		$this->collectControllerImpliedKeys($keys);

		$withPlurals = [];

		foreach (array_keys($keys) as $key)
		{
			$withPlurals[$key] = true;

			if ($this->isPluralCandidate($key))
			{
				$withPlurals[$key . '_1'] = true;
			}
		}

		$result = array_keys($withPlurals);
		sort($result);

		return $result;
	}

	/**
	 * Keys defined in any package en-GB ini file.
	 *
	 * @return  array<string, true>
	 *
	 * @since   1.0.0
	 */
	public function definedKeys(): array
	{
		$defined = [];

		foreach (self::INI_FILES as $relative)
		{
			$path = $this->root . '/' . $relative;

			if (!is_file($path))
			{
				continue;
			}

			foreach ($this->parseIniKeys((string) file_get_contents($path)) as $key)
			{
				$defined[$key] = true;
			}
		}

		return $defined;
	}

	/**
	 * Absolute paths of PHP, XML and layout files under the extension roots.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function scanSourceFiles(): array
	{
		$files = [];

		foreach (self::SCAN_ROOTS as $relative)
		{
			$dir = $this->root . '/' . $relative;

			if (!is_dir($dir))
			{
				continue;
			}

			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
			);

			/** @var SplFileInfo $file */
			foreach ($iterator as $file)
			{
				if (!$file->isFile())
				{
					continue;
				}

				$ext = strtolower($file->getExtension());

				if (!in_array($ext, ['php', 'xml'], true))
				{
					continue;
				}

				$path = $file->getPathname();

				if (str_contains($path, '/vendor/') || str_contains($path, '/node_modules/'))
				{
					continue;
				}

				$files[] = $path;
			}
		}

		sort($files);

		return $files;
	}

	/**
	 * Collect Text::_ / Text::sprintf / Text::plural / JText keys.
	 *
	 * @param   string               $contents  File contents.
	 * @param   array<string, true>  $keys      Accumulator.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function collectLiteralKeys(string $contents, array &$keys): void
	{
		$pattern = '/\b(?:Text|JText)::(?:_|sprintf|plural)\(\s*(["\'])([A-Z][A-Z0-9_]*)\1/';

		if (preg_match_all($pattern, $contents, $matches) === false)
		{
			return;
		}

		foreach ($matches[2] as $key)
		{
			if ($this->isPackageKey($key))
			{
				$keys[$key] = true;
			}
		}
	}

	/**
	 * Collect package keys from common XML language attributes.
	 *
	 * @param   string               $contents  File contents.
	 * @param   array<string, true>  $keys      Accumulator.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function collectXmlAttributeKeys(string $contents, array &$keys): void
	{
		$pattern = '/\b(?:label|description|message|hint|addonBefore|addonAfter|button|'
			. 'option|menu|title|alt|toolbar|confirm)\s*=\s*["\']([A-Z][A-Z0-9_]*)["\']/i';

		if (preg_match_all($pattern, $contents, $matches) === false)
		{
			return;
		}

		foreach ($matches[1] as $key)
		{
			if ($this->isPackageKey($key))
			{
				$keys[$key] = true;
			}
		}
	}

	/**
	 * Resolve AdminController / FormController text_prefix concatenations.
	 *
	 * @param   array<string, true>  $keys  Accumulator.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function collectControllerImpliedKeys(array &$keys): void
	{
		$controllerDir = $this->root . '/com_wendfold/admin/src/Controller';

		if (!is_dir($controllerDir))
		{
			return;
		}

		foreach (glob($controllerDir . '/*Controller.php') ?: [] as $path)
		{
			$contents = (string) file_get_contents($path);
			$prefix = $this->controllerTextPrefix($contents);
			$isAdminList = str_contains($contents, 'extends AdminController')
				|| str_contains($contents, 'extends ListController');
			$isForm = str_contains($contents, 'extends FormController');

			if ($isAdminList)
			{
				foreach (self::ADMIN_PLURAL_SUFFIXES as $suffix)
				{
					$keys[$prefix . $suffix] = true;
				}
			}

			if ($isForm)
			{
				foreach (self::FORM_SUFFIXES as $suffix)
				{
					$keys[$prefix . $suffix] = true;
				}
			}
		}
	}

	/**
	 * text_prefix property value, or COM_WENDFOLD when unset.
	 *
	 * @param   string  $contents  Controller source.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function controllerTextPrefix(string $contents): string
	{
		if (preg_match('/\$text_prefix\s*=\s*[\'"]([A-Z0-9_]+)[\'"]/', $contents, $match) === 1)
		{
			return $match[1];
		}

		return 'COM_WENDFOLD';
	}

	/**
	 * Whether a key is owned by this package.
	 *
	 * @param   string  $key  Language key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isPackageKey(string $key): bool
	{
		return str_starts_with($key, 'COM_WENDFOLD_')
			|| str_starts_with($key, 'PLG_USER_WENDFOLD_')
			|| str_starts_with($key, 'PLG_TASK_WENDFOLD_')
			|| str_starts_with($key, 'PLG_PRIVACY_WENDFOLD_')
			|| str_starts_with($key, 'PKG_WENDFOLD_');
	}

	/**
	 * Whether Text::plural may look up a _1 singular variant for this key.
	 *
	 * Joomla plural keys use an `_N_` segment (for example `_N_ITEMS_`).
	 *
	 * @param   string  $key  Language key.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isPluralCandidate(string $key): bool
	{
		return str_contains($key, '_N_') && !str_ends_with($key, '_1');
	}

	/**
	 * Parse KEY= lines from an ini file, ignoring comments.
	 *
	 * @param   string  $contents  Ini file contents.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function parseIniKeys(string $contents): array
	{
		$keys = [];

		foreach (preg_split("/\R/", $contents) ?: [] as $line)
		{
			$line = trim($line);

			if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#'))
			{
				continue;
			}

			if (preg_match('/^([A-Z][A-Z0-9_]*)\s*=/', $line, $match) === 1)
			{
				$keys[] = $match[1];
			}
		}

		return $keys;
	}
}
