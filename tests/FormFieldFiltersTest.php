<?php

/**
 * Wendfold - form XML field types and filters for rich text vs plain text.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Locks editor + filterText on event description and safe filters on notes.
 *
 * @since  1.0.0
 */
final class FormFieldFiltersTest extends TestCase
{
	private const FILTER_TEXT = '\Joomla\CMS\Component\ComponentHelper::filterText';

	/**
	 * Admin event description uses the editor and group text filters.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventDescriptionIsEditorWithFilterText(): void
	{
		$field = $this->field('com_wendfold/admin/forms/event.xml', 'description');

		self::assertSame('editor', (string) $field['type']);
		self::assertSame(self::FILTER_TEXT, (string) $field['filter']);
		self::assertSame('true', (string) $field['buttons']);
		self::assertSame('COM_WENDFOLD_FIELD_DESCRIPTION_LABEL', (string) $field['label']);
		self::assertSame('COM_WENDFOLD_FIELD_DESCRIPTION_DESC', (string) $field['description']);
	}

	/**
	 * Plain-text note and description fields keep textarea with a safe filter.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPlainTextNoteFieldsUseSafeFilters(): void
	{
		$cases = [
			['com_wendfold/admin/forms/event.xml', 'trip_lodging_notes', 'string'],
			['com_wendfold/admin/forms/event.xml', 'volunteer_notes', 'string'],
			['com_wendfold/admin/forms/event.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/duespayment.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/location.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/member.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/motorcycle.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/officer.xml', 'notes', 'string'],
			['com_wendfold/admin/forms/volunteerrole.xml', 'description', 'string'],
			['com_wendfold/admin/forms/awarddefinition.xml', 'description', 'safehtml'],
		];

		foreach ($cases as [$path, $name, $filter])
		{
			$field = $this->field($path, $name);
			self::assertSame('textarea', (string) $field['type'], $path . '#' . $name);
			self::assertSame($filter, (string) $field['filter'], $path . '#' . $name);
		}
	}

	/**
	 * User-editable text fields must not use filter="raw".
	 *
	 * Media and file fields may still use raw for path storage.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoRawFilterOnUserEditableTextFields(): void
	{
		$roots = [
			dirname(__DIR__) . '/com_wendfold/admin/forms',
			dirname(__DIR__) . '/com_wendfold/site',
			dirname(__DIR__) . '/plg_task_wendfold/forms',
		];
		$textTypes = ['text', 'textarea', 'editor', 'email', 'tel', 'url'];

		foreach ($this->formXmlFiles($roots) as $path)
		{
			$xml = simplexml_load_file($path);
			self::assertNotFalse($xml, $path);

			foreach ($xml->xpath('//field') ?: [] as $field)
			{
				$type = strtolower((string) $field['type']);
				$filter = (string) $field['filter'];
				$name = (string) $field['name'];

				if (!in_array($type, $textTypes, true))
				{
					continue;
				}

				self::assertNotSame(
					'raw',
					strtolower($filter),
					$path . ' field "' . $name . '" must not use filter="raw"'
				);
			}
		}
	}

	/**
	 * Site event and trip views prepare HTML descriptions; teasers escape plain text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEventDescriptionRenderingUsesContentPrepare(): void
	{
		$event = $this->read('com_wendfold/site/tmpl/event/default.php');
		$trip = $this->read('com_wendfold/site/tmpl/trip/default.php');
		$events = $this->read('com_wendfold/site/tmpl/events/default.php');

		self::assertStringContainsString(
			"HTMLHelper::_('content.prepare', (string) \$item->description)",
			$event
		);
		self::assertStringNotContainsString(
			'nl2br(htmlspecialchars((string) $item->description',
			$event
		);

		self::assertStringContainsString(
			"HTMLHelper::_('content.prepare', (string) \$item->description)",
			$trip
		);
		self::assertStringNotContainsString(
			'nl2br(htmlspecialchars((string) $item->description',
			$trip
		);

		self::assertStringContainsString(
			"OutputEscape::hasVisibleContent((string) (\$item->description ?? ''))",
			$event
		);
		self::assertStringContainsString(
			"OutputEscape::hasVisibleContent((string) (\$item->description ?? ''))",
			$trip
		);
		self::assertStringContainsString(
			"OutputEscape::hasVisibleContent((string) (\$item->description ?? ''))",
			$events
		);
		self::assertStringContainsString('OutputEscape::htmlExcerpt((string) $item->description, 100)', $events);
		self::assertStringContainsString("echo \$event['excerpt'];", $events);
		self::assertStringContainsString(
			"OutputEscape::htmlExcerpt((string) (\$childEvent->description ?? ''), 140)",
			$trip
		);
		self::assertStringNotContainsString('strip_tags((string) $item->description)', $events);
		self::assertStringNotContainsString(
			'strip_tags((string) ($childEvent->description',
			$trip
		);

		self::assertStringContainsString(
			'nl2br(htmlspecialchars((string) $item->trip_lodging_notes',
			$event
		);
		self::assertStringContainsString(
			'nl2br(htmlspecialchars((string) $item->volunteer_notes',
			$this->read('com_wendfold/site/tmpl/volunteerboard/default.php')
		);
		self::assertStringContainsString(
			'htmlspecialchars($item->notes, ENT_QUOTES)',
			$this->read('com_wendfold/admin/tmpl/locations/default.php')
		);
	}

	/**
	 * Load one named field from a form XML file.
	 *
	 * @param   string  $relativePath  Path from the repository root.
	 * @param   string  $name          Field name attribute.
	 *
	 * @return  SimpleXMLElement
	 *
	 * @since   1.0.0
	 */
	private function field(string $relativePath, string $name): SimpleXMLElement
	{
		$xml = simplexml_load_file(dirname(__DIR__) . '/' . $relativePath);
		self::assertNotFalse($xml, $relativePath);
		$matches = $xml->xpath('//field[@name="' . $name . '"]') ?: [];
		self::assertNotEmpty($matches, $relativePath . '#' . $name);

		return $matches[0];
	}

	/**
	 * Read a repository file as a string.
	 *
	 * @param   string  $relativePath  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function read(string $relativePath): string
	{
		$contents = file_get_contents(dirname(__DIR__) . '/' . $relativePath);
		self::assertNotFalse($contents, $relativePath);

		return $contents;
	}

	/**
	 * Collect form XML paths under the given roots.
	 *
	 * @param   list<string>  $roots  Absolute directories to scan.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function formXmlFiles(array $roots): array
	{
		$files = [];

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
				if (!$file->isFile())
				{
					continue;
				}

				$path = $file->getPathname();

				if (str_ends_with($path, '.xml')
					&& (str_contains($path, '/forms/') || str_ends_with($path, '/default.xml')))
				{
					$files[] = $path;
				}
			}
		}

		sort($files);

		return $files;
	}
}
