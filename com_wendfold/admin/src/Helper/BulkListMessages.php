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

defined('_JEXEC') or die;

/**
 * Builds honest success and failure messages for admin list batch actions.
 *
 * Counts only rows that actually changed. Failures use translated plurals —
 * never raw database or exception text.
 *
 * @since  1.0.0
 */
final class BulkListMessages
{
	/**
	 * Message type for a success enqueue/setMessage call.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const TYPE_SUCCESS = 'success';

	/**
	 * Message type for a failure enqueue/setMessage call.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const TYPE_ERROR = 'error';

	/**
	 * Message type for an informational notice (all rows already at target).
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const TYPE_NOTICE = 'notice';

	/**
	 * Messages after publish / unpublish / archive / trash.
	 *
	 * @param   string   $textPrefix  Controller text_prefix.
	 * @param   integer  $value       1 publish, 0 unpublish, 2 archive, -2 trash.
	 * @param   integer  $requested   Items selected in the request.
	 * @param   integer  $succeeded   Items that actually changed.
	 * @param   integer  $failed      Items that failed (including ACL refusals).
	 *
	 * @return  list<array{key: string, count: int, type: string}>
	 *
	 * @since   1.0.0
	 */
	public static function forPublish(
		string $textPrefix,
		int $value,
		int $requested,
		int $succeeded,
		int $failed = 0
	): array {
		$messages = [];
		$requested = max(0, $requested);
		$succeeded = max(0, $succeeded);
		$failed = max(0, $failed);

		if ($succeeded > 0)
		{
			$messages[] = [
				'key'   => self::successKeyForValue($textPrefix, $value),
				'count' => $succeeded,
				'type'  => self::TYPE_SUCCESS,
			];
		}

		if ($failed > 0)
		{
			$messages[] = [
				'key'   => self::failureKeyForValue($textPrefix, $value),
				'count' => $failed,
				'type'  => self::TYPE_ERROR,
			];
		}

		if ($requested > 0 && $succeeded === 0 && $failed === 0)
		{
			$messages[] = [
				'key'   => $textPrefix . '_NO_ITEMS_NEEDED_CHANGING',
				'count' => $requested,
				'type'  => self::TYPE_NOTICE,
			];
		}

		return $messages;
	}

	/**
	 * Messages after delete.
	 *
	 * @param   string   $textPrefix  Controller text_prefix.
	 * @param   integer  $requested   Items selected in the request.
	 * @param   integer  $succeeded   Items successfully deleted.
	 * @param   integer  $failed      Items that failed (including ACL refusals).
	 *
	 * @return  list<array{key: string, count: int, type: string}>
	 *
	 * @since   1.0.0
	 */
	public static function forDelete(
		string $textPrefix,
		int $requested,
		int $succeeded,
		int $failed = 0
	): array {
		$messages = [];
		$succeeded = max(0, $succeeded);
		$failed = max(0, $failed);

		if ($succeeded > 0)
		{
			$messages[] = [
				'key'   => $textPrefix . '_N_ITEMS_DELETED',
				'count' => $succeeded,
				'type'  => self::TYPE_SUCCESS,
			];
		}

		if ($failed > 0)
		{
			$messages[] = [
				'key'   => $textPrefix . '_N_ITEMS_FAILED_DELETING',
				'count' => $failed,
				'type'  => self::TYPE_ERROR,
			];
		}

		return $messages;
	}

	/**
	 * Plural success key for a published-state value.
	 *
	 * @param   string   $textPrefix  Controller text_prefix.
	 * @param   integer  $value       Published state value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function successKeyForValue(string $textPrefix, int $value): string
	{
		if ($value === 1)
		{
			return $textPrefix . '_N_ITEMS_PUBLISHED';
		}

		if ($value === 0)
		{
			return $textPrefix . '_N_ITEMS_UNPUBLISHED';
		}

		if ($value === 2)
		{
			return $textPrefix . '_N_ITEMS_ARCHIVED';
		}

		return $textPrefix . '_N_ITEMS_TRASHED';
	}

	/**
	 * Plural failure key for a published-state value.
	 *
	 * @param   string   $textPrefix  Controller text_prefix.
	 * @param   integer  $value       Published state value.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private static function failureKeyForValue(string $textPrefix, int $value): string
	{
		if ($value === 1)
		{
			return $textPrefix . '_N_ITEMS_FAILED_PUBLISHING';
		}

		if ($value === 0)
		{
			return $textPrefix . '_N_ITEMS_FAILED_UNPUBLISHING';
		}

		if ($value === 2)
		{
			return $textPrefix . '_N_ITEMS_FAILED_ARCHIVING';
		}

		return $textPrefix . '_N_ITEMS_FAILED_TRASHING';
	}
}
