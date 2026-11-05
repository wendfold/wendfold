<?php

/**
 * Wendfold - Sync screen actions reach the controller.
 *
 * The administrator Sync screen posts a task and fields. Each task is a
 * public SyncController method, each posted field is a name that method
 * reads, and the method runs only after a POST with a CSRF token.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Locks the Sync screen forms to SyncController, including Run Lapsed Cleanup
 * and the flagged-member Keep and Remove from Roster actions.
 *
 * @since  1.0.0
 */
final class SyncScreenActionsTest extends TestCase
{
	private const TEMPLATE = 'com_wendfold/admin/tmpl/sync/default.php';

	private const CONTROLLER = 'com_wendfold/admin/src/Controller/SyncController.php';

	private const POST_GUARD = 'com_wendfold/admin/src/Helper/PostGuard.php';

	/**
	 * @return array{
	 *     forms: array<string, array{method: string, fields: list<string>, token: bool}>,
	 *     methods: list<string>,
	 *     bodies: array<string, string>
	 * }
	 */
	private static function screen(): array
	{
		static $screen = null;

		if ($screen !== null)
		{
			return $screen;
		}

		$template = self::read(self::TEMPLATE);
		$controller = self::read(self::CONTROLLER);
		$forms = self::forms($template);
		$bodies = [];

		foreach (array_keys($forms) as $task)
		{
			$bodies[$task] = self::functionBody($controller, $task);
		}

		$screen = [
			'forms' => $forms,
			'methods' => self::publicMethods($controller),
			'bodies' => $bodies,
		];

		return $screen;
	}

