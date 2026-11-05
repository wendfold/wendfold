<?php

/**
 * Wendfold - In-memory ReminderLogStore for unit tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Service\ReminderLogStore;

/**
 * Claim/release store keyed like #__wendfold_reminder_log.
 *
 * @since  1.0.0
 */
final class MemoryReminderLogStore implements ReminderLogStore
{
	/**
	 * Rows keyed by profile|key|reference.
	 *
	 * @var    array<string, array{profile_id: int, reminder_key: string, reference_date: string, sent_at: string}>
	 * @since  1.0.0
	 */
	public array $rows = [];

	/**
	 * Keys already logged for a profile and reference date.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $referenceDate  Y-m-d.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function keysFor(int $profileId, string $referenceDate): array
	{
		$keys = [];

		foreach ($this->rows as $row)
		{
			if ($row['profile_id'] === $profileId && $row['reference_date'] === $referenceDate)
			{
				$keys[] = $row['reminder_key'];
			}
		}

		return $keys;
	}

	/**
	 * Claim a log row. False means the unique key already exists.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $reminderKey    Reminder key.
	 * @param   string   $referenceDate  Y-m-d.
	 * @param   string   $sentAt         Sent timestamp.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function claim(int $profileId, string $reminderKey, string $referenceDate, string $sentAt): bool
	{
		$id = $profileId . '|' . $reminderKey . '|' . $referenceDate;

		if (isset($this->rows[$id]))
		{
			return false;
		}

		$this->rows[$id] = [
			'profile_id'     => $profileId,
			'reminder_key'   => $reminderKey,
			'reference_date' => $referenceDate,
			'sent_at'        => $sentAt,
		];

		return true;
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
		unset($this->rows[$profileId . '|' . $reminderKey . '|' . $referenceDate]);
	}
}
