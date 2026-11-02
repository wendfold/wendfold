<?php

/**
 * Wendfold - confirm prompts stay out of inline handlers.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;

/**
 * Locks HTML escaping for confirm prompts and the check-in welcome line,
 * and keeps PHP out of inline onclick handlers.
 */
final class InlineConfirmTemplateTest extends TestCase
{
	public function testAMemberNameStaysInsideTheConfirmAttribute(): void
	{
		$name = "x');alert(1);//";
		$prompt = 'Remove %s from the meeting roll call?';
		$attribute = OutputEscape::confirm($prompt, $name);
		$html = '<button type="submit" data-confirm="' . $attribute . '">Remove</button>';

		$button = $this->buttonFrom($html);

		self::assertSame(sprintf($prompt, $name), $button->getAttribute('data-confirm'));
		self::assertStringNotContainsString('<script', strtolower($html));
		self::assertStringNotContainsString('onclick=', strtolower($html));
	}

	public function testMarkupInANameCannotLeaveTheConfirmAttribute(): void
	{
		$name = '"><img src=x onerror=alert(1)>';
		$html = '<button type="submit" data-confirm="'
			. OutputEscape::confirm('Remove %s from the meeting roll call?', $name)
			. '">Remove</button>';

		$button = $this->buttonFrom($html);

		self::assertSame('Remove ' . $name . ' from the meeting roll call?', $button->getAttribute('data-confirm'));
		self::assertSame(0, $this->document($html)->getElementsByTagName('img')->length);
	}

	public function testAnApostropheInATranslationStaysInTheAttribute(): void
	{
		$prompt = "Don't cancel O'Brien's RSVP?');alert(1);//";
		$attribute = OutputEscape::html($prompt);

		self::assertSame(
			'Don&#039;t cancel O&#039;Brien&#039;s RSVP?&#039;);alert(1);//',
			$attribute
		);
		self::assertSame($prompt, $this->buttonFrom(
			'<button type="submit" data-confirm="' . $attribute . '">Cancel</button>'
		)->getAttribute('data-confirm'));
	}

	public function testTheCheckInWelcomeKeepsItsMarkupAndEscapesTheName(): void
	{
		$name = '<script>alert(1)</script>';
		$html = OutputEscape::sprintfHtml('Welcome, <strong>%s</strong>!', $name);
		$document = $this->document('<p>' . $html . '</p>');

		self::assertSame('Welcome, <strong>&lt;script&gt;alert(1)&lt;/script&gt;</strong>!', $html);
		self::assertSame('Welcome, ' . $name . '!', $document->getElementsByTagName('p')->item(0)->textContent);
		self::assertSame(1, $document->getElementsByTagName('strong')->length);
		self::assertSame(0, $document->getElementsByTagName('script')->length);
	}

	public function testTheCheckInFirstNameIsEscapedOnItsOwn(): void
	{
		$name = '<script>alert(1)</script> Ann';
		$first = explode(' ', $name)[0];
		$html = OutputEscape::sprintfHtml('Welcome, %s!', $first);
		$document = $this->document('<h2>' . $html . '</h2>');

		self::assertSame('<script>alert(1)</script>', $first);
		self::assertSame('Welcome, &lt;script&gt;alert(1)&lt;/script&gt;!', $html);
		self::assertSame(0, $document->getElementsByTagName('img')->length);
		self::assertSame('Welcome, ' . $first . '!', $document->getElementsByTagName('h2')->item(0)->textContent);
	}

	public function testNoOnclickAttributeContainsPhp(): void
	{
		$hits = [];

		foreach ($this->templateFiles() as $path) {
			$hits = array_merge($hits, $this->handlerHits($path, true));
		}

		self::assertSame([], $hits);
	}

	public function testNoOnclickAttributeCallsConfirm(): void
	{
		$hits = [];

		foreach ($this->templateFiles() as $path) {
			foreach ($this->handlerHits($path, false) as $hit) {
				if ($hit['name'] === 'onclick' && str_contains(strtolower($hit['value']), 'confirm(')) {
					$hits[] = $hit['where'];
				}
			}
		}

		self::assertSame([], $hits);
	}

	/**
	 * Meeting and award confirm prompts must come from language strings.
	 *
	 * @return  void
	 */
	public function testTheMeetingPromptAndAwardPromptsAreLanguageStrings(): void
	{
		$site = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/language/en-GB/com_wendfold.ini');
		$admin = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/language/en-GB/com_wendfold.ini');

		self::assertStringContainsString(
			'COM_WENDFOLD_MEETING_ATTENDANCE_REMOVE_CONFIRM="Remove %s from the meeting roll call?"',
			$site
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_ATTENDANCE_REMOVE_CONFIRM="Remove %s from roster?"',
			$admin
		);
		self::assertStringContainsString(
			'COM_WENDFOLD_MEMBERAWARDS_CONFIRM_RECALCULATE="Recalculate member awards now?"',
			$admin
		);
	}

