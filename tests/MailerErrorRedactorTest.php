<?php

/**
 * Wendfold - MailerErrorRedactor and mail-send failure log redaction.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\CMS\Log\Log;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\EmailblastHelper;
use Wendfold\Component\Wendfold\Administrator\Helper\MailerErrorRedactor;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MailerErrorRedactor.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EmailblastHelper.php';

/**
 * Redact addresses from mailer error text before logging.
 *
 * @since  1.0.0
 */
final class MailerErrorRedactorTest extends TestCase
{
	/**
	 * One address becomes [redacted].
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsOneAddress(): void
	{
		$this->assertSame(
			'recipients failed: [redacted]',
			MailerErrorRedactor::redact('recipients failed: name@example.com')
		);
	}

	/**
	 * Several addresses, mixed case, plus-tags, and long or IDN TLDs are redacted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsManyMixedCaseSubaddressAndIdn(): void
	{
		$input = 'SMTP: Name@Example.COM, other+tag@sub.example.museum, user@xn--fsq.com, 用户@例子.公司';
		$out   = MailerErrorRedactor::redact($input);

		$this->assertSame(
			'SMTP: [redacted], [redacted], [redacted], [redacted]',
			$out
		);
		$this->assertStringNotContainsString('@', $out);
	}

	/**
	 * Angle-bracketed address forms are replaced as a whole.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsAngleBracketedAddresses(): void
	{
		$this->assertSame(
			'to [redacted] failed',
			MailerErrorRedactor::redact('to <a@b.c> failed')
		);
		$this->assertSame(
			'[redacted] and [redacted]',
			MailerErrorRedactor::redact('<Ada@Example.com> and name+tag@example.org')
		);
	}

	/**
	 * Dotless domain labels are still recipient identifiers.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsDotlessDomains(): void
	{
		$this->assertSame(
			'failed: [redacted], [redacted], [redacted]',
			MailerErrorRedactor::redact('failed: root@localhost, admin@mailhost, user+tag@intranet')
		);
		$this->assertSame(
			'not an email: [redacted]',
			MailerErrorRedactor::redact('not an email: foo@bar')
		);
	}

	/**
	 * Quoted local-parts are redacted including the quotes.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsQuotedLocalParts(): void
	{
		$this->assertSame(
			'[redacted] and [redacted]',
			MailerErrorRedactor::redact('"john doe"@example.com and "a.b"@localhost')
		);
		$this->assertSame(
			'to [redacted]',
			MailerErrorRedactor::redact('to <"Ada Lovelace"@Example.com>')
		);
	}

	/**
	 * Ordinary '@' text is not treated as an address.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLeavesAtMentionAndLoneAtAlone(): void
	{
		$this->assertSame('at @mention', MailerErrorRedactor::redact('at @mention'));
		$this->assertSame('lone @ here', MailerErrorRedactor::redact('lone @ here'));
		$this->assertSame('@', MailerErrorRedactor::redact('@'));
	}

	/**
	 * Trailing punctuation after an address is preserved.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPreservesTrailingPunctuation(): void
	{
		$this->assertSame('code:[redacted]:', MailerErrorRedactor::redact('code:a@b.com:'));
		$this->assertSame('list [redacted];', MailerErrorRedactor::redact('list a@b.com;'));
		$this->assertSame('[redacted], next', MailerErrorRedactor::redact('a@b.com, next'));
		$this->assertSame('([redacted])', MailerErrorRedactor::redact('(a@b.com)'));
		$this->assertSame('end [redacted].', MailerErrorRedactor::redact('end a@b.com.'));
	}

	/**
	 * IPv4 literal domains are redacted.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsIpv4LiteralDomains(): void
	{
		$this->assertSame(
			'relay [redacted] rejected',
			MailerErrorRedactor::redact('relay user@[192.0.2.1] rejected')
		);
		$this->assertSame(
			'[redacted]',
			MailerErrorRedactor::redact('"postmaster"@[192.0.2.1]')
		);
	}

	/**
	 * URL userinfo local@host is redacted; scheme and port remain.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRedactsUrlUserinfo(): void
	{
		$this->assertSame(
			'smtp://[redacted]:25',
			MailerErrorRedactor::redact('smtp://user@host:25')
		);
		$this->assertSame(
			'smtp://[redacted]:25/path',
			MailerErrorRedactor::redact('smtp://user+tag@intranet:25/path')
		);
	}

	/**
	 * Non-address text is left alone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testLeavesNonAddressTextAlone(): void
	{
		$text = 'Connection timed out after 30 seconds';
		$this->assertSame($text, MailerErrorRedactor::redact($text));
		$this->assertSame('', MailerErrorRedactor::redact(''));
	}

	/**
	 * append() keeps the traceable prefix and redacts the detail.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testAppendKeepsPrefixAndRedactsDetail(): void
	{
		$line = MailerErrorRedactor::append(
			'Email blast send failed for profile 42',
			'recipients failed: blast@example.com'
		);

		$this->assertSame(
			'Email blast send failed for profile 42: recipients failed: [redacted]',
			$line
		);
		$this->assertStringNotContainsString('blast@example.com', $line);
		$this->assertSame(
			'Email blast send failed for profile 42',
			MailerErrorRedactor::append('Email blast send failed for profile 42', '')
		);
	}

	/**
	 * Email blast failure logging uses the redactor with a profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmailBlastFailureLogRedactsAddresses(): void
	{
		Log::$messages = [];

		$line = MailerErrorRedactor::append(
			'Email blast send failed for profile 17',
			'SMTP Error: The following recipients failed: One@Example.com, two+tag@例子.company'
		);
		Log::add($line, Log::ERROR, 'com_wendfold');

		$this->assertCount(1, Log::$messages);
		$message = Log::$messages[0][0];
		$this->assertStringContainsString('profile 17', $message);
		$this->assertStringContainsString('[redacted]', $message);
		$this->assertStringNotContainsString('One@Example.com', $message);
		$this->assertStringNotContainsString('two+tag@', $message);
		$this->assertStringNotContainsString('@例子.company', $message);

		$controller = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Controller/EmailblastController.php'
		);
		$helper = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EmailblastHelper.php'
		);
		$this->assertStringContainsString('EmailblastHelper::attemptSend', $controller);
		$model = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/src/Model/EmailblastModel.php'
		);
		$this->assertStringContainsString("quoteName(['p.id', 'u.name', 'u.email'])", $model);
		$this->assertStringContainsString('MailerErrorRedactor::append', $helper);
		$this->assertStringContainsString('Email blast send failed for profile', $helper);

		Log::$messages = [];
		EmailblastHelper::logFailure(
			17,
			'SMTP Error: The following recipients failed: One@Example.com'
		);
		$this->assertNotEmpty(Log::$messages);
		$this->assertStringContainsString('profile 17', Log::$messages[0][0]);
		$this->assertStringContainsString('[redacted]', Log::$messages[0][0]);
		$this->assertStringNotContainsString('One@Example.com', Log::$messages[0][0]);
	}

	/**
	 * User-plugin admin notify catch redacts mailer text and keeps the profile id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testUserPluginNotifyAdminFailureLogRedactsAddresses(): void
	{
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/plg_user_wendfold/src/Extension/Wendfold.php'
		);

		$this->assertStringContainsString('MailerErrorRedactor::append', $source);
		$this->assertStringContainsString(
			'plg_user_wendfold notifyAdmin error for profile',
			$source
		);

		$line = MailerErrorRedactor::append(
			'plg_user_wendfold notifyAdmin error for profile 9',
			'Could not instantiate mail function. recipients failed: admin@example.com'
		);

		$this->assertStringContainsString('profile 9', $line);
		$this->assertStringContainsString('[redacted]', $line);
		$this->assertStringNotContainsString('admin@example.com', $line);
	}
}
