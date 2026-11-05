<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

/**
 * Shared com_wendfold file logger helpers.
 *
 * Registers the category when callers run outside the component provider
 * (scheduled tasks, unit-test stubs that still load Log).
 *
 * @since  1.0.0
 */
final class WendfoldLog
{
	/**
	 * Whether the com_wendfold log category has been registered this process.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private static bool $categoryRegistered = false;

	/**
	 * Register the shared com_wendfold log file once per process.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function ensureCategory(): void
	{
		if (self::$categoryRegistered || !\class_exists(Log::class))
		{
			return;
		}

		Log::addLogger(['text_file' => 'com_wendfold.php'], Log::ALL, ['com_wendfold']);
		self::$categoryRegistered = true;
	}

	/**
	 * One-line summary of a throwable for the primary log entry.
	 *
	 * @param   string      $context  Short context label.
	 * @param   \Throwable  $error    Caught error.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function formatThrowable(string $context, \Throwable $error): string
	{
		return sprintf(
			'%s: %s (%s) in %s:%d',
			$context,
			$error->getMessage(),
			$error::class,
			$error->getFile(),
			$error->getLine()
		);
	}

	/**
	 * Write one com_wendfold log line after ensuring the category is registered.
	 *
	 * @param   string   $message   Detail text.
	 * @param   integer  $priority  Log priority constant.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function add(string $message, int $priority = Log::ERROR): void
	{
		if (!\class_exists(Log::class))
		{
			return;
		}

		try
		{
			self::ensureCategory();
			Log::add($message, $priority, 'com_wendfold');
		}
		catch (\Throwable)
		{
			// Logging must never replace the original failure path.
		}
	}

	/**
	 * Log a throwable at ERROR with a DEBUG stack trace.
	 *
	 * @param   string      $context  Short context label.
	 * @param   \Throwable  $error    Caught error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function throwable(string $context, \Throwable $error): void
	{
		if (!\class_exists(Log::class))
		{
			return;
		}

		try
		{
			self::add(self::formatThrowable($context, $error), Log::ERROR);
			self::add($error->getTraceAsString(), Log::DEBUG);
		}
		catch (\Throwable)
		{
			// Logging must never replace the original failure path.
		}
	}
}
