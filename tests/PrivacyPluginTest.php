<?php

/**
 * Wendfold - privacy plugin export, refusal, anonymization, and idempotency.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\RenewalReminderOptOut;
use Wendfold\Plugin\Privacy\Wendfold\Service\PrivacyDataHandler;
use Wendfold\Plugin\Privacy\Wendfold\Service\SyncLogText;
use Wendfold\Tests\Support\MemoryPrivacyMemberStore;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RenewalReminderOptOut.php';
require_once dirname(__DIR__) . '/plg_privacy_wendfold/src/Service/PrivacyMemberStoreInterface.php';
require_once dirname(__DIR__) . '/plg_privacy_wendfold/src/Service/SyncLogText.php';
require_once dirname(__DIR__) . '/plg_privacy_wendfold/src/Service/PrivacyDataHandler.php';
require_once __DIR__ . '/support/MemoryPrivacyMemberStore.php';

/**
 * PrivacyDataHandler behaviour for com_privacy integration.
 *
 * @since  1.0.0
 */
final class PrivacyPluginTest extends TestCase
{
	/**
	 * Export includes every personal domain seeded for the member.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testExportIncludesPersonalFieldsAcrossTables(): void
	{
		$store = $this->seededStore();
		$handler = new PrivacyDataHandler($store);

		$domains = $handler->export(42, 'Ada Lovelace');

		$this->assertArrayHasKey('wendfold_reminder_optout', $domains);
		$this->assertSame('1', $domains['wendfold_reminder_optout'][0]['wendfold_renewal_reminders_optout']);

		$this->assertSame('Ada', $domains['wendfold_profiles'][0]['preferred_name']);
		$this->assertSame('555-0100', $domains['wendfold_profiles'][0]['emergency_phone']);
		$this->assertSame('1HD1VINTEST000001', $domains['wendfold_motorcycles'][0]['vin']);
		$this->assertSame('seat preference', $domains['wendfold_attendance'][0]['notes']);
		$this->assertSame(1, (int) $domains['wendfold_rsvps'][0]['response']);
		$this->assertSame('front left', $domains['wendfold_event_group_members'][0]['notes']);
		$this->assertSame('can haul ice', $domains['wendfold_event_volunteers'][0]['notes']);
		$this->assertSame('arrives Friday', $domains['wendfold_event_trip_participants'][0]['arrival_notes']);
		$this->assertSame('Coast loop', $domains['wendfold_activities'][0]['title']);
		$this->assertSame('manual note', $domains['wendfold_metric_entries'][0]['note']);
		$this->assertSame('presented at banquet', $domains['wendfold_member_awards'][0]['notes']);
		$this->assertSame('cash', $domains['wendfold_dues_payments'][0]['payment_method']);
		$this->assertSame('Director', $domains['wendfold_officers'][0]['notes']);
		$this->assertSame(7, (int) $domains['wendfold_event_roles'][0]['id']);
		$this->assertSame(3, (int) $domains['wendfold_event_group_roles'][0]['id']);
		$this->assertStringContainsString('Ada Lovelace', (string) $domains['wendfold_sync_log'][0]['log_detail']);
		$this->assertSame('renewal-60', $domains['wendfold_reminder_log'][0]['reminder_key']);
	}

	/**
	 * Current officers cannot be removed.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovalRefusedForCurrentOfficer(): void
	{
		$store = $this->seededStore();
		$handler = new PrivacyDataHandler($store);

		$this->assertSame(
			PrivacyDataHandler::REFUSAL_CURRENT_OFFICER,
			$handler->removalBlockReason(42)
		);

		$store->officers[0]['is_current'] = 0;

		$this->assertNull($handler->removalBlockReason(42));
	}

	/**
	 * Removal anonymizes personal fields and keeps aggregate links.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovalAnonymizesPersonalFieldsAndKeepsAggregates(): void
	{
		$store = $this->seededStore();
		$store->officers[0]['is_current'] = 0;
		$handler = new PrivacyDataHandler($store);
		$root = sys_get_temp_dir() . '/wendfold-privacy-' . bin2hex(random_bytes(4));
		$photoDir = $root . '/images/wendfold/profiles';
		mkdir($photoDir, 0777, true);
		$photoFile = $photoDir . '/9.jpg';
		file_put_contents($photoFile, 'fake-image');
		$mediaPhoto = $root . '/images/profiles/ada.jpg';
		mkdir(\dirname($mediaPhoto), 0777, true);
		file_put_contents($mediaPhoto, 'media-image');
		$store->profiles[0]['photo'] = 'images/profiles/ada.jpg';

		$otherAttendanceNotes = $store->attendance[1]['notes'];
		$otherDuesAmount = $store->duesPayments[1]['amount'];

		$handler->remove(42, $root, ['Ada Lovelace']);

		$profile = $store->profiles[0];
		$this->assertNull($profile['user_id']);
		$this->assertNull($profile['member_number']);
		$this->assertSame('', $profile['preferred_name']);
		$this->assertSame('', $profile['emergency_name']);
		$this->assertSame('', $profile['emergency_phone']);
		$this->assertSame('', $profile['photo']);
		$this->assertSame(0, (int) $profile['published']);
		$this->assertSame(3, (int) $profile['member_status']);
		$this->assertNull($store->profiles[1]['sponsor_id']);

		$this->assertSame('', $store->motorcycles[0]['vin']);
		$this->assertSame('', $store->motorcycles[0]['nickname']);
		$this->assertSame('', $store->attendance[0]['notes']);
		$this->assertSame(9, (int) $store->attendance[0]['profile_id']);
		$this->assertSame($otherAttendanceNotes, $store->attendance[1]['notes']);
		$this->assertSame([], $store->rsvps);
		$this->assertSame('', $store->eventVolunteers[0]['notes']);
		$this->assertSame(9, (int) $store->eventVolunteers[0]['profile_id']);
		$this->assertSame('', $store->tripParticipants[0]['arrival_notes']);
		$this->assertSame('', $store->activities[0]['title']);
		$this->assertNull($store->activities[0]['motorcycle_id']);
		$this->assertSame('', $store->metricEntries[0]['note']);
		$this->assertSame(12.5, (float) $store->metricEntries[0]['value']);
		$this->assertSame(9, (int) $store->metricEntries[0]['profile_id']);
		$this->assertSame('', $store->memberAwards[0]['notes']);
		$this->assertSame(9, (int) $store->memberAwards[0]['profile_id']);
		$this->assertSame('', $store->duesPayments[0]['receipt_number']);
		$this->assertSame(25.00, (float) $store->duesPayments[0]['amount']);
		$this->assertSame($otherDuesAmount, $store->duesPayments[1]['amount']);
		$this->assertSame(0, (int) $store->officers[0]['is_current']);
		$this->assertSame('', $store->officers[0]['notes']);
		$this->assertNull($store->events[0]['road_captain_id']);
		$this->assertNull($store->eventGroups[0]['sweep_id']);
		$this->assertStringContainsString(PrivacyDataHandler::REDACTED, (string) $store->syncLog[0]['log_detail']);
		$this->assertStringNotContainsString('Ada Lovelace', (string) $store->syncLog[0]['log_detail']);
		$this->assertSame(0, (int) $store->syncLog[0]['performed_by']);
		$this->assertFalse(
			RenewalReminderOptOut::isOptedOutFromParams($store->users[42]['params'])
		);
		$this->assertFileDoesNotExist($photoFile);
		$this->assertFileDoesNotExist($mediaPhoto);
		$this->assertSame(1, $store->transactions);

		$this->cleanupTree($root);
	}

	/**
	 * A second removal after anonymization is a no-op and still succeeds.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovalIsIdempotent(): void
	{
		$store = $this->seededStore();
		$store->officers[0]['is_current'] = 0;
		$handler = new PrivacyDataHandler($store);
		$root = sys_get_temp_dir() . '/wendfold-privacy-idemp-' . bin2hex(random_bytes(4));
		mkdir($root, 0777, true);

		$handler->remove(42, $root, ['Ada Lovelace']);
		$afterFirst = $store->profiles[0];
		$transactions = $store->transactions;

		$handler->remove(42, $root, ['Ada Lovelace']);

		$this->assertSame($afterFirst, $store->profiles[0]);
		$this->assertSame($transactions, $store->transactions);
		$this->assertNull($handler->removalBlockReason(42));

		$this->cleanupTree($root);
	}

	/**
	 * Package and plugin manifests mention the privacy plugin.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPackageManifestListsPrivacyPlugin(): void
	{
		$root = dirname(__DIR__);
		$package = file_get_contents($root . '/pkg_wendfold/pkg_wendfold.xml');
		$build = file_get_contents($root . '/build/build_release.py');
		$plugin = file_get_contents($root . '/plg_privacy_wendfold/wendfold.xml');
		$extension = file_get_contents($root . '/plg_privacy_wendfold/src/Extension/Wendfold.php');
		$ini = file_get_contents($root . '/plg_privacy_wendfold/language/en-GB/plg_privacy_wendfold.ini');
		$sysIni = file_get_contents($root . '/plg_privacy_wendfold/language/en-GB/plg_privacy_wendfold.sys.ini');

		$this->assertNotFalse($package);
		$this->assertNotFalse($build);
		$this->assertNotFalse($plugin);
		$this->assertNotFalse($extension);
		$this->assertNotFalse($ini);
		$this->assertNotFalse($sysIni);

		$this->assertStringContainsString('group="privacy"', $package);
		$this->assertStringContainsString('plg_privacy_wendfold', $package);
		$this->assertStringContainsString("'plg_privacy_wendfold'", $build);
		$this->assertStringContainsString('privacy_plugin', $build);
		$this->assertStringContainsString('group="privacy"', $plugin);
		$this->assertMatchesRegularExpression('/<name>\s*PLG_PRIVACY_WENDFOLD\s*<\/name>/', $plugin);
		$this->assertStringContainsString('en-GB/plg_privacy_wendfold.ini', $plugin);
		$this->assertStringContainsString('en-GB/plg_privacy_wendfold.sys.ini', $plugin);
		$this->assertMatchesRegularExpression('/^PLG_PRIVACY_WENDFOLD=/m', $sysIni);
		$this->assertMatchesRegularExpression('/^PLG_PRIVACY_WENDFOLD=/m', $ini);
		$this->assertStringContainsString('PLG_PRIVACY_WENDFOLD_ERROR_CURRENT_OFFICER', $ini);
		$this->assertStringContainsString('PLG_PRIVACY_WENDFOLD_XML_DESCRIPTION', $sysIni);
		$this->assertStringContainsString('onPrivacyExportRequest', $extension);
		$this->assertStringContainsString('onPrivacyCanRemoveData', $extension);
		$this->assertStringContainsString('onPrivacyRemoveData', $extension);
		$this->assertStringContainsString("createDomain(\$name, \$name . '_data')", $extension);
		$this->assertStringNotContainsString("'wendfold_' . \$name", $extension);
		$this->assertStringNotContainsString('Factory::getDbo', $extension);
		$this->assertStringNotContainsString('Factory::getMailer', $extension);
		$this->assertStringNotContainsString('$app->input', $extension);

		// Brand guard forbids the contiguous legacy token in tracked files.
		$legacyToken = \chr(72) . \chr(79) . \chr(71);
		$this->assertStringNotContainsString($legacyToken, $extension);
		$this->assertStringNotContainsString($legacyToken, $plugin);
		$this->assertStringNotContainsString($legacyToken, $ini);
		$this->assertStringNotContainsString($legacyToken, $sysIni);
	}

	/**
	 * Export for member A excludes sync-log rows that only share substrings with A.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testExportSyncLogExcludesSubstringMemberMatches(): void
	{
		$store = $this->substringCollisionStore();
		$handler = new PrivacyDataHandler($store);

		$domains = $handler->export(10, 'Target');
		$syncIds = array_map(static fn (array $row): int => (int) $row['id'], $domains['wendfold_sync_log']);

		$this->assertContains(1, $syncIds);
		$this->assertNotContains(2, $syncIds);
		$this->assertNotContains(3, $syncIds);
	}

	/**
	 * Removing member A leaves member B sync-log rows byte-identical.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRemovalLeavesOtherMemberSyncLogRowsUnchanged(): void
	{
		$store = $this->substringCollisionStore();
		$store->officers = [];
		$handler = new PrivacyDataHandler($store);
		$beforeBExact = $store->syncLog[1];
		$beforeBSurname = $store->syncLog[2];
		$root = sys_get_temp_dir() . '/wendfold-privacy-sync-' . bin2hex(random_bytes(4));
		mkdir($root, 0777, true);

		$handler->remove(10, $root, ['Target']);

		$this->assertSame($beforeBExact, $store->syncLog[1]);
		$this->assertSame($beforeBSurname, $store->syncLog[2]);
		$this->assertStringContainsString(PrivacyDataHandler::REDACTED, (string) $store->syncLog[0]['log_detail']);
		$this->assertStringNotContainsString('A122', (string) $store->syncLog[0]['log_detail']);
		$this->assertSame(0, (int) $store->syncLog[0]['performed_by']);

		$handler->remove(10, $root, ['Target']);
		$this->assertSame($beforeBExact, $store->syncLog[1]);
		$this->assertSame($beforeBSurname, $store->syncLog[2]);

		$this->cleanupTree($root);
	}

	/**
	 * Exact-token matcher rejects substring overlaps and accepts bounded tokens.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSyncLogTextExactTokenBoundaries(): void
	{
		$this->assertTrue(SyncLogText::containsExactToken('Imported Target (Member # A122)', 'A122'));
		$this->assertTrue(SyncLogText::containsExactToken('Imported Target (Member # A122)', 'Target'));
		$this->assertFalse(SyncLogText::containsExactToken('Imported Targetson (Member # A1220)', 'A122'));
		$this->assertFalse(SyncLogText::containsExactToken('Imported Targetson (Member # A1220)', 'Target'));

		$redacted = SyncLogText::replaceExactTokens(
			'Imported Targetson (Member # A1220)',
			['A122', 'Target'],
			PrivacyDataHandler::REDACTED
		);
		$this->assertSame('Imported Targetson (Member # A1220)', $redacted);

		$own = SyncLogText::replaceExactTokens(
			'Imported Target (Member # A122)',
			['A122', 'Target'],
			PrivacyDataHandler::REDACTED
		);
		$this->assertSame(
			'Imported ' . PrivacyDataHandler::REDACTED . ' (Member # ' . PrivacyDataHandler::REDACTED . ')',
			$own
		);
	}

	/**
	 * Shared first names do not attribute another member's sync-log rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSharedPreferredNameDoesNotAttributeSyncLog(): void
	{
		$store = $this->sharedNameStore();
		$handler = new PrivacyDataHandler($store);

		$domains = $handler->export(10, 'John Smith');
		$syncIds = array_map(static fn (array $row): int => (int) $row['id'], $domains['wendfold_sync_log']);

		$this->assertContains(1, $syncIds);
		$this->assertNotContains(2, $syncIds);
		$this->assertNotContains(3, $syncIds);

		$beforeB = $store->syncLog[1];
		$beforeOrphan = $store->syncLog[2];
		$root = sys_get_temp_dir() . '/wendfold-privacy-name-' . bin2hex(random_bytes(4));
		mkdir($root, 0777, true);

		$handler->remove(10, $root, ['John Smith']);

		$this->assertSame($beforeB, $store->syncLog[1]);
		$this->assertSame($beforeOrphan, $store->syncLog[2]);
		$this->assertStringContainsString(PrivacyDataHandler::REDACTED, (string) $store->syncLog[0]['log_detail']);
		$this->assertStringNotContainsString('M100', (string) $store->syncLog[0]['log_detail']);
		$this->assertStringNotContainsString('John', (string) $store->syncLog[0]['log_detail']);

		$afterFirst = $store->syncLog;
		$handler->remove(10, $root, ['John Smith']);
		$this->assertSame($afterFirst, $store->syncLog);

		$this->cleanupTree($root);
	}

	/**
	 * An emergency contact name matching another member does not attribute rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testEmergencyContactNameDoesNotAttributeSyncLog(): void
	{
		$store = $this->sharedNameStore();
		$store->profiles[0]['emergency_name'] = 'Jane Roe';
		$store->syncLog[] = [
			'id' => 4,
			'sync_type' => 1,
			'sync_date' => '2026-04-02 09:00:00',
			'performed_by' => 20,
			'log_detail' => 'Imported Jane Roe (Member # M200)',
		];
		$store->syncLog[] = [
			'id' => 5,
			'sync_type' => 2,
			'sync_date' => '2026-04-02 09:05:00',
			'performed_by' => 99,
			'log_detail' => 'Called emergency contact Jane Roe',
		];

		$handler = new PrivacyDataHandler($store);
		$domains = $handler->export(10, 'John Smith');
		$syncIds = array_map(static fn (array $row): int => (int) $row['id'], $domains['wendfold_sync_log']);

		$this->assertContains(1, $syncIds);
		$this->assertNotContains(4, $syncIds);
		$this->assertNotContains(5, $syncIds);

		$beforeJaneMember = $store->syncLog[3];
		$beforeJaneOrphan = $store->syncLog[4];
		$root = sys_get_temp_dir() . '/wendfold-privacy-emerg-' . bin2hex(random_bytes(4));
		mkdir($root, 0777, true);

		$handler->remove(10, $root, ['John Smith']);

		$this->assertSame($beforeJaneMember, $store->syncLog[3]);
		$this->assertSame($beforeJaneOrphan, $store->syncLog[4]);

		$this->cleanupTree($root);
	}

	/**
	 * Privacy plugin adds no schema SQL; component SQL stays versioned updates only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPrivacyPluginAddsNoSchemaSql(): void
	{
		$root = dirname(__DIR__);
		$pluginSql = $root . '/plg_privacy_wendfold/sql';
		$updates = $root . '/com_wendfold/admin/sql/updates/mysql';
		$install = $root . '/com_wendfold/admin/sql/install.mysql.utf8.sql';
		$uninstall = $root . '/com_wendfold/admin/sql/uninstall.mysql.utf8.sql';

		$this->assertDirectoryDoesNotExist($pluginSql);
		$this->assertFileExists($install);
		$this->assertFileExists($uninstall);
		$this->assertDirectoryExists($updates);

		$updateFiles = array_values(
			array_filter(
				scandir($updates) ?: [],
				static fn (string $name): bool => str_ends_with($name, '.sql')
			)
		);

		$this->assertNotSame([], $updateFiles);

		foreach ($updateFiles as $name)
		{
			$this->assertMatchesRegularExpression(
				'/^\d+\.\d+\.\d+\.sql$/',
				$name,
				'Update SQL must be versioned as X.Y.Z.sql'
			);
		}
	}

	/**
	 * Two members share preferred_name "John" with distinct member numbers.
	 *
	 * @return  MemoryPrivacyMemberStore
	 *
	 * @since   1.0.0
	 */
	private function sharedNameStore(): MemoryPrivacyMemberStore
	{
		$store = new MemoryPrivacyMemberStore;
		$store->users[10] = ['id' => 10, 'params' => '{}'];
		$store->users[20] = ['id' => 20, 'params' => '{}'];
		$store->profiles = [
			[
				'id' => 1,
				'user_id' => 10,
				'member_number' => 'M100',
				'preferred_name' => 'John',
				'emergency_name' => '',
				'emergency_phone' => '',
				'emergency_relation' => '',
				'shirt_size' => '',
				'photo' => '',
				'notes' => null,
				'chapter_on_file' => '',
				'waiver_version' => '',
				'sponsor_id' => null,
				'is_road_captain' => 0,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => null,
				'reminder_30_sent' => null,
			],
			[
				'id' => 2,
				'user_id' => 20,
				'member_number' => 'M200',
				'preferred_name' => 'John',
				'emergency_name' => '',
				'emergency_phone' => '',
				'emergency_relation' => '',
				'shirt_size' => '',
				'photo' => '',
				'notes' => 'keep',
				'chapter_on_file' => '',
				'waiver_version' => '',
				'sponsor_id' => null,
				'is_road_captain' => 0,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => null,
				'reminder_30_sent' => null,
			],
		];
		$store->syncLog = [
			[
				'id' => 1,
				'sync_type' => 1,
				'sync_date' => '2026-04-02 08:00:00',
				'performed_by' => 10,
				'log_detail' => 'Imported John (Member # M100)',
			],
			[
				'id' => 2,
				'sync_type' => 1,
				'sync_date' => '2026-04-02 08:05:00',
				'performed_by' => 20,
				'log_detail' => 'Imported John (Member # M200)',
			],
			[
				'id' => 3,
				'sync_type' => 2,
				'sync_date' => '2026-04-02 08:10:00',
				'performed_by' => 99,
				'log_detail' => 'Note about John without a member number',
			],
		];

		return $store;
	}

