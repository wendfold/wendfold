<?php

/**
 * Wendfold - Departure/Return template labels use language keys and tooltips.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards Departure/Return wording, tooltips, and hard-coded KSU/KSD labels.
 *
 * @since  1.0.0
 */
final class KsuKsdTemplateLanguageTest extends TestCase
{
	/**
	 * Site and admin en-GB files define Departure/Return labels and tooltip text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventKsuAndKsdKeysUseDepartureAndReturnValues(): void
	{
		$site = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini'
		);
		$admin = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini'
		);

		foreach (['COM_WENDFOLD_EVENT_KSU="Departure"', 'COM_WENDFOLD_EVENT_KSD="Return"'] as $line)
		{
			self::assertStringContainsString($line, $site, $line . ' missing from site ini');
			self::assertStringContainsString($line, $admin, $line . ' missing from admin ini');
		}

		foreach (['COM_WENDFOLD_EVENT_KSU_DESC="Kick Stands Up"', 'COM_WENDFOLD_EVENT_KSD_DESC="Kick Stands Down"'] as $line)
		{
			self::assertStringContainsString($line, $site, $line . ' missing from site ini');
			self::assertStringContainsString($line, $admin, $line . ' missing from admin ini');
		}
	}

	/**
	 * Templates that render Departure/Return also render the accessible tooltip text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTemplatesRenderAccessibleDepartureReturnTooltips(): void
	{
		$paths = [
			'com_wendfold/site/tmpl/checkin/default.php',
			'com_wendfold/site/tmpl/event/default.php',
			'com_wendfold/site/tmpl/events/default.php',
			'com_wendfold/site/tmpl/trip/default.php',
			'com_wendfold/site/tmpl/rcattendance/default.php',
			'com_wendfold/admin/tmpl/attendance/default.php',
		];

		foreach ($paths as $relative)
		{
			$source = (string) file_get_contents(dirname(__DIR__) . '/' . $relative);

			self::assertStringContainsString('OutputEscape::termWithTooltip', $source, $relative);
			self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSU_DESC')", $source, $relative);
			self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSU')", $source, $relative);
		}

		$event = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/event/default.php'
		);
		self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSD')", $event);
		self::assertStringContainsString("Text::_('COM_WENDFOLD_EVENT_KSD_DESC')", $event);
	}

	/**
	 * Site, admin, and plugin templates must not hard-code "KSU "/"KSD " labels.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTemplatesHaveNoHardCodedKsuOrKsdLiterals(): void
	{
		$hits = [];

		foreach ($this->templateFiles() as $path)
		{
			$source = $this->withoutComments((string) file_get_contents($path));
			$relative = substr($path, strlen(dirname(__DIR__)) + 1);

			if (preg_match_all("/['\"]KS[UD] /", $source, $matches, PREG_OFFSET_CAPTURE) > 0)
			{
				foreach ($matches[0] as $match)
				{
					$line = substr_count($source, "\n", 0, $match[1]) + 1;
					$hits[] = $relative . ':' . $line . ' ' . $match[0];
				}
			}

			if (preg_match_all('/(?<![\w\'"])KS[UD] (?!is the same)/', $source, $matches, PREG_OFFSET_CAPTURE) > 0)
			{
				foreach ($matches[0] as $match)
				{
					$line = substr_count($source, "\n", 0, $match[1]) + 1;
					$hits[] = $relative . ':' . $line . ' bare ' . trim($match[0]);
				}
			}
		}

		self::assertSame([], array_values(array_unique($hits)));
	}

	/**
	 * Helper output includes title and visually-hidden accessible text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTermWithTooltipHelperIsAccessible(): void
	{
		$html = \Wendfold\Component\Wendfold\Site\Helper\OutputEscape::termWithTooltipAndValue(
			'Departure',
			'Kick Stands Up',
			'9:00 AM'
		);

		self::assertStringContainsString('title="Kick Stands Up"', $html);
		self::assertStringContainsString('class="visually-hidden"', $html);
		self::assertStringContainsString('Kick Stands Up', $html);
		self::assertStringContainsString('Departure', $html);
		self::assertStringContainsString('9:00 AM', $html);
		self::assertStringNotContainsString('<script', $html);
	}

	/**
	 * An ampersand in label, description, or value is escaped exactly once.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTermWithTooltipEscapesAmpersandOnce(): void
	{
		$html = \Wendfold\Component\Wendfold\Site\Helper\OutputEscape::termWithTooltipAndValue(
			'A & B',
			'Kick Stands Up & Down',
			'9:00 AM & later'
		);

		self::assertStringContainsString('title="Kick Stands Up &amp; Down"', $html);
		self::assertStringContainsString('>A &amp; B<span class="visually-hidden">', $html);
		self::assertStringContainsString('(Kick Stands Up &amp; Down)</span>', $html);
		self::assertStringContainsString('9:00 AM &amp; later', $html);
		self::assertStringNotContainsString('&amp;amp;', $html);
	}

	/**
	 * @return  list<string>
	 */
	private function templateFiles(): array
	{
		$files = [];
		$roots = [
			dirname(__DIR__) . '/com_wendfold',
			dirname(__DIR__) . '/plg_user_wendfold',
			dirname(__DIR__) . '/plg_task_wendfold',
			dirname(__DIR__) . '/plg_privacy_wendfold',
		];

		foreach ($roots as $root)
		{
			if (!is_dir($root))
			{
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file)
			{
				$path = $file->getPathname();

				if (!str_contains($path, '/tmpl/') || !str_ends_with($path, '.php'))
				{
					continue;
				}

				if (str_contains($path, '/vendor/'))
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
	 * Strip block, line, and HTML comments so developer notes are ignored.
	 *
	 * @param   string  $source  Template source.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function withoutComments(string $source): string
	{
		$withoutBlock = preg_replace('#/\*.*?\*/#s', '', $source) ?? $source;
		$withoutLine = preg_replace('#^\s*//.*$#m', '', $withoutBlock) ?? $withoutBlock;

		return preg_replace('#<!--.*?-->#s', '', $withoutLine) ?? $withoutLine;
	}
}