	/**
	 * The Member Awards Recalculate toolbar button uses the escaped language confirm.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberAwardsRecalculateToolbarUsesTheLanguageConfirm(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/View/Memberawards/HtmlView.php'
		);

		self::assertStringContainsString(
			"Text::_('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_RECALCULATE')",
			$source
		);
		self::assertMatchesRegularExpression(
			"/standardButton\(\s*'recalculate'\s*\)[\s\S]*?"
			. "'data-confirm'\s*=>\s*OutputEscape::html\([\s\S]*?"
			. "Text::_\('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_RECALCULATE'\)/s",
			$source
		);
		self::assertStringContainsString(
			"data-confirm=\"<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_MEMBERAWARDS_CONFIRM_LOCK')); ?>\"",
			(string) file_get_contents(dirname(__DIR__) . '/com_wendfold/admin/tmpl/memberawards/default.php')
		);
	}

	public function testAdminRosterRemoveUsesTheLanguageConfirmHelper(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/tmpl/attendance/default.php'
		);

		self::assertStringContainsString(
			"OutputEscape::confirm(\n\t\t\tText::_('COM_WENDFOLD_ATTENDANCE_REMOVE_CONFIRM')",
			$source
		);
		self::assertStringNotContainsString("Remove ' . (string) (\$item->member_name", $source);
		self::assertStringNotContainsString("' from roster?'", $source);
	}

	public function testConfirmViewsLoadTheModule(): void
	{
		$views = [
			'/com_wendfold/site/src/View/Meetingattendance/HtmlView.php',
			'/com_wendfold/site/src/View/Myrsvps/HtmlView.php',
			'/com_wendfold/admin/src/View/Sync/HtmlView.php',
			'/com_wendfold/admin/src/View/Memberawards/HtmlView.php',
			'/com_wendfold/admin/src/View/Attendance/HtmlView.php',
		];

		foreach ($views as $view) {
			$source = (string) file_get_contents(dirname(__DIR__) . $view);

			self::assertStringContainsString("useScript('com_wendfold.confirm')", $source, $view);
		}
	}

	public function testConfirmScriptIsAnEsModuleWithoutJquery(): void
	{
		$manifest = json_decode(
			(string) file_get_contents(dirname(__DIR__) . '/com_wendfold/media/joomla.asset.json'),
			true,
			512,
			JSON_THROW_ON_ERROR
		);
		$confirm = null;

		foreach ($manifest['assets'] as $asset) {
			if (($asset['name'] ?? '') === 'com_wendfold.confirm' && ($asset['type'] ?? '') === 'script') {
				$confirm = $asset;
			}
		}

		self::assertIsArray($confirm);
		self::assertSame('com_wendfold/confirm.js', $confirm['uri']);
		self::assertSame('module', $confirm['attributes']['type'] ?? null);

		$script = (string) file_get_contents(dirname(__DIR__) . '/com_wendfold/media/js/confirm.js');

		self::assertStringContainsString('el.dataset.confirm', $script);
		self::assertStringContainsString('preventDefault', $script);
		self::assertStringContainsString("addEventListener('click'", $script);
		self::assertStringContainsString("addEventListener('submit'", $script);
		self::assertStringNotContainsString('jquery', strtolower($script));
		self::assertDoesNotMatchRegularExpression('/\bonclick\b/', $script);
	}

	private function buttonFrom(string $html): DOMElement
	{
		$button = $this->document($html)->getElementsByTagName('button')->item(0);
		self::assertInstanceOf(DOMElement::class, $button);

		return $button;
	}

	private function document(string $html): DOMDocument
	{
		$document = new DOMDocument();
		$loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NONET);
		self::assertTrue($loaded);

		return $document;
	}

	/**
	 * @return list<string>
	 */
	private function templateFiles(): array
	{
		$files = [];
		$root = dirname(__DIR__);
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root . '/com_wendfold', \FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			$path = $file->getPathname();

			if (!str_contains($path, '/tmpl/') || !str_ends_with($path, '.php')) {
				continue;
			}

			$files[] = $path;
		}

		sort($files);

		return $files;
	}

	/**
	 * @return list<array{name: string, value: string, where: string}>
	 */
	private function handlerHits(string $path, bool $phpOnly): array
	{
		$source = (string) file_get_contents($path);
		$hits = [];
		$length = strlen($source);
		$offset = 0;

		while (preg_match('/\bon[a-z]+\s*=/i', $source, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
			$start = $match[0][1];
			$name = strtolower(trim(rtrim($match[0][0], " \t=")));
			$cursor = $start + strlen($match[0][0]);

			while ($cursor < $length && ctype_space($source[$cursor])) {
				$cursor++;
			}

			if ($cursor >= $length || ($source[$cursor] !== '"' && $source[$cursor] !== "'")) {
				$offset = $cursor + 1;
				continue;
			}

			$quote = $source[$cursor];
			$valueStart = $cursor + 1;
			$valueEnd = $valueStart;

			while ($valueEnd < $length && $source[$valueEnd] !== $quote) {
				$valueEnd++;
			}

			$value = substr($source, $valueStart, $valueEnd - $valueStart);
			$containsPhp = str_contains($value, '<?php') || str_contains($value, '<?=');

			if (!$phpOnly || $containsPhp) {
				$line = substr_count($source, "\n", 0, $start) + 1;
				$hits[] = [
					'name' => $name,
					'value' => $value,
					'where' => $path . ':' . $line,
				];
			}

			$offset = $valueEnd + 1;
		}

		return $hits;
	}
}