	/**
	 * Member A tokens are substrings of member B's member number and surname.
	 *
	 * @return  MemoryPrivacyMemberStore
	 *
	 * @since   1.0.0
	 */
	private function substringCollisionStore(): MemoryPrivacyMemberStore
	{
		$store = new MemoryPrivacyMemberStore;
		$store->users[10] = ['id' => 10, 'params' => '{}'];
		$store->users[20] = ['id' => 20, 'params' => '{}'];
		$store->profiles = [
			[
				'id' => 1,
				'user_id' => 10,
				'member_number' => 'A122',
				'preferred_name' => 'Target',
				'emergency_name' => '',
				'emergency_phone' => '',
				'emergency_relation' => '',
				'shirt_size' => '',
				'photo' => '',
				'notes' => null,
				'chapter_on_file' => '',
				'waiver_version' => '',
				'sponsor_id' => null,
				'is_road_captain' => 0,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => null,
				'reminder_30_sent' => null,
			],
			[
				'id' => 2,
				'user_id' => 20,
				'member_number' => 'A1220',
				'preferred_name' => 'Targetson',
				'emergency_name' => '',
				'emergency_phone' => '',
				'emergency_relation' => '',
				'shirt_size' => '',
				'photo' => '',
				'notes' => 'keep',
				'chapter_on_file' => '',
				'waiver_version' => '',
				'sponsor_id' => null,
				'is_road_captain' => 0,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => null,
				'reminder_30_sent' => null,
			],
		];
		$store->syncLog = [
			[
				'id' => 1,
				'sync_type' => 1,
				'sync_date' => '2026-04-02 08:00:00',
				'performed_by' => 10,
				'log_detail' => 'Imported Target (Member # A122)',
			],
			[
				'id' => 2,
				'sync_type' => 1,
				'sync_date' => '2026-04-02 08:05:00',
				'performed_by' => 20,
				'log_detail' => 'Imported Targetson (Member # A1220)',
			],
			[
				'id' => 3,
				'sync_type' => 2,
				'sync_date' => '2026-04-02 08:10:00',
				'performed_by' => 99,
				'log_detail' => 'Reviewed Targetson transfer for A1220',
			],
		];

		return $store;
	}

