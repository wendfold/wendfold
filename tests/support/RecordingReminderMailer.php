<?php

/**
 * Wendfold - Recording ReminderMailer for unit tests.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests\Support;

use Wendfold\Component\Wendfold\Administrator\Service\ReminderMailer;

/**
 * Records outbound reminder mail and can force failures.
 *
 * @since  1.0.0
 */
final class RecordingReminderMailer implements ReminderMailer
{
	/**
	 * Sent messages, oldest first.
	 *
	 * @var    list<array{email: string, name: string, subject: string, body: string}>
	 * @since  1.0.0
	 */
	public array $sent = [];

	/**
	 * When true, send() returns false.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $fail = false;

	/**
	 * Addresses that fail while others still send.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $failEmails = [];

	/**
	 * Raw failure detail returned by lastError() after a forced failure.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public string $failureDetail = '';

	/**
	 * Detail from the most recent failed send().
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $lastError = '';

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
	public function send(string $email, string $name, string $subject, string $body): bool
	{
		$this->lastError = '';

		if ($this->fail || \in_array($email, $this->failEmails, true))
		{
			$this->lastError = $this->failureDetail;

			return false;
		}

		$this->sent[] = [
			'email'   => $email,
			'name'    => $name,
			'subject' => $subject,
			'body'    => $body,
		];

		return true;
	}

	/**
	 * Raw failure detail from the most recent failed send().
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function lastError(): string
	{
		return $this->lastError;
	}
}