	/**
	 * Every Sync form task names a public SyncController method.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEverySyncFormTaskMapsToAPublicControllerMethod(): void
	{
		$screen = self::screen();

		self::assertSame(
			['upload', 'runLapsedCleanup', 'keepMember', 'removeFromRoster'],
			array_keys($screen['forms'])
		);

		foreach (array_keys($screen['forms']) as $task)
		{
			self::assertContains($task, $screen['methods'], $task);
		}

		self::assertNotContains('lapsedCleanup', $screen['methods']);
		self::assertNotContains('lapsedCleanup', array_keys($screen['forms']));
		self::assertSame(
			['upload', 'sendReminders', 'runLapsedCleanup', 'removeFromRoster', 'keepMember'],
			$screen['methods']
		);
	}

	/**
	 * Each form posts the field names its controller method reads.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPostedFieldNamesMatchTheControllerReads(): void
	{
		$screen = self::screen();

		foreach ($screen['forms'] as $task => $form)
		{
			$missing = array_values(array_diff($form['fields'], self::inputNames($screen['bodies'][$task])));
			self::assertSame([], $missing, $task);
		}

		self::assertSame(['csv_file', 'dry_run', 'preview'], $screen['forms']['upload']['fields']);
		self::assertSame([], $screen['forms']['runLapsedCleanup']['fields']);
		self::assertSame(['profile_id'], $screen['forms']['keepMember']['fields']);
		self::assertSame(['profile_id'], $screen['forms']['removeFromRoster']['fields']);

		self::assertEqualsCanonicalizing(
			['csv_file', 'sync_csv', 'dry_run', 'preview'],
			self::inputNames($screen['bodies']['upload'])
		);
		self::assertSame(['grace_days'], self::inputNames($screen['bodies']['runLapsedCleanup']));
		self::assertSame(['profile_id'], self::inputNames($screen['bodies']['keepMember']));
		self::assertSame(['profile_id'], self::inputNames($screen['bodies']['removeFromRoster']));
	}

	/**
	 * Each Sync action starts with the POST guard and keeps its permission check.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEachSyncActionIsPostOnlyAndKeepsItsPermissionCheck(): void
	{
		$screen = self::screen();
		$permissions = [
			'upload' => ['core.create', 'core.edit'],
			'runLapsedCleanup' => ['core.edit.state'],
			'keepMember' => ['core.edit'],
			'removeFromRoster' => ['core.delete'],
		];

		foreach ($screen['forms'] as $task => $form)
		{
			self::assertSame('post', $form['method'], $task);
			self::assertTrue($form['token'], $task);

			$body = $screen['bodies'][$task];
			self::assertStringStartsWith('$this->requirePostToken();', self::code($body), $task);
			self::assertStringContainsString('$this->checkAccess();', $body, $task);
			self::assertSame($permissions[$task], self::coreActions($body), $task);

			$tokenAt = strpos($body, '$this->requirePostToken();');
			$accessAt = strpos($body, '$this->checkAccess();');
			self::assertIsInt($tokenAt, $task);
			self::assertIsInt($accessAt, $task);
			self::assertLessThan($accessAt, $tokenAt, $task);
		}
	}

	/**
	 * The POST guard checks the request method before the CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testThePostGuardChecksTheVerbBeforeTheCsrfToken(): void
	{
		$guard = self::functionBody(self::read(self::POST_GUARD), 'passes');
		$verbAt = strpos($guard, "!== 'POST'");
		$tokenAt = strpos($guard, "Session::checkToken('post')");

		self::assertIsInt($verbAt);
		self::assertIsInt($tokenAt);
		self::assertLessThan($tokenAt, $verbAt);

		$caller = self::functionBody(self::read(self::CONTROLLER), 'requirePostToken');
		self::assertStringContainsString('PostGuard::passes()', $caller);
		self::assertStringNotContainsString('Session::checkToken', $caller);
	}

	/**
	 * The Sync confirm prompts stay on escaped data-confirm attributes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSyncConfirmPromptsStayOnEscapedDataAttributes(): void
	{
		$template = self::read(self::TEMPLATE);

		self::assertStringContainsString(
			'use Wendfold\\Component\\Wendfold\\Site\\Helper\\OutputEscape;',
			$template
		);
		self::assertStringContainsString(
			'data-confirm="<?php echo OutputEscape::html(Text::_(\'COM_WENDFOLD_SYNC_LAPSED_CONFIRM\')); ?>"',
			$template
		);
		self::assertStringContainsString(
			'data-confirm="<?php echo OutputEscape::html(Text::_(\'COM_WENDFOLD_SYNC_REMOVE_CONFIRM\')); ?>"',
			$template
		);
		self::assertDoesNotMatchRegularExpression('/\bonclick\s*=/i', $template);
	}

	/**
	 * SyncController reads posted fields through getInput(), not $app->input.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSyncControllerReadsPostedFieldsThroughGetInput(): void
	{
		$controller = self::read(self::CONTROLLER);

		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $controller);
		self::assertStringContainsString("\$input = \$app->getInput();", $controller);
		self::assertStringContainsString("\$app->getInput()->getInt('profile_id', 0)", $controller);
	}

	/**
	 * The form parser reads a Sync form whose tag spans several lines.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTheFormParserReadsAMultilineSyncForm(): void
	{
		$template = <<<'PHP'
<form action="<?php echo Route::_('index.php?option=com_wendfold&task=sync.keepMember'); ?>"
      method="post">
    <input type="hidden" name="profile_id" value="4" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
PHP;
		$forms = self::forms($template);

		self::assertSame(['keepMember'], array_keys($forms));
		self::assertSame('post', $forms['keepMember']['method']);
		self::assertSame(['profile_id'], $forms['keepMember']['fields']);
		self::assertTrue($forms['keepMember']['token']);
	}

	/**
	 * Reads a repository file.
	 *
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function read(string $relative): string
	{
		$contents = file_get_contents(dirname(__DIR__) . '/' . $relative);

		if ($contents === false)
		{
			self::fail('Unreadable ' . $relative);
		}

		return $contents;
	}

	/**
	 * @param   string  $template  Template source.
	 *
	 * @return  array<string, array{method: string, fields: list<string>, token: bool}>
	 */
	private static function forms(string $template): array
	{
		$forms = [];
		$length = strlen($template);
		$offset = 0;

		while (($start = stripos($template, '<form', $offset)) !== false)
		{
			$after = $start + 5;

			if ($after < $length && ctype_alnum($template[$after]))
			{
				$offset = $after;
				continue;
			}

			$tagEnd = self::htmlTagEnd($template, $start);
			$attrs = substr($template, $start + 5, $tagEnd - ($start + 5));
			$close = stripos($template, '</form>', $tagEnd);

			if ($close === false)
			{
				self::fail('Unclosed sync form');
			}

			$body = substr($template, $tagEnd + 1, $close - ($tagEnd + 1));

			if (!preg_match('/task=sync\.([A-Za-z_][A-Za-z0-9_]*)/', $attrs, $task))
			{
				self::fail('Sync form is missing a sync task');
			}

			if (!preg_match('/\bmethod\s*=\s*(["\'])([^"\']*)\1/i', $attrs, $method))
			{
				self::fail('Sync form is missing a request method');
			}

			$fieldMatches = [];
			$matchedFields = preg_match_all('/\bname\s*=\s*"([^"]*)"/', $body, $fieldMatches);

			if ($matchedFields === false)
			{
				self::fail('Could not read posted field names');
			}

			$fields = [];

			foreach ($fieldMatches[1] as $field)
			{
				if (!in_array($field, $fields, true))
				{
					$fields[] = $field;
				}
			}

			$taskName = $task[1];

			if (isset($forms[$taskName]))
			{
				self::fail('Duplicate sync task ' . $taskName);
			}

			$forms[$taskName] = [
				'method' => strtolower($method[2]),
				'fields' => $fields,
				'token' => str_contains($body, 'form.token'),
			];
			$offset = $close + 7;
		}

		return $forms;
	}