	/**
	 * Seed a member with rows in every personal-data table the plugin covers.
	 *
	 * @return  MemoryPrivacyMemberStore
	 *
	 * @since   1.0.0
	 */
	private function seededStore(): MemoryPrivacyMemberStore
	{
		$store = new MemoryPrivacyMemberStore;
		$store->users[42] = [
			'id' => 42,
			'params' => json_encode([RenewalReminderOptOut::PARAM_KEY => '1']),
		];
		$store->profiles = [
			[
				'id' => 9,
				'user_id' => 42,
				'member_number' => '1001',
				'preferred_name' => 'Ada',
				'emergency_name' => 'Charles',
				'emergency_phone' => '555-0100',
				'emergency_relation' => 'spouse',
				'shirt_size' => 'M',
				'photo' => 'images/wendfold/profiles/9.jpg',
				'notes' => 'allergy notes',
				'chapter_on_file' => 'CA1',
				'waiver_version' => '2026.1',
				'sponsor_id' => null,
				'is_road_captain' => 1,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => '2026-01-01',
				'reminder_30_sent' => null,
			],
			[
				'id' => 10,
				'user_id' => 99,
				'member_number' => '2002',
				'preferred_name' => 'Other',
				'emergency_name' => '',
				'emergency_phone' => '',
				'emergency_relation' => '',
				'shirt_size' => '',
				'photo' => '',
				'notes' => 'keep me',
				'chapter_on_file' => '',
				'waiver_version' => '',
				'sponsor_id' => 9,
				'is_road_captain' => 0,
				'is_road_crew' => 0,
				'published' => 1,
				'member_status' => 1,
				'reminder_60_sent' => null,
				'reminder_30_sent' => null,
			],
		];
		$store->motorcycles = [
			[
				'id' => 1,
				'profile_id' => 9,
				'make' => 'Example',
				'model' => 'Tour',
				'color' => 'black',
				'nickname' => 'Bessie',
				'vin' => '1HD1VINTEST000001',
				'notes' => 'garage code',
				'model_year' => 2020,
				'year' => 2020,
			],
		];
		$store->officers = [
			[
				'id' => 1,
				'profile_id' => 9,
				'role_id' => 1,
				'is_current' => 1,
				'published' => 1,
				'notes' => 'Director',
			],
		];
		$store->attendance = [
			[
				'id' => 1,
				'event_id' => 7,
				'profile_id' => 9,
				'check_in_time' => '2026-05-01 18:00:00',
				'check_in_method' => 1,
				'checked_in_by' => 42,
				'notes' => 'seat preference',
			],
			[
				'id' => 2,
				'event_id' => 7,
				'profile_id' => 10,
				'check_in_time' => '2026-05-01 18:05:00',
				'check_in_method' => 1,
				'checked_in_by' => 99,
				'notes' => 'other member note',
			],
		];
		$store->rsvps = [
			[
				'id' => 1,
				'event_id' => 7,
				'profile_id' => 9,
				'response' => 1,
				'guests' => 0,
				'created' => '2026-04-01 12:00:00',
				'modified' => '2026-04-01 12:00:00',
			],
		];
		$store->eventGroupMembers = [
			[
				'id' => 1,
				'event_group_id' => 3,
				'event_id' => 7,
				'profile_id' => 9,
				'assignment_order' => 1,
				'assigned_at' => '2026-05-01 17:00:00',
				'assigned_by' => 42,
				'notes' => 'front left',
			],
		];
		$store->eventVolunteers = [
			[
				'id' => 1,
				'volunteer_role_id' => 5,
				'event_id' => 7,
				'profile_id' => 9,
				'assignment_status' => 2,
				'notes' => 'can haul ice',
			],
		];
		$store->tripParticipants = [
			[
				'id' => 1,
				'event_id' => 7,
				'profile_id' => 9,
				'lodging_status' => 1,
				'lodging_notes' => 'queen bed',
				'arrival_notes' => 'arrives Friday',
				'departure_notes' => 'leaves Sunday',
				'booked_at' => '2026-04-15 10:00:00',
			],
		];
		$store->activities = [
			[
				'id' => 1,
				'profile_id' => 9,
				'activity_date' => '2026-03-01',
				'title' => 'Coast loop',
				'description' => 'Solo ride',
				'motorcycle_id' => 1,
				'start_location' => 'Home',
				'end_location' => 'Pier',
			],
		];
		$store->metricEntries = [
			[
				'id' => 1,
				'profile_id' => 9,
				'metric_id' => 1,
				'entry_date' => '2026-03-01',
				'value' => 12.5,
				'event_id' => null,
				'source_type' => 3,
				'source_id' => null,
				'published' => 1,
				'note' => 'manual note',
			],
		];
		$store->memberAwards = [
			[
				'id' => 1,
				'award_id' => 2,
				'profile_id' => 9,
				'period_key' => '2026',
				'tier' => 1,
				'state' => 1,
				'notes' => 'presented at banquet',
			],
		];
		$store->duesPayments = [
			[
				'id' => 1,
				'profile_id' => 9,
				'period_start' => '2026-01-01',
				'period_end' => '2026-12-31',
				'amount' => 25.00,
				'payment_date' => '2026-01-05',
				'payment_method' => 'cash',
				'receipt_number' => 'R-9',
				'notes' => 'paid at meeting',
				'year' => 2026,
			],
			[
				'id' => 2,
				'profile_id' => 10,
				'period_start' => '2026-01-01',
				'period_end' => '2026-12-31',
				'amount' => 40.00,
				'payment_date' => '2026-01-06',
				'payment_method' => 'card',
				'receipt_number' => 'R-10',
				'notes' => 'other',
				'year' => 2026,
			],
		];
		$store->reminderLog = [
			[
				'id' => 1,
				'profile_id' => 9,
				'reminder_key' => 'renewal-60',
				'reference_date' => '2026-06-01',
				'sent_at' => '2026-04-01 09:00:00',
			],
		];
		$store->events = [
			[
				'id' => 7,
				'title' => 'Spring Ride',
				'event_date' => '2026-05-01',
				'road_captain_id' => 9,
				'sweep_id' => null,
				'volunteer_manager_id' => null,
				'volunteer_backup_id' => null,
				'trip_lead_id' => null,
				'trip_sweep_id' => null,
			],
		];
		$store->eventGroups = [
			[
				'id' => 3,
				'event_id' => 7,
				'title' => 'Group A',
				'road_captain_id' => null,
				'sweep_id' => 9,
			],
		];
		$store->syncLog = [
			[
				'id' => 1,
				'sync_type' => 1,
				'sync_date' => '2026-04-02 08:00:00',
				'performed_by' => 42,
				'log_detail' => 'Imported Ada Lovelace (Member # 1001)',
			],
		];

		return $store;
	}

	/**
	 * @param   string  $path  Directory tree to remove.
	 *
	 * @return  void
	 */
	private function cleanupTree(string $path): void
	{
		if (!is_dir($path))
		{
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $file)
		{
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($path);
	}
}
