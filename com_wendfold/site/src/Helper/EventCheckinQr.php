<?php

/**
 * Wendfold - Site event page check-in QR visibility and image payload.
 *
 * Signed-in members, organizers, and admins may see a local check-in QR on an
 * open (self-check-in status, not ended) event page. Guests never receive the
 * token, check-in URL, or image. Closed and past events hide the QR for everyone.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Wendfold\Component\Wendfold\Administrator\Helper\QrCodeHelper;

/**
 * Builds the site event check-in QR when the viewer may see it.
 *
 * @since  1.0.0
 */
abstract class EventCheckinQr
{
	/**
	 * Whether this viewer may see a check-in QR for the event.
	 *
	 * Guests (user id 0), events whose status SelfCheckinStatus refuses, events
	 * after their end date and time, and events without a token are never eligible.
	 *
	 * @param   object    $event   Event row with status, dates, times, qr_token.
	 * @param   integer   $userId  Joomla user id. Zero is a guest.
	 * @param   SiteTime  $clock   Site timezone clock.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isVisible(object $event, int $userId, SiteTime $clock): bool
	{
		if ($userId <= 0)
		{
			return false;
		}

		$fields = get_object_vars($event);

		if (!SelfCheckinStatus::allows((int) ($fields['status'] ?? 0)))
		{
			return false;
		}

		if (self::hasEnded($event, $clock))
		{
			return false;
		}

		return trim((string) ($fields['qr_token'] ?? '')) !== '';
	}

	/**
	 * Whether the event's end date and time are already in the past.
	 *
	 * End date is end_date when set, otherwise event_date. End time is ksd_time
	 * when set, otherwise the last second of that calendar day.
	 *
	 * @param   object    $event  Event row.
	 * @param   SiteTime  $clock  Site timezone clock.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function hasEnded(object $event, SiteTime $clock): bool
	{
		$end = self::endInstant($event, $clock);

		if ($end === null)
		{
			return false;
		}

		return $clock->now()->getTimestamp() > $end->getTimestamp();
	}

	/**
	 * Check-in QR payload for the event page, or null when it must stay hidden.
	 *
	 * The returned array never includes the raw token or check-in URL as plain
	 * fields. The image data URI encodes the same absolute check-in URL the
	 * admin Attendance page uses.
	 *
	 * @param   object    $event   Event row.
	 * @param   integer   $userId  Joomla user id.
	 * @param   SiteTime  $clock   Site timezone clock.
	 *
	 * @return  array{dataUri: string, extension: string, eventId: int}|null
	 *
	 * @since   1.0.0
	 */
	public static function forViewer(object $event, int $userId, SiteTime $clock): ?array
	{
		if (!self::isVisible($event, $userId, $clock))
		{
			return null;
		}

		$fields = get_object_vars($event);
		$token = trim((string) ($fields['qr_token'] ?? ''));
		$checkinUrl = RouteHelper::site(['view' => 'checkin', 'token' => $token], true);

		return self::imagePayload($checkinUrl, (int) ($fields['id'] ?? 0));
	}

	/**
	 * Local QR image fields for a check-in URL.
	 *
	 * @param   string   $checkinUrl  Absolute check-in URL encoded in the symbol.
	 * @param   integer  $eventId     Event id for the download filename.
	 *
	 * @return  array{dataUri: string, extension: string, eventId: int}|null
	 *
	 * @since   1.0.0
	 */
	public static function imagePayload(string $checkinUrl, int $eventId): ?array
	{
		if ($checkinUrl === '' || $eventId <= 0)
		{
			return null;
		}

		try
		{
			$dataUri = QrCodeHelper::dataUri($checkinUrl);
			$extension = QrCodeHelper::fileExtension();
		}
		catch (\Throwable)
		{
			return null;
		}

		if ($dataUri === '')
		{
			return null;
		}

		return [
			'dataUri'   => $dataUri,
			'extension' => $extension,
			'eventId'   => $eventId,
		];
	}

	/**
	 * End instant in the site timezone, or null when the event has no end date.
	 *
	 * @param   object    $event  Event row.
	 * @param   SiteTime  $clock  Site timezone clock.
	 *
	 * @return  \DateTimeImmutable|null
	 *
	 * @since   1.0.0
	 */
	private static function endInstant(object $event, SiteTime $clock): ?\DateTimeImmutable
	{
		$fields = get_object_vars($event);
		$endDate = trim((string) ($fields['end_date'] ?? ''));

		if ($endDate === '')
		{
			$endDate = trim((string) ($fields['event_date'] ?? ''));
		}

		if ($endDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))
		{
			return null;
		}

		$endTime = trim((string) ($fields['ksd_time'] ?? ''));

		if ($endTime === '')
		{
			$endTime = '23:59:59';
		}

		$zone = $clock->now()->getTimezone();

		try
		{
			return new \DateTimeImmutable($endDate . ' ' . $endTime, $zone);
		}
		catch (\Throwable)
		{
			return null;
		}
	}
}
