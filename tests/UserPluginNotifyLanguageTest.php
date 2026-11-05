<?php

/**
 * Wendfold - user plugin admin-notify mail language under CLI-like context.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\Language
{
	if (!\class_exists(Text::class, false))
	{
		/**
		 * Text stand-in that returns loaded strings or the raw key.
		 *
		 * @since  1.0.0
		 */
		class Text
		{
			// phpcs:disable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore -- mirrors Joomla Text

			/**
			 * @var    array<string, string>
			 * @since  1.0.0
			 */
			public static array $strings = [];

			/**
			 * @param   string  $string  Language key.
			 *
			 * @return  string
			 *
			 * @since   1.0.0
			 */
			public static function _($string)
			{
				return self::$strings[(string) $string] ?? (string) $string;
			}

			/**
			 * @param   string  $string  Language key.
			 * @param   mixed   ...$args Placeholder values.
			 *
			 * @return  string
			 *
			 * @since   1.0.0
			 */
			public static function sprintf($string, ...$args)
			{
				return \sprintf(self::_($string), ...$args);
			}

			// phpcs:enable Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
		}
	}
}

namespace Joomla\CMS
{
	if (!\class_exists(Factory::class, false))
	{
		/**
		 * Factory stand-in for AdminNotifyMail language loading.
		 *
		 * @since  1.0.0
		 */
		class Factory
		{
			/**
			 * @var    object|null
			 * @since  1.0.0
			 */
			public static $application;

			/**
			 * @return  object
			 *
			 * @since   1.0.0
			 */
			public static function getApplication()
			{
				return self::$application;
			}
		}
	}
}

namespace Wendfold\Tests
{
	use Joomla\CMS\Factory;
	use Joomla\CMS\Language\Text;
	use PHPUnit\Framework\Attributes\PreserveGlobalState;
	use PHPUnit\Framework\Attributes\RunInSeparateProcess;
	use PHPUnit\Framework\TestCase;
	use Wendfold\Plugin\User\Wendfold\Helper\AdminNotifyMail;

	require_once dirname(__DIR__) . '/plg_user_wendfold/src/Helper/AdminNotifyMail.php';

	if (!\defined('JPATH_ADMINISTRATOR'))
	{
		\define('JPATH_ADMINISTRATOR', '/administrator');
	}

	if (!\defined('JPATH_PLUGINS'))
	{
		\define('JPATH_PLUGINS', '/plugins');
	}

	/**
	 * Admin-notify mail must load plugin language outside a web boot.
	 *
	 * @since  1.0.0
	 */
	final class UserPluginNotifyLanguageTest extends TestCase
	{
		/**
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		protected function setUp(): void
		{
			parent::setUp();

			if ((new \ReflectionClass(Text::class))->hasProperty('strings'))
			{
				Text::$strings = [];
			}

			UserPluginNotifyLanguageDouble::$loads = [];
			UserPluginNotifyLanguageDouble::$siteLanguage = 'en-GB';
			UserPluginNotifyLanguageDouble::$failUntilPath = null;
			UserPluginNotifyLanguageDouble::$failUntilLang = null;
			Factory::$application = new UserPluginNotifyAppDouble;
		}

		/**
		 * Building the notification outside a web app returns translated text.
		 *
		 * Runs in a separate process so this file's Text stub (with a mutable
		 * string table) is not replaced by other test doubles.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		#[RunInSeparateProcess]
		#[PreserveGlobalState(false)]
		public function testBuildAdminNotifyMailReturnsTranslatedTextOutsideWeb(): void
		{
			[$subject, $body] = AdminNotifyMail::build(
				'Chapter Site',
				42,
				'Ada Lovelace',
				'ada',
				7
			);

			$this->assertStringNotContainsString('PLG_USER_WENDFOLD_MAIL_SUBJECT', $subject);
			$this->assertStringNotContainsString('PLG_', $subject);
			$this->assertStringContainsString('Chapter Site', $subject);
			$this->assertStringContainsString('New member profile created', $subject);

			$this->assertStringNotContainsString('PLG_USER_WENDFOLD_MAIL_BODY', $body);
			$this->assertStringNotContainsString('PLG_', $body);
			$this->assertStringContainsString('42', $body);
			$this->assertStringContainsString('Ada Lovelace', $body);
			$this->assertStringContainsString('ada', $body);
			$this->assertStringContainsString('7', $body);

			$this->assertNotEmpty(UserPluginNotifyLanguageDouble::$loads);
			$this->assertSame('plg_user_wendfold', UserPluginNotifyLanguageDouble::$loads[0][0]);
			$this->assertSame(JPATH_ADMINISTRATOR, UserPluginNotifyLanguageDouble::$loads[0][1]);
		}

		/**
		 * Site default language tag is tried when the first admin load misses.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		#[RunInSeparateProcess]
		#[PreserveGlobalState(false)]
		public function testLoadMailLanguageFallsBackToSiteDefaultTag(): void
		{
			UserPluginNotifyLanguageDouble::$siteLanguage = 'de-DE';
			UserPluginNotifyLanguageDouble::$failUntilPath = JPATH_ADMINISTRATOR;
			UserPluginNotifyLanguageDouble::$failUntilLang = 'de-DE';

			AdminNotifyMail::loadLanguage();

			$paths = \array_column(UserPluginNotifyLanguageDouble::$loads, 1);
			$langs = \array_column(UserPluginNotifyLanguageDouble::$loads, 2);

			$this->assertContains(JPATH_ADMINISTRATOR, $paths);
			$this->assertContains('de-DE', $langs);
			$this->assertArrayHasKey('PLG_USER_WENDFOLD_MAIL_SUBJECT', Text::$strings);
		}

		/**
		 * notifyAdmin builds mail through AdminNotifyMail after language load.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testNotifyAdminUsesAdminNotifyMailHelper(): void
		{
			$source = (string) file_get_contents(
				dirname(__DIR__) . '/plg_user_wendfold/src/Extension/Wendfold.php'
			);

			$this->assertStringContainsString('AdminNotifyMail::build(', $source);
			$this->assertStringContainsString(
				'use Wendfold\\Plugin\\User\\Wendfold\\Helper\\AdminNotifyMail;',
				$source
			);
		}
	}

	/**
	 * Application double for CLI-like language loading.
	 *
	 * @since  1.0.0
	 */
	final class UserPluginNotifyAppDouble
	{
		/**
		 * @param   string  $key      Config key.
		 * @param   mixed   $default  Default.
		 *
		 * @return  mixed
		 *
		 * @since   1.0.0
		 */
		public function get($key, $default = null)
		{
			if ($key === 'language')
			{
				return UserPluginNotifyLanguageDouble::$siteLanguage;
			}

			if ($key === 'sitename')
			{
				return 'Chapter Site';
			}

			return $default;
		}

