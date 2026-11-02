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
 * Outbound mail for reminder sends.
 *
 * @since  1.0.0
 */
interface ReminderMailer
{
	/**
	 * Send one plain-text reminder. True only when the mailer reports success.
	 *
	 * @param   string  $email    Recipient address.
	 * @param   string  $name     Recipient name.
	 * @param   string  $subject  Subject line.
	 * @param   string  $body     Plain-text body.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function send(string $email, string $name, string $subject, string $body): bool;

	/**
	 * Raw mailer or exception detail from the most recent failed send().
	 *
	 * Empty after a successful send. Callers must redact before logging.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function lastError(): string;
}
