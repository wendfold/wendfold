<?php

/**
 * Wendfold - WendfoldLog throwable formatting and recalculate logging.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Log\Log;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php';

/**
 * Throwable log lines include class, message, and file:line.
 *
 * @since  1.0.0
 */
final class WendfoldLogTest extends TestCase
{
	/**
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		Log::$messages = [];
		Log::$loggers = [];

		$ref = new \ReflectionClass(WendfoldLog::class);
		$prop = $ref->getProperty('categoryRegistered');
		$prop->setValue(null, false);
	}

	/**
	 * formatThrowable keeps the operator-facing context and exception metadata.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testFormatThrowableIncludesClassMessageAndLocation(): void
	{
		$error = new \RuntimeException('boom happened');
		$line = __LINE__ - 1;
		$formatted = WendfoldLog::formatThrowable('Member awards recalculate failed', $error);

		$this->assertStringContainsString('Member awards recalculate failed', $formatted);
		$this->assertStringContainsString('boom happened', $formatted);
		$this->assertStringContainsString(\RuntimeException::class, $formatted);
		$this->assertStringContainsString(__FILE__, $formatted);
		$this->assertStringContainsString(':' . $line, $formatted);
	}

	/**
	 * Recalculate catches Throwable, logs via WendfoldLog, and never shows the exception text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRecalculateLogsThrowableWithoutExposingMessage(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/MemberawardsController.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString('catch (\Throwable $error)', $source);
		$this->assertStringContainsString(
			"WendfoldLog::throwable('Member awards recalculate failed', \$error)",
			$source
		);
		$this->assertStringContainsString(
			"COM_WENDFOLD_MEMBERAWARDS_ERR_RECALCULATE_FAILED",
			$source
		);
		$this->assertStringNotContainsString(
			'$app->enqueueMessage($error->getMessage()',
			$source
		);
	}

	/**
	 * Helper registers the shared log file category.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testHelperRegistersComWendfoldLogger(): void
	{
		$source = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Helper/WendfoldLog.php'
		);
		$this->assertIsString($source);
		$this->assertStringContainsString("addLogger(['text_file' => 'com_wendfold.php']", $source);
		$this->assertStringContainsString('Log::DEBUG', $source);
		$this->assertStringContainsString('getTraceAsString', $source);
	}

	/**
	 * ensureCategory registers once; a CLI-style failure line reaches the logger.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEnsureCategoryIsIdempotentAndKeepsCliFailureLines(): void
	{
		WendfoldLog::ensureCategory();
		WendfoldLog::ensureCategory();

		$this->assertCount(1, Log::$loggers);
		$this->assertSame(['text_file' => 'com_wendfold.php'], Log::$loggers[0][0]);
		$this->assertSame(['com_wendfold'], Log::$loggers[0][2]);

		Log::add('Renewal reminder send failed for profile 9', Log::ERROR, 'com_wendfold');

		$this->assertCount(1, Log::$messages);
		$this->assertSame('com_wendfold', Log::$messages[0][2]);
		$this->assertStringContainsString('Renewal reminder send failed', Log::$messages[0][0]);
	}

	/**
	 * Task plugin reminder entry points register the shared logger before work.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testTaskPluginRegistersLoggerOnReminderEntryPoints(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/plg_task_wendfold/src/Extension/Wendfold.php'
		);

		foreach (['runRenewalReminders', 'runEventReminders'] as $method)
		{
			$pos = strpos($source, 'function ' . $method);
			$this->assertNotFalse($pos, $method . ' missing');
			$snippet = substr($source, $pos, 280);
			$this->assertStringContainsString('WendfoldLog::ensureCategory()', $snippet, $method);
		}
	}

	/**
	 * ReminderService registers the logger before any com_wendfold Log::add.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testReminderServiceRegistersLoggerBeforeSend(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Service/ReminderService.php'
		);

		foreach (['sendRenewalReminders', 'sendEventReminders'] as $method)
		{
			$pos = strpos($source, 'function ' . $method);
			$this->assertNotFalse($pos, $method . ' missing');
			$bodyStart = strpos($source, '{', $pos);
			$snippet = substr($source, $bodyStart, 200);
			$this->assertStringContainsString('WendfoldLog::ensureCategory()', $snippet, $method);
		}
	}

	/**
	 * Component providers share WendfoldLog::ensureCategory (no bare addLogger).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testComponentProvidersUseEnsureCategory(): void
	{
		foreach (['admin', 'site'] as $client)
		{
			$source = (string) file_get_contents(
				dirname(__DIR__) . '/com_wendfold/' . $client . '/services/provider.php'
			);
			$this->assertStringContainsString('WendfoldLog::ensureCategory()', $source);
			$this->assertStringNotContainsString('Log::addLogger', $source);
		}
	}
}