	/**
	 * Finds the end of the HTML tag that starts at an offset.
	 *
	 * @param   string   $source  Template source.
	 * @param   integer  $start   Offset of the tag.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function htmlTagEnd(string $source, int $start): int
	{
		$length = strlen($source);
		$state = 'code';

		for ($i = $start; $i < $length; $i++)
		{
			$char = $source[$i];
			$next = $i + 1 < $length ? $source[$i + 1] : '';

			if ($state === 'code')
			{
				if ($char === '<' && $next === '?')
				{
					$state = 'php';
					$i++;
					continue;
				}

				if ($char === '"' || $char === "'")
				{
					$state = $char;
					continue;
				}

				if ($char === '>')
				{
					return $i;
				}

				continue;
			}

			if ($state === 'php')
			{
				if ($char === '?' && $next === '>')
				{
					$state = 'code';
					$i++;
				}

				continue;
			}

			if ($char === '\\')
			{
				$i++;
				continue;
			}

			if ($char === $state)
			{
				$state = 'code';
			}
		}

		self::fail('Unclosed form tag');
	}

	/**
	 * @param   string  $source  Controller source.
	 *
	 * @return  list<string>
	 */
	private static function publicMethods(string $source): array
	{
		$matched = preg_match_all('/public function ([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);

		if ($matched === false)
		{
			self::fail('Could not read controller methods');
		}

		return $matches[1];
	}

	/**
	 * @param   string  $body  Form body.
	 *
	 * @return  list<string>
	 */
	private static function inputNames(string $body): array
	{
		$matched = preg_match_all(
			'/->get(?:Int|String|Cmd|Word|Bool|Float|Uint|Raw|Html|Array|Base64)?\(\s*\'([A-Za-z_][A-Za-z0-9_]*)\'/',
			$body,
			$matches
		);

		if ($matched === false)
		{
			self::fail('Could not read controller input names');
		}

		$names = [];

		foreach ($matches[1] as $name)
		{
			if (!in_array($name, $names, true))
			{
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * @param   string  $body  Method body.
	 *
	 * @return  list<string>
	 */
	private static function coreActions(string $body): array
	{
		$matched = preg_match_all("/requireCoreAction\\(\\s*'([^']+)'\\s*\\)/", $body, $matches);

		if ($matched === false)
		{
			self::fail('Could not read permission checks');
		}

		return $matches[1];
	}

	/**
	 * Strips comments from PHP code.
	 *
	 * @param   string  $body  PHP code.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function code(string $body): string
	{
		$withoutBlock = preg_replace('#/\*.*?\*/#s', '', $body);
		$withoutLine = preg_replace('#[ \t]*//[^\n]*#', '', $withoutBlock ?? '');

		return trim($withoutLine ?? '');
	}

	/**
	 * Returns the body of a named function.
	 *
	 * @param   string  $source  PHP source.
	 * @param   string  $name    Function name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function functionBody(string $source, string $name): string
	{
		$pattern = '/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*(?::\s*[^{\n]+)?\s*\{/';

		if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE))
		{
			self::fail('No function ' . $name);
		}

		$open = $match[0][1] + strlen($match[0][0]) - 1;
		$length = strlen($source);
		$depth = 0;
		$state = 'code';

		for ($i = $open; $i < $length; $i++)
		{
			$char = $source[$i];
			$next = $i + 1 < $length ? $source[$i + 1] : '';

			if ($state === 'code')
			{
				if ($char === "'")
				{
					$state = 'single';
					continue;
				}

				if ($char === '"')
				{
					$state = 'double';
					continue;
				}

				if ($char === '/' && $next === '/')
				{
					$state = 'line';
					$i++;
					continue;
				}

				if ($char === '/' && $next === '*')
				{
					$state = 'block';
					$i++;
					continue;
				}

				if ($char === '{')
				{
					$depth++;
					continue;
				}

				if ($char === '}')
				{
					$depth--;

					if ($depth === 0)
					{
						return substr($source, $open + 1, $i - $open - 1);
					}
				}

				continue;
			}

			if ($state === 'single' || $state === 'double')
			{
				if ($char === '\\')
				{
					$i++;
					continue;
				}

				if (($state === 'single' && $char === "'") || ($state === 'double' && $char === '"'))
				{
					$state = 'code';
				}

				continue;
			}

			if ($state === 'line')
			{
				if ($char === "\n")
				{
					$state = 'code';
				}

				continue;
			}

			if ($char === '*' && $next === '/')
			{
				$state = 'code';
				$i++;
			}
		}

		self::fail('Unclosed function ' . $name);
	}
}
