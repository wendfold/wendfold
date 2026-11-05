<?php

/**
 * Wendfold - event type join SQL stays unambiguous.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Queries that join events to event types must qualify shared column names.
 *
 * @since  1.0.0
 */
final class EventTypeJoinSqlTest extends TestCase
{
	/**
	 * Columns present on both events and event types.
	 *
	 * @var string[]
	 */
	private const AMBIGUOUS = [
		'id',
		'title',
		'published',
		'created',
		'created_by',
		'modified',
		'modified_by',
	];

	/**
	 * A joined query does not select or filter an unqualified shared column.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testJoinedEventQueriesQualifySharedColumns(): void
	{
		$failures = [];

		foreach ($this->phpFiles() as $path)
		{
			$text = (string) file_get_contents($path);
			$starts = [];

			if (preg_match_all('/createQuery\(\)/', $text, $found, PREG_OFFSET_CAPTURE) !== false)
			{
				foreach ($found[0] as $hit)
				{
					$starts[] = $hit[1];
				}
			}

			foreach ($starts as $index => $start)
			{
				$end = $starts[$index + 1] ?? \strlen($text);
				$window = substr($text, $start, $end - $start);
				$closed = preg_match('/->(loadObject|loadObjectList|loadResult|loadColumn|execute)\(/', $window, $match, PREG_OFFSET_CAPTURE);

				if ($closed === 1)
				{
					$window = substr($window, 0, $match[0][1] + \strlen($match[0][0]));
				}

				if (!str_contains($window, 'wendfold_event_types') || !str_contains($window, 'wendfold_events') || !str_contains($window, 'join'))
				{
					continue;
				}

				$line = substr_count(substr($text, 0, $start), "\n") + 1;
				$problems = $this->problems($window);

				if ($problems !== [])
				{
					$failures[] = $path . ':' . $line . ' ' . implode(', ', $problems);
				}
			}
		}

		$this->assertSame([], $failures, implode("\n", $failures));
	}

	/**
	 * An import code matches a published membership type only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMembershipImportCodeRequiresPublishedType(): void
	{
		$src = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/src/Model/SyncModel.php');
		$start = strpos($src, 'function membershipTypeIdForImport');
		$this->assertNotFalse($start);
		$body = substr($src, (int) $start, 900);
		$this->assertSame(2, substr_count($body, "quoteName('published') . ' = 1'"));
	}

	/**
	 * @return  string[]
	 */
	private function phpFiles(): array
	{
		$root = dirname(__DIR__);
		$paths = [];

		foreach (['com_wendfold', 'plg_task_wendfold', 'plg_user_wendfold', 'plg_privacy_wendfold'] as $dir)
		{
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir));

			foreach ($iterator as $file)
			{
				if ($file->isFile() && $file->getExtension() === 'php')
				{
					$paths[] = $file->getPathname();
				}
			}
		}

		sort($paths);

		return $paths;
	}

	/**
	 * @param   string  $window  One query.
	 *
	 * @return  string[]
	 */
	private function problems(string $window): array
	{
		$problems = [];

		if (preg_match_all("/quoteName\\(\\s*'([A-Za-z0-9_]+)'\\s*\\)/", $window, $names) === false)
		{
			return ['could not scan quoteName'];
		}

		foreach ($names[1] as $column)
		{
			if (\in_array($column, self::AMBIGUOUS, true))
			{
				$problems[] = 'unqualified ' . $column;
			}
		}

		if (preg_match_all("/(?<![\\w.])'(id|title|published|created|created_by|modified|modified_by)'/", $window, $bare) !== false)
		{
			foreach ($bare[1] as $column)
			{
				$problems[] = 'bare ' . $column;
			}
		}

		if (preg_match('/->group\\((.*?)\\)(?:->|;)/s', $window, $group) === 1
			&& preg_match("/quoteName\\(\\s*'[^']+'\\s*,\\s*'/", $group[1]) === 1)
		{
			$problems[] = 'GROUP BY uses a select alias';
		}

		return $problems;
	}
}
