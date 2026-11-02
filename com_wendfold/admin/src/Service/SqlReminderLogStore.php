<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Database-backed reminder log.
 *
 * @since  1.0.0
 */
final class SqlReminderLogStore implements ReminderLogStore
{
	/**
	 * @param   DatabaseInterface  $db  Database driver.
	 *
	 * @since   1.0.0
	 */
	public function __construct(private DatabaseInterface $db)
	{
	}

	/**
	 * Keys already logged for a profile and reference date.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $referenceDate  Y-m-d renewal or event date.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function keysFor(int $profileId, string $referenceDate): array
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName('reminder_key'))
			->from($this->db->quoteName('#__wendfold_reminder_log'))
			->where($this->db->quoteName('profile_id') . ' = :pid')
			->where($this->db->quoteName('reference_date') . ' = :ref')
			->bind(':pid', $profileId, ParameterType::INTEGER)
			->bind(':ref', $referenceDate);

		$keys = $this->db->setQuery($query)->loadColumn();

		if (!\is_array($keys))
		{
			return [];
		}

		return array_values(array_map('strval', $keys));
	}

	/**
	 * Claim a log row. False means the unique key already exists.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $reminderKey    e.g. renewal-60 or event-12-0.
	 * @param   string   $referenceDate  Y-m-d.
	 * @param   string   $sentAt         UTC datetime for sent_at.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function claim(int $profileId, string $reminderKey, string $referenceDate, string $sentAt): bool
	{
		$values = implode(',', [':pid', ':key', ':ref', ':sent']);
		$query  = $this->db->createQuery()
			->insert($this->db->quoteName('#__wendfold_reminder_log'))
			->columns(
				[
					$this->db->quoteName('profile_id'),
					$this->db->quoteName('reminder_key'),
					$this->db->quoteName('reference_date'),
					$this->db->quoteName('sent_at'),
				]
			)
			->values($values);
		$query->bind(':pid', $profileId, ParameterType::INTEGER)
			->bind(':key', $reminderKey)
			->bind(':ref', $referenceDate)
			->bind(':sent', $sentAt);

		try
		{
			$this->db->setQuery($query)->execute();

			return true;
		}
		catch (\Throwable $e)
		{
			if ($this->isDuplicateKey($e))
			{
				return false;
			}

			throw $e;
		}
	}

	/**
	 * Remove a claim so a failed send can be retried.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $reminderKey    Reminder key.
	 * @param   string   $referenceDate  Y-m-d.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function release(int $profileId, string $reminderKey, string $referenceDate): void
	{
		$query = $this->db->createQuery()
			->delete($this->db->quoteName('#__wendfold_reminder_log'))
			->where($this->db->quoteName('profile_id') . ' = :pid')
			->where($this->db->quoteName('reminder_key') . ' = :key')
			->where($this->db->quoteName('reference_date') . ' = :ref')
			->bind(':pid', $profileId, ParameterType::INTEGER)
			->bind(':key', $reminderKey)
			->bind(':ref', $referenceDate);

		$this->db->setQuery($query)->execute();
	}

	/**
	 * Detect a unique-key collision from the driver message.
	 *
	 * @param   \Throwable  $e  Database exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isDuplicateKey(\Throwable $e): bool
	{
		$message = $e->getMessage();

		return str_contains($message, 'Duplicate')
			|| str_contains($message, '1062')
			|| str_contains($message, 'UNIQUE');
	}
}
