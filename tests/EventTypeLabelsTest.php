<?php

/**
 * Wendfold - Event type labels stay on EventTypeHelper.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeHelper;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeBehavior.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/LookupTitle.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeHelper.php';

/**
 * Overnight and chapter-defined types never fall back to a private 1-5 map.
 *
 * @since  1.0.0
 */
final class EventTypeLabelsTest extends TestCase
{
	/**
	 * Badge classes stay wired through the helper.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBadgeClassesMatchBehavior(): void
	{
		$this->assertSame(
			EventTypeBehavior::adminBadgeClass(EventTypeBehavior::Overnight->value),
			EventTypeHelper::adminBadgeClass(EventTypeBehavior::Overnight->value)
		);
		$this->assertSame(
			EventTypeBehavior::siteBadgeClass(EventTypeBehavior::Ride->value),
			EventTypeHelper::siteBadgeClass(EventTypeBehavior::Ride->value)
		);
	}

	/**
	 * Custom titles pass through; empty titles use the general behavior key.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLabelUsesLookupTitleAndGeneralFallback(): void
	{
		if (!class_exists(\Joomla\CMS\Language\Text::class, false))
		{
			eval(<<<'PHP'
namespace Joomla\CMS\Language;
class Text
{
	public static function _($string)
	{
		return (string) $string;
	}
}
PHP);
		}

		$this->assertSame('Poker Run', EventTypeHelper::label('Poker Run'));
		$this->assertSame(EventTypeHelper::FALLBACK_LABEL_KEY, EventTypeHelper::label(''));
		$this->assertSame(EventTypeHelper::FALLBACK_LABEL_KEY, EventTypeHelper::label(null));
		$this->assertSame(
			'COM_WENDFOLD_EVENT_TYPE_OVERNIGHT',
			EventTypeHelper::label('COM_WENDFOLD_EVENT_TYPE_OVERNIGHT')
		);
		$this->assertNotSame('', EventTypeHelper::label('COM_WENDFOLD_EVENT_TYPE_OVERNIGHT'));
	}

	/**
	 * Every language key EventTypeHelper can return must exist in admin and site.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHelperLanguageKeysExistInAdminAndSiteIni(): void
	{
		$admin = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);
		$site = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);

		$this->assertNotSame([], EventTypeHelper::languageKeys());

		foreach (EventTypeHelper::languageKeys() as $key)
		{
			$this->assertMatchesRegularExpression(
				'/^' . preg_quote($key, '/') . '="/m',
				$admin,
				$key . ' missing from admin ini'
			);
			$this->assertMatchesRegularExpression(
				'/^' . preg_quote($key, '/') . '="/m',
				$site,
				$key . ' missing from site ini'
			);
		}
	}

	/**
	 * Report models and templates call EventTypeHelper for type wording.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReportsAndListsUseEventTypeHelper(): void
	{
		$paths = [
			'/com_wendfold/admin/src/Model/AttendancetrendsModel.php',
			'/com_wendfold/admin/src/Model/ActivityreportModel.php',
			'/com_wendfold/admin/tmpl/attendancetrends/default.php',
			'/com_wendfold/admin/tmpl/activityreport/default.php',
			'/com_wendfold/admin/tmpl/events/default.php',
			'/com_wendfold/site/tmpl/events/default.php',
			'/com_wendfold/site/tmpl/myrsvps/default.php',
			'/com_wendfold/site/tmpl/volunteerboard/default.php',
			'/com_wendfold/site/tmpl/trip/default.php',
		];

		foreach ($paths as $relative)
		{
			$source = (string) file_get_contents(dirname(__DIR__) . $relative);
			$this->assertStringContainsString('EventTypeHelper::', $source, $relative);
			$this->assertStringNotContainsString("TYPE_LABELS", $source, $relative);
		}
	}

	/**
	 * Dead numeric 1-6 event-type maps must stay gone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoHardCodedNumericEventTypeMapsRemain(): void
	{
		$hits = [];
		$root = dirname(__DIR__) . '/com_wendfold';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			$path = $file->getPathname();

			if (!str_ends_with($path, '.php'))
			{
				continue;
			}

			if (str_contains($path, '/vendor/'))
			{
				continue;
			}

			$source = (string) file_get_contents($path);

			if (preg_match(
				"/\\$[a-zA-Z]*[Tt]ype(?:Labels|Info|s)?\\s*=\\s*\\[\\s*1\\s*=>\\s*\\[\\s*'label'\\s*=>\\s*Text::_\\('COM_WENDFOLD_EVENT_TYPE_RIDE'\\)/s",
				$source
			) === 1)
			{
				$hits[] = $path;
			}
		}

		$this->assertSame([], $hits);
	}

	/**
	 * English event-type literals stay out of PHP and templates.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoEnglishEventTypeLiteralsOutsideLanguageFiles(): void
	{
		$hits = [];
		$root = dirname(__DIR__) . '/com_wendfold';
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			$path = $file->getPathname();

			if (!str_ends_with($path, '.php'))
			{
				continue;
			}

			if (str_contains($path, '/vendor/'))
			{
				continue;
			}

			$source = (string) file_get_contents($path);

			if (preg_match("/'(Ride|Meeting|Rally|Social|Overnight)'/", $source) === 1)
			{
				$hits[] = $path;
			}
		}

		$this->assertSame([], $hits);
	}
}
