<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  Privacy.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Plugin\Privacy\Wendfold\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Privacy\CanRemoveDataEvent;
use Joomla\CMS\Event\Privacy\CollectCapabilitiesEvent;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Language\Text;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Component\Privacy\Administrator\Removal\Status;
use Joomla\Event\SubscriberInterface;
use Wendfold\Plugin\Privacy\Wendfold\Service\DatabasePrivacyMemberStore;
use Wendfold\Plugin\Privacy\Wendfold\Service\PrivacyDataHandler;
use Wendfold\Plugin\Privacy\Wendfold\Service\PrivacyMemberStoreInterface;

/**
 * Privacy plugin for Wendfold member data.
 *
 * @since  1.0.0
 */
final class Wendfold extends PrivacyPlugin implements SubscriberInterface
{
	/**
	 * Optional store override for tests.
	 *
	 * @var    PrivacyMemberStoreInterface|null
	 * @since  1.0.0
	 */
	private ?PrivacyMemberStoreInterface $storeOverride = null;

	/**
	 * Optional handler override for tests.
	 *
	 * @var    PrivacyDataHandler|null
	 * @since  1.0.0
	 */
	private ?PrivacyDataHandler $handlerOverride = null;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array<string, string>
	 *
	 * @since   1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onPrivacyCollectAdminCapabilities' => 'onPrivacyCollectAdminCapabilities',
			'onPrivacyExportRequest'            => 'onPrivacyExportRequest',
			'onPrivacyCanRemoveData'            => 'onPrivacyCanRemoveData',
			'onPrivacyRemoveData'               => 'onPrivacyRemoveData',
		];
	}

	/**
	 * Inject a store for unit tests.
	 *
	 * @param   PrivacyMemberStoreInterface  $store  Store.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setStore(PrivacyMemberStoreInterface $store): void
	{
		$this->storeOverride = $store;
		$this->handlerOverride = null;
	}

	/**
	 * Reports Wendfold personal-data capabilities to com_privacy.
	 *
	 * @param   CollectCapabilitiesEvent  $event  Capabilities event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onPrivacyCollectAdminCapabilities(CollectCapabilitiesEvent $event): void
	{
		$event->addResult(
			[
				Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITIES_SECTION') => [
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_MEMBER_PROFILE'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_MOTORCYCLES'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_ATTENDANCE_RSVP'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_METRICS_AWARDS'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_DUES_OFFICERS'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_PHOTOS'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_REMINDERS'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_SYNC_LOG'),
					Text::_('PLG_PRIVACY_WENDFOLD_CAPABILITY_THIRD_PARTY'),
				],
			]
		);
	}

	/**
	 * Export Wendfold personal data for a privacy request.
	 *
	 * @param   ExportRequestEvent  $event  Export event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onPrivacyExportRequest(ExportRequestEvent $event): void
	{
		$user = $event->getUser();

		if ($user === null)
		{
			return;
		}

		$domains = [];
		$exported = $this->handler()->export((int) $user->id, (string) $user->name);

		foreach ($exported as $name => $rows)
		{
			$domain = $this->createDomain($name, $name . '_data');

			foreach ($rows as $row)
			{
				$itemId = isset($row['id']) ? (int) $row['id'] : null;
				$domain->addItem($this->createItemFromArray($row, $itemId));
			}

			$domains[] = $domain;
		}

		if ($domains !== [])
		{
			$event->addResult($domains);
		}
	}

	/**
	 * Decide whether Wendfold data for the user may be removed.
	 *
	 * @param   CanRemoveDataEvent  $event  Can-remove event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onPrivacyCanRemoveData(CanRemoveDataEvent $event): void
	{
		$status = new Status;
		$user   = $event->getUser();

		if ($user === null)
		{
			$event->addResult($status);

			return;
		}

		$reason = $this->handler()->removalBlockReason((int) $user->id);

		if ($reason !== null)
		{
			$status->canRemove = false;
			$status->reason    = Text::_($reason);
		}

		$event->addResult($status);
	}

	/**
	 * Anonymize Wendfold personal data for a remove request.
	 *
	 * @param   RemoveDataEvent  $event  Remove event.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onPrivacyRemoveData(RemoveDataEvent $event): void
	{
		$user = $event->getUser();

		if ($user === null)
		{
			return;
		}

		$tokens = array_filter(
			[
				(string) $user->name,
				(string) $user->username,
			],
			static fn (string $value): bool => trim($value) !== ''
		);

		$root = \defined('JPATH_ROOT') ? (string) JPATH_ROOT : '';

		$this->handler()->remove((int) $user->id, $root, array_values($tokens));
	}

	/**
	 * Build or return the privacy data handler.
	 *
	 * @return  PrivacyDataHandler
	 *
	 * @since   1.0.0
	 */
	private function handler(): PrivacyDataHandler
	{
		if ($this->handlerOverride instanceof PrivacyDataHandler)
		{
			return $this->handlerOverride;
		}

		$this->handlerOverride = new PrivacyDataHandler($this->store());

		return $this->handlerOverride;
	}

	/**
	 * Build or return the member store.
	 *
	 * @return  PrivacyMemberStoreInterface
	 *
	 * @since   1.0.0
	 */
	private function store(): PrivacyMemberStoreInterface
	{
		if ($this->storeOverride instanceof PrivacyMemberStoreInterface)
		{
			return $this->storeOverride;
		}

		return new DatabasePrivacyMemberStore($this->getDatabase());
	}
}