		/**
		 * @return  UserPluginNotifyLanguageDouble
		 *
		 * @since   1.0.0
		 */
		public function getLanguage(): UserPluginNotifyLanguageDouble
		{
			return new UserPluginNotifyLanguageDouble;
		}
	}

	/**
	 * Language double that loads the real plugin ini into Text::$strings.
	 *
	 * @since  1.0.0
	 */
	final class UserPluginNotifyLanguageDouble
	{
		/**
		 * @var    list<array{0: string, 1: string, 2: string|null}>
		 * @since  1.0.0
		 */
		public static array $loads = [];

		/**
		 * @var    string
		 * @since  1.0.0
		 */
		public static string $siteLanguage = 'en-GB';

		/**
		 * When set, load() returns false until this base path is requested.
		 *
		 * @var    string|null
		 * @since  1.0.0
		 */
		public static ?string $failUntilPath = null;

		/**
		 * When set with failUntilPath, also require this language tag.
		 *
		 * @var    string|null
		 * @since  1.0.0
		 */
		public static ?string $failUntilLang = null;

		/**
		 * @param   string       $extension  Extension name.
		 * @param   string       $basePath   Base path.
		 * @param   string|null  $lang       Language tag.
		 * @param   boolean      $reload     Unused.
		 * @param   boolean      $default    Unused.
		 *
		 * @return  boolean
		 *
		 * @since   1.0.0
		 */
		public function load($extension, $basePath = JPATH_ADMINISTRATOR, $lang = null, $reload = false, $default = true): bool
		{
			unset($reload, $default);

			self::$loads[] = [(string) $extension, (string) $basePath, $lang !== null ? (string) $lang : null];

			if (self::$failUntilPath !== null)
			{
				$pathOk = (string) $basePath === self::$failUntilPath;
				$langOk = self::$failUntilLang === null
					|| ($lang !== null && (string) $lang === self::$failUntilLang);

				if (!$pathOk || !$langOk)
				{
					return false;
				}

				self::$failUntilPath = null;
				self::$failUntilLang = null;
			}

			$ini = dirname(__DIR__) . '/plg_user_wendfold/language/en-GB/plg_user_wendfold.ini';
			$parsed = \parse_ini_file($ini);

			if ($parsed === false)
			{
				return false;
			}

			foreach ($parsed as $key => $value)
			{
				Text::$strings[(string) $key] = (string) $value;
			}

			return true;
		}
	}
}
