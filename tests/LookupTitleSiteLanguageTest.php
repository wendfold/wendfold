<?php

/**
 * Wendfold - LookupTitle resolves admin-seeded keys on the site.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Joomla\CMS\Language
{
	if (!\class_exists(LanguageHelper::class, false))
	{
		/**
		 * LanguageHelper::parseIniFile stand-in for LookupTitle tests.
		 *
		 * @since  1.0.0
		 */
		class LanguageHelper
		{
			/**
			 * @param   string   $fileName  Ini path.
			 * @param   boolean  $debug     Unused.
			 *
			 * @return  array<string, string>
			 */
			public static function parseIniFile($fileName, $debug = false): array
			{
				return \Wendfold\Tests\LookupTitleLanguageHelperDouble::$files[(string) $fileName] ?? [];
			}
		}
	}
}

namespace Wendfold\Tests
{
	use Joomla\CMS\Factory;
	use PHPUnit\Framework\TestCase;
	use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;

	require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';

	if (!\defined('JPATH_ADMINISTRATOR'))
	{
		\define('JPATH_ADMINISTRATOR', '/administrator');
	}

	/**
	 * Site officer role titles go through LookupTitle without clobbering site strings.
	 *
	 * @since  1.0.0
	 */
	final class LookupTitleSiteLanguageTest extends TestCase
	{
		/**
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		protected function setUp(): void
		{
			parent::setUp();

			$ref = new \ReflectionClass(LookupTitle::class);
			$prop = $ref->getProperty('adminStrings');
			$prop->setValue(null, []);

			LookupTitleLanguageDouble::$tag = 'en-GB';
			LookupTitleLanguageDouble::$strings = [
				'COM_WENDFOLD_EVENTS_TITLE'        => 'Events',
				'COM_WENDFOLD_OFFICERS_NO_OFFICERS' => 'No officers currently listed. Check back soon!',
			];
			LookupTitleLanguageHelperDouble::$files = [
				JPATH_ADMINISTRATOR . '/language/en-GB/com_wendfold.ini' => [
					'COM_WENDFOLD_EVENTS_TITLE'          => 'Wendfold - Events',
					'COM_WENDFOLD_OFFICERS_NO_OFFICERS'  => 'No officers found. Click New to add the first officer appointment.',
					'COM_WENDFOLD_OFFICER_ROLE_DIRECTOR' => 'Director',
					'COM_WENDFOLD_MEMBERSHIP_FULL'       => 'Full',
				],
			];

			Factory::$application = new LookupTitleAppDouble;
		}

		/**
		 * Officer role language keys are present in the site en-GB ini.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testSiteIniDefinesOfficerRoleDirector(): void
		{
			$siteIni = (string) file_get_contents(
				dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
			);

			self::assertMatchesRegularExpression(
				'/^COM_WENDFOLD_OFFICER_ROLE_DIRECTOR="Director"$/m',
				$siteIni
			);
		}

		/**
		 * LookupTitle never reloads the whole admin ini into the shared Language.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testLookupTitleDoesNotReloadAdministratorLanguage(): void
		{
			$source = (string) file_get_contents(
				dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php'
			);

			self::assertStringContainsString('LanguageHelper::parseIniFile', $source);
			self::assertStringContainsString('hasKey', $source);
			self::assertDoesNotMatchRegularExpression('/->load\s*\(/', $source);
			self::assertTrue(LookupTitle::isLanguageKey('COM_WENDFOLD_OFFICER_ROLE_DIRECTOR'));
		}

		/**
		 * A key present in both inis keeps the site wording after LookupTitle::display.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testSiteWordingPreservedForSharedKeys(): void
		{
			self::assertSame('Events', LookupTitle::display('COM_WENDFOLD_EVENTS_TITLE'));
			self::assertSame(
				'Events',
				Factory::getApplication()->getLanguage()->_('COM_WENDFOLD_EVENTS_TITLE')
			);
			self::assertSame(
				'No officers currently listed. Check back soon!',
				LookupTitle::display('COM_WENDFOLD_OFFICERS_NO_OFFICERS')
			);
			self::assertNotSame(
				'Wendfold - Events',
				LookupTitle::display('COM_WENDFOLD_EVENTS_TITLE')
			);
		}

		/**
		 * A role key present only in the admin ini still translates on site.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testAdminOnlyRoleKeyStillTranslates(): void
		{
			self::assertSame('Director', LookupTitle::display('COM_WENDFOLD_OFFICER_ROLE_DIRECTOR'));
			self::assertFalse(
				Factory::getApplication()->getLanguage()->hasKey('COM_WENDFOLD_OFFICER_ROLE_DIRECTOR')
			);
		}

		/**
		 * Missing current-language admin ini falls back to en-GB for admin-only keys.
		 *
		 * @return  void
		 *
		 * @since   1.0.0
		 */
		public function testMissingCurrentLanguageAdminIniFallsBackToEnGb(): void
		{
			LookupTitleLanguageDouble::$tag = 'fr-FR';
			LookupTitleLanguageDouble::$strings = [
				'COM_WENDFOLD_EVENTS_TITLE' => 'Événements',
			];

			self::assertSame('Full', LookupTitle::display('COM_WENDFOLD_MEMBERSHIP_FULL'));
			self::assertSame('Événements', LookupTitle::display('COM_WENDFOLD_EVENTS_TITLE'));
			self::assertNotSame('Wendfold - Events', LookupTitle::display('COM_WENDFOLD_EVENTS_TITLE'));
		}
	}

	/**
	 * Shared site language strings for LookupTitle tests.
	 *
	 * @since  1.0.0
	 */
	final class LookupTitleLanguageDouble
	{
		/** @var string */
		public static string $tag = 'en-GB';

		/** @var array<string, string> */
		public static array $strings = [];
	}

	/**
	 * Parsed admin ini files for LookupTitle tests.
	 *
	 * @since  1.0.0
	 */
	final class LookupTitleLanguageHelperDouble
	{
		/** @var array<string, array<string, string>> */
		public static array $files = [];
	}

	/**
	 * Application stand-in for LookupTitle.
	 *
	 * @since  1.0.0
	 */
	final class LookupTitleAppDouble
	{
		/**
		 * @return  LookupTitleLanguageObjectDouble
		 */
		public function getLanguage(): LookupTitleLanguageObjectDouble
		{
			return new LookupTitleLanguageObjectDouble;
		}
	}

	/**
	 * Language object stand-in with hasKey / getTag / _.
	 *
	 * @since  1.0.0
	 */
	final class LookupTitleLanguageObjectDouble
	{
		/**
		 * @param   string  $string  Language key.
		 *
		 * @return  boolean
		 */
		public function hasKey($string): bool
		{
			return isset(LookupTitleLanguageDouble::$strings[strtoupper((string) $string)]);
		}

		/**
		 * @return  string
		 */
		public function getTag(): string
		{
			return LookupTitleLanguageDouble::$tag;
		}

		/**
		 * Translate one key from the site string map.
		 *
		 * @param   string   $string               Language key.
		 * @param   boolean  $jsSafe               Unused.
		 * @param   boolean  $interpretBackSlashes Unused.
		 *
		 * @return  string
		 */
		public function _($string, $jsSafe = false, $interpretBackSlashes = true): string // phpcs:ignore Generic.Files.LineLength.TooLong, Joomla.NamingConventions.ValidFunctionName.MethodUnderscore
		{
			$key = strtoupper((string) $string);

			return LookupTitleLanguageDouble::$strings[$key] ?? (string) $string;
		}
	}
}
