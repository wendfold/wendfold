<?php

/**
 * Wendfold - deprecated API cleanup locks for issues #27 and #151.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Locks the PHP 8.4/8.5 and Joomla 6 deprecation fixes for issues #27 and #151,
 * including a package-wide source scan that fails if Factory::getDbo(),
 * Factory::getMailer(), Application $input property access, getQuery(true),
 * or a plugin provider passing a dispatcher to the constructor returns.
 *
 * @since  1.0.0
 */
final class DeprecatedApiCleanupTest extends TestCase
{
	/**
	 * Extension trees scanned for reintroduced deprecated APIs.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	private const SCAN_ROOTS = [
		'com_wendfold',
		'pkg_wendfold',
		'plg_user_wendfold',
		'plg_task_wendfold',
		'plg_privacy_wendfold',
	];

	/**
	 * SyncController reads request data through getInput(), not $app->input.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSyncControllerUsesGetInput(): void
	{
		$source = self::read('com_wendfold/admin/src/Controller/SyncController.php');

		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $source);
		self::assertSame(4, preg_match_all('/->getInput\(\)/', $source));
	}

	/**
	 * Email blast send uses MailerFactoryInterface, not Factory::getMailer().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmailblastUsesMailerFactoryAndGetInput(): void
	{
		$controller = self::read('com_wendfold/admin/src/Controller/EmailblastController.php');
		$view = self::read('com_wendfold/admin/src/View/Emailblast/HtmlView.php');

		self::assertStringNotContainsString('Factory::getMailer()', $controller);
		self::assertStringContainsString('MailerFactoryInterface::class', $controller);
		self::assertStringContainsString('->createMailer()', $controller);
		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $controller);
		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $view);
		self::assertStringContainsString('->getInput()', $controller);
		self::assertStringContainsString('->getInput()', $view);
		self::assertDoesNotMatchRegularExpression('/->getConfig\(\)/', $controller);
	}

	/**
	 * Admin motorcycle edit reads profile_id through getInput().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAdminMotorcycleUsesGetInput(): void
	{
		$controller = self::read('com_wendfold/admin/src/Controller/MotorcycleController.php');
		$model = self::read('com_wendfold/admin/src/Model/MotorcycleModel.php');

		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $controller);
		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $model);
		self::assertStringContainsString('->getInput()', $controller);
		self::assertStringContainsString('->getInput()', $model);
	}

	/**
	 * Site events list filters use getInput().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSiteEventsUsesGetInput(): void
	{
		$model = self::read('com_wendfold/site/src/Model/EventsModel.php');
		$template = self::read('com_wendfold/site/tmpl/events/default.php');

		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $model);
		self::assertDoesNotMatchRegularExpression('/\$app\s*->\s*input\b/', $template);
		self::assertStringContainsString('->getInput()', $model);
		self::assertStringContainsString('->getInput()', $template);
	}

	/**
	 * OfficerController marks the MVC factory parameter as nullable.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOfficerControllerFactoryParameterIsExplicitlyNullable(): void
	{
		$source = self::read('com_wendfold/admin/src/Controller/OfficerController.php');

		self::assertMatchesRegularExpression(
			'/function\s+__construct\s*\([^)]*\?MVCFactoryInterface\s+\$factory\s*=\s*null/s',
			$source
		);
		self::assertDoesNotMatchRegularExpression(
			'/(?<!\?)MVCFactoryInterface\s+\$factory\s*=\s*null/',
			$source
		);
	}

	/**
	 * Profile photo checks do not call deprecated finfo_close().
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testProfilePhotoChecksDoNotCallFinfoClose(): void
	{
		$profileedit = self::read('com_wendfold/site/src/Controller/ProfileeditController.php');
		$member = self::read('com_wendfold/admin/src/Controller/MemberController.php');

		self::assertStringNotContainsString('finfo_close', $profileedit);
		self::assertStringNotContainsString('finfo_close', $member);
		self::assertStringContainsString('finfo_open', $profileedit);
	}

	/**
	 * Lapsed Cleanup posts the controller method name, not the old alias.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLapsedCleanupFormPostsRunLapsedCleanupTask(): void
	{
		$template = self::read('com_wendfold/admin/tmpl/sync/default.php');

		self::assertStringContainsString('task=sync.runLapsedCleanup', $template);
		self::assertStringNotContainsString('task=sync.lapsedCleanup', $template);
		self::assertStringContainsString(
			'public function runLapsedCleanup()',
			self::read('com_wendfold/admin/src/Controller/SyncController.php')
		);
	}

	/**
	 * Sync confirm prompts use escaped data-confirm attributes, not onclick.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSyncConfirmPromptsUseEscapedDataConfirm(): void
	{
		$template = self::read('com_wendfold/admin/tmpl/sync/default.php');

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
	 * Extension PHP must not reintroduce the deprecated APIs from #27.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPackageSourcesDoNotReintroduceDeprecatedApis(): void
	{
		$hits = [];

		foreach (self::phpSources() as $relative => $source)
		{
			foreach (self::deprecatedHits($source, $relative) as $hit)
			{
				$hits[] = $relative . ': ' . $hit;
			}
		}

		self::assertSame([], $hits, "Deprecated API hits:\n" . implode("\n", $hits));
	}

	/**
	 * Installer scripts obtain the database from the container and log via WendfoldLog.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInstallerScriptsUseContainerDatabaseAndWendfoldLog(): void
	{
		$component = self::read('com_wendfold/script.php');
		$package = self::read('pkg_wendfold/script.php');

		self::assertStringContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $component);
		self::assertStringContainsString('Factory::getContainer()->get(DatabaseInterface::class)', $package);
		self::assertStringContainsString('WendfoldLog::add(', $component);
		self::assertDoesNotMatchRegularExpression('/(?<!Wendfold)Log::add\s*\(/', $component);
		self::assertStringNotContainsString('Factory::getDbo()', $component);
		self::assertStringNotContainsString('Factory::getDbo()', $package);
	}

	/**
	 * Installer scripts return a ServiceProviderInterface that registers InstallerScriptInterface.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInstallerScriptsReturnServiceProvider(): void
	{
		foreach (['com_wendfold/script.php', 'pkg_wendfold/script.php'] as $relative)
		{
			$source = self::read($relative);

			self::assertStringContainsString('implements InstallerScriptInterface', $source, $relative);
			self::assertStringContainsString('implements ServiceProviderInterface', $source, $relative);
			self::assertStringContainsString('InstallerScriptInterface::class', $source, $relative);
			self::assertMatchesRegularExpression(
				'/return\s+new\s+class(?:\s*\(\s*\))?\s+implements\s+ServiceProviderInterface/',
				$source,
				$relative . ' must return a service provider'
			);
			self::assertDoesNotMatchRegularExpression(
				'/Legacy installer files are deprecated/',
				$source
			);
		}
	}

	/**
	 * Plugin providers construct with config only (Joomla 6), not a dispatcher first argument.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPluginProvidersDoNotPassDispatcherToConstructor(): void
	{
		$providers = [
			'plg_task_wendfold/services/provider.php',
			'plg_user_wendfold/services/provider.php',
			'plg_privacy_wendfold/services/provider.php',
		];

		foreach ($providers as $relative)
		{
			$source = self::read($relative);

			self::assertDoesNotMatchRegularExpression(
				'/new\s+Wendfold\s*\(\s*\$dispatcher\b/',
				$source,
				$relative . ' must not pass $dispatcher to the plugin constructor'
			);
			self::assertMatchesRegularExpression(
				'/new\s+Wendfold\s*\(\s*\$pluginData\s*\)/',
				$source,
				$relative . ' must construct the plugin with the config array only'
			);
		}
	}

	/**
	 * Collects relative path => source for scanned extension PHP files.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	private static function phpSources(): array
	{
		$root = dirname(__DIR__);
		$sources = [];

		foreach (self::SCAN_ROOTS as $dir)
		{
			$base = $root . '/' . $dir;

			if (!is_dir($base))
			{
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file)
			{
				if (!$file->isFile() || $file->getExtension() !== 'php')
				{
					continue;
				}

				$relative = substr($file->getPathname(), strlen($root) + 1);
				$contents = file_get_contents($file->getPathname());

				if ($contents === false)
				{
					self::fail('Could not read ' . $relative);
				}

				$sources[$relative] = $contents;
			}
		}

		ksort($sources);

		return $sources;
	}

	/**
	 * Returns human-readable labels for deprecated API matches in one file.
	 *
	 * @param   string  $source    File contents.
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private static function deprecatedHits(string $source, string $relative = ''): array
	{
		$hits = [];
		$patterns = [
			'Factory::getDbo()' => '/Factory::getDbo\s*\(/',
			'Factory::getMailer()' => '/Factory::getMailer\s*\(/',
			'Factory::getUser()' => '/Factory::getUser\s*\(/',
			'Factory::getSession()' => '/Factory::getSession\s*\(/',
			'Factory::getDocument()' => '/Factory::getDocument\s*\(/',
			'Factory::getLanguage()' => '/Factory::getLanguage\s*\(/',
			'Factory::getConfig()' => '/Factory::getConfig\s*\(/',
			'finfo_close()' => '/finfo_close\s*\(/',
			// Application Input property access ($app->input). Controllers may
			// still use $this->input, which is the injected Input object.
			'Application ->input property' => '/(?<!\\$this)->\s*input\b/',
			// Database package 2.2 deprecates getQuery($new); use createQuery().
			'getQuery(true)' => '/getQuery\s*\(\s*true\s*\)/',
			// CMSPlugin::__construct(DispatcherInterface, ...) is deprecated in Joomla 6.
			'plugin constructor dispatcher arg' => '/new\s+Wendfold\s*\(\s*\$dispatcher\b/',
			// State::get() deprecates empty stored values; use StateValue::get().
			'State::get / $state->get' => '/(?:\$this->state|\$state)->get\s*\(/',
			// DatabaseDriver::setQuery($query, $offset, $limit) is deprecated.
			'setQuery 3-arg form' => '/->setQuery\s*\(\s*[^,()]+,\s*[^,()]+,\s*[^,()]+\)/',
		];

		foreach ($patterns as $label => $pattern)
		{
			if (preg_match($pattern, $source) === 1)
			{
				$hits[] = $label;
			}
		}

		// AbstractView::get('Items') and friends — Views and their templates.
		if (
			(str_contains($relative, '/View/') || str_contains($relative, '/tmpl/'))
			&& preg_match('/\$this->get\s*\(\s*[\'"]/', $source) === 1
		)
		{
			$hits[] = "AbstractView::get('...')";
		}

		return $hits;
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
			self::fail('Could not read ' . $relative);
		}

		return $contents;
	}
}
