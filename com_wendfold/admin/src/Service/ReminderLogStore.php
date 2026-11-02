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

/**
 * Persistence for #__wendfold_reminder_log.
 *
 * @since  1.0.0
 */
interface ReminderLogStore
{
	/**
	 * Keys already logged for a profile and reference date.
	 *
	 * @param   integer  $profileId      Profile id.
	 * @param   string   $referenceDate  Y-m-d renewal or event date.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	public function keysFor(int $profileId, string $referenceDate): array;

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
	public function claim(int $profileId, string $reminderKey, string $referenceDate, string $sentAt): bool;

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
	public function release(int $profileId, string $reminderKey, string $referenceDate): void;
}
