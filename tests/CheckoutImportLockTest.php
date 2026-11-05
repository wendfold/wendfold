<?php

/**
 * Wendfold - PR5d checkout columns, import lock, and duplicate-key saves.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\ImportLock;
use Wendfold\Component\Wendfold\Administrator\Helper\ImportRowFreshness;
use Wendfold\Component\Wendfold\Administrator\Helper\RecordCheckout;
use Wendfold\Component\Wendfold\Administrator\Helper\SaveDuplicateKey;

if (!\class_exists(\Joomla\CMS\Language\Text::class, false))
{
	require_once __DIR__ . '/support/ReminderTextStub.php';
}

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/DuplicateKey.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ImportLock.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ImportRowFreshness.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/RecordCheckout.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/SaveDuplicateKey.php';

/**
 * Behaviour locks for checkout schema, edit locks, CSV import concurrency, and saves.
 *
 * @since  1.0.0
 */
final class CheckoutImportLockTest extends TestCase
{
	private const INSTALL_SQL = 'com_wendfold/admin/sql/install.mysql.utf8.sql';

	private const CHECKOUT_TABLES = [
		'#__wendfold_membership_types',
		'#__wendfold_officer_roles',
		'#__wendfold_event_types',
		'#__wendfold_dues_payments',
		'#__wendfold_motorcycles',
	];

	/**
	 * Install SQL defines checked_out and checked_out_time on all five tables.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testInstallSqlHasCheckoutColumnsOnFiveTables(): void
	{
		$sql = $this->read(self::INSTALL_SQL);

		foreach (self::CHECKOUT_TABLES as $table)
		{
			$block = $this->createTableBlock($sql, $table);
			$this->assertNotSame('', $block, $table . ' missing from install SQL');
			$this->assertMatchesRegularExpression(
				'/`checked_out`\s+int\s+unsigned\s+DEFAULT\s+NULL/i',
				$block,
				$table . ' checked_out type/default'
			);
			$this->assertMatchesRegularExpression(
				'/`checked_out_time`\s+datetime\s+NULL\s+DEFAULT\s+NULL/i',
				$block,
				$table . ' checked_out_time type/default'
			);
		}
	}

	/**
	 * Checkout is refused when another user holds the lock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckoutIsRefusedForAnotherUser(): void
	{
		$this->assertTrue(RecordCheckout::isHeldByOther(7, 3));
		$taken = RecordCheckout::take(7, 3, '2026-11-01 12:00:00');
		$this->assertFalse($taken['ok']);
		$this->assertSame(7, $taken['checked_out']);

		$own = RecordCheckout::take(3, 3, '2026-11-01 12:00:00');
		$this->assertTrue($own['ok']);
		$this->assertSame(3, $own['checked_out']);
		$this->assertSame('2026-11-01 12:00:00', $own['checked_out_time']);

		$free = RecordCheckout::take(0, 9, '2026-11-01 12:00:00');
		$this->assertTrue($free['ok']);
		$this->assertSame(9, $free['checked_out']);
	}

	/**
	 * Check-in clears checked_out and checked_out_time.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckinClearsTheLock(): void
	{
		$cleared = RecordCheckout::release(
			[
				'checked_out'      => 12,
				'checked_out_time' => '2026-11-01 10:00:00',
			]
		);

		$this->assertNull($cleared['checked_out']);
		$this->assertNull($cleared['checked_out_time']);
		$this->assertSame(RecordCheckout::cleared(), $cleared);
	}

	/**
	 * Checkout tables opt into NULL check-in values matching RecordCheckout::cleared().
	 *
	 * Core Table::checkIn writes NULL for both columns only when $_supportNullValue
	 * is true. Each Wendfold table with checkout columns sets that flag, and the
	 * object values after check-in match the cleared helper (both null).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCheckinLeavesCheckoutColumnsNull(): void
	{
		$tables = [
			'LookupTable.php',
			'EventTable.php',
			'MemberTable.php',
			'LocationTable.php',
			'OfficerTable.php',
			'DuespaymentTable.php',
			'MotorcycleTable.php',
		];

		foreach ($tables as $file)
		{
			$source = $this->read('com_wendfold/admin/src/Table/' . $file);
			$this->assertMatchesRegularExpression(
				'/protected\s+\$_supportNullValue\s*=\s*true\s*;/',
				$source,
				$file . ' must set $_supportNullValue so checkIn writes NULL'
			);
			$this->assertMatchesRegularExpression(
				'/function\s+store\s*\(\s*\$updateNulls\s*=\s*true\s*\)/',
				$source,
				$file . ' store must keep updateNulls true by default'
			);
		}

		// Mirrors Table::checkIn object assignment when $_supportNullValue is true.
		$supportNullValue = true;
		$checkedOut       = $supportNullValue ? null : 0;
		$checkedOutTime   = $supportNullValue ? null : '';

		$this->assertNull($checkedOut);
		$this->assertNull($checkedOutTime);
		$this->assertSame(
			RecordCheckout::cleared(),
			[
				'checked_out'      => $checkedOut,
				'checked_out_time' => $checkedOutTime,
			]
		);
	}

	/**
	 * A second GET_LOCK(..., 0) is refused while the first holder keeps the lock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testImportLockRefusesASecondImport(): void
	{
		$db = new ImportLockDatabaseDouble('jos_');

		$this->assertSame('jos_wendfold_import', ImportLock::name($db));
		$this->assertTrue(ImportLock::acquire($db));
		$this->assertFalse(ImportLock::acquire($db));
		ImportLock::release($db);
		$this->assertTrue(ImportLock::acquire($db));
		ImportLock::release($db);

		$source = $this->read('com_wendfold/admin/src/Model/SyncModel.php');
		$this->assertStringContainsString('ImportLock::acquire', $source);
		$this->assertStringContainsString('ImportLock::release', $source);
		$this->assertStringContainsString('COM_WENDFOLD_SYNC_IMPORT_ALREADY_RUNNING', $source);
		$this->assertStringContainsString('finally', $source);
	}

	/**
	 * Rows whose modified stamp changed since the preview are skipped.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRowsModifiedSincePreviewAreSkippedAndReported(): void
	{
		$this->assertFalse(
			ImportRowFreshness::changedSincePreview('2026-01-01 00:00:00', '2026-01-01 00:00:00')
		);
		$this->assertTrue(
			ImportRowFreshness::changedSincePreview('2026-01-01 00:00:00', '2026-01-02 00:00:00')
		);
		$this->assertTrue(
			ImportRowFreshness::changedSincePreview(null, '2026-01-02 00:00:00')
		);

		$source = $this->read('com_wendfold/admin/src/Model/SyncModel.php');
		$this->assertStringContainsString('ImportRowFreshness::changedSincePreview', $source);
		$this->assertStringContainsString('COM_WENDFOLD_SYNC_WARN_MODIFIED_SINCE_PREVIEW', $source);
		$this->assertStringContainsString('FOR UPDATE', $source);
		$this->assertStringContainsString('lockCsvProfileForUpdate', $source);
	}

	/**
	 * Dues duplicate keys map to a friendly language key.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDuplicateKeyMappingsForDuesAndMemberNumber(): void
	{
		$this->assertTrue(DuplicateKey::is(new \RuntimeException('Duplicate entry', 1062)));

		$mappedDues = SaveDuplicateKey::remap(
			'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry',
			'COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS'
		);
		$this->assertSame('COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS', $mappedDues);

		$this->assertNull(SaveDuplicateKey::remap('deadlock', 'COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS'));

		$duesSource = $this->read('com_wendfold/admin/src/Model/DuespaymentModel.php');
		$this->assertStringContainsString('SaveDuplicateKey', $duesSource);
		$this->assertStringContainsString('COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS', $duesSource);

		$syncSource = $this->read('com_wendfold/admin/src/Model/SyncModel.php');
		$this->assertStringContainsString('COM_WENDFOLD_SYNC_ERR_DUPLICATE_KEY', $syncSource);
		$this->assertStringContainsString('DuplicateKey::is', $syncSource);

		$ini = $this->read('com_wendfold/admin/language/en-GB/com_wendfold.ini');
		$this->assertStringContainsString(
			'COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS="A payment for this member and year already exists"',
			$ini
		);
		$this->assertStringContainsString(
			'COM_WENDFOLD_SYNC_IMPORT_ALREADY_RUNNING="An import is already running"',
			$ini
		);
	}

	/**
	 * Member save maps duplicate keys to the conflicting field, never the wrong one.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMemberDuplicateKeyMapsToTheConflictingField(): void
	{
		$numberError = "Duplicate entry 'ABC' for key 'idx_member_number'";
		$userError = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '9' for key 'idx_user_id'";
		$otherError = "Duplicate entry 'x' for key 'idx_something_else'";
		$bareError = 'Duplicate entry \'x\'';

		$this->assertSame('idx_member_number', DuplicateKey::constraintName($numberError));
		$this->assertSame('idx_user_id', DuplicateKey::constraintName($userError));
		$this->assertSame(
			'idx_user_id',
			DuplicateKey::constraintName("Duplicate entry '9' for key 'profiles.idx_user_id'")
		);

		$this->assertSame(
			'COM_WENDFOLD_ERROR_MEMBER_NUMBER_EXISTS',
			SaveDuplicateKey::memberLangKey($numberError)
		);
		$this->assertSame(
			'COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS',
			SaveDuplicateKey::memberLangKey($userError)
		);
		$this->assertSame(
			'COM_WENDFOLD_ERROR_MEMBER_DUPLICATE',
			SaveDuplicateKey::memberLangKey($otherError)
		);
		$this->assertSame(
			'COM_WENDFOLD_ERROR_MEMBER_DUPLICATE',
			SaveDuplicateKey::memberLangKey($bareError)
		);
		$this->assertNull(SaveDuplicateKey::memberLangKey('deadlock'));

		$wrappedUser = new \RuntimeException('wrapper', 0, new \RuntimeException($userError, 1062));
		$this->assertSame(
			'COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS',
			SaveDuplicateKey::memberLangKey($wrappedUser)
		);

		// Never the wrong field for a named clash.
		$this->assertNotSame(
			'COM_WENDFOLD_ERROR_MEMBER_NUMBER_EXISTS',
			SaveDuplicateKey::memberLangKey($userError)
		);
		$this->assertNotSame(
			'COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS',
			SaveDuplicateKey::memberLangKey($numberError)
		);
		$this->assertNotSame(
			'COM_WENDFOLD_ERROR_MEMBER_NUMBER_EXISTS',
			SaveDuplicateKey::memberLangKey($otherError)
		);
		$this->assertNotSame(
			'COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS',
			SaveDuplicateKey::memberLangKey($otherError)
		);

		$memberSource = $this->read('com_wendfold/admin/src/Model/MemberModel.php');
		$ini = $this->read('com_wendfold/admin/language/en-GB/com_wendfold.ini');
		$this->assertStringContainsString('SaveDuplicateKey::memberLangKey', $memberSource);
		$this->assertStringContainsString('COM_WENDFOLD_ERROR_MEMBER_USER_EXISTS', $ini);
		$this->assertStringContainsString('COM_WENDFOLD_ERROR_MEMBER_DUPLICATE', $ini);
		$this->assertStringNotContainsString('setMemberNumberDuplicateError', $memberSource);
	}

	/**
	 * Stale chapter-switch candidates are omitted from the sync log details.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testStaleChapterSwitchIsAbsentFromLoggedFlaggedDetails(): void
	{
		$writes = [
			[
				'mismatch' => true,
				'stale'    => true,
				'row'      => ['member_id' => '100', 'chapter' => '99'],
			],
			[
				'mismatch' => true,
				'stale'    => false,
				'row'      => ['member_id' => '200', 'chapter' => '88'],
			],
			[
				'mismatch' => false,
				'stale'    => false,
				'row'      => ['member_id' => '300', 'chapter' => '77'],
			],
		];

		$flaggedApplied = 0;
		$flaggedDetailsApplied = [];

		foreach ($writes as $write)
		{
			if ($write['stale'])
			{
				continue;
			}

			if (!empty($write['mismatch']))
			{
				$flaggedApplied++;
				$flaggedDetailsApplied[] = 'Member ' . $write['row']['member_id']
					. ' (chapter ' . $write['row']['chapter'] . ')';
			}
		}

		$this->assertSame(1, $flaggedApplied);
		$this->assertSame($flaggedApplied, \count($flaggedDetailsApplied));
		$this->assertSame(['Member 200 (chapter 88)'], $flaggedDetailsApplied);
		$this->assertNotContains('Member 100 (chapter 99)', $flaggedDetailsApplied);

		$source = $this->read('com_wendfold/admin/src/Model/SyncModel.php');
		$this->assertStringContainsString('$flaggedDetailsApplied', $source);
		$this->assertStringContainsString(
			'insertCsvSyncLog($file, $stats, $flaggedDetailsApplied, $preview[\'log_notes\'])',
			$source
		);
		$this->assertStringNotContainsString(
			"insertCsvSyncLog(\$file, \$stats, \$plan['flagged_details']",
			$source
		);
	}

	/**
	 * List views wire jgrid.checkedout and a Check-in toolbar task.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testListViewsRenderCheckedOutAndCheckinToolbar(): void
	{
		$templates = [
			'com_wendfold/admin/tmpl/events/default.php' => 'events.',
			'com_wendfold/admin/tmpl/members/default.php' => 'members.',
			'com_wendfold/admin/tmpl/locations/default.php' => 'locations.',
			'com_wendfold/admin/tmpl/officers/default.php' => 'officers.',
			'com_wendfold/admin/tmpl/dues/default.php' => 'dues.',
			'com_wendfold/admin/tmpl/_includes/lookup_list.php' => 'listTask',
		];

		foreach ($templates as $path => $needle)
		{
			$source = $this->read($path);
			$this->assertStringContainsString('checkedOutIcon', $source, $path);
			$this->assertStringContainsString($needle, $source, $path);
		}

		$views = [
			'Events' => 'events.checkin',
			'Members' => 'members.checkin',
			'Locations' => 'locations.checkin',
			'Officers' => 'officers.checkin',
			'Dues' => 'dues.checkin',
			'Membershiptypes' => 'membershiptypes.checkin',
			'Officerroles' => 'officerroles.checkin',
			'Eventtypes' => 'eventtypes.checkin',
			'Metricdefinitions' => 'metricdefinitions.checkin',
			'Awarddefinitions' => 'awarddefinitions.checkin',
		];

		foreach ($views as $view => $task)
		{
			$source = $this->read('com_wendfold/admin/src/View/' . $view . '/HtmlView.php');
			$this->assertStringContainsString('CheckoutAcl::addCheckinButton', $source, $view);
			$this->assertStringContainsString($task, $source, $view);
		}

		$controller = $this->read('com_wendfold/admin/src/Controller/ListController.php');
		$this->assertStringContainsString('function checkin', $controller);
		$this->assertStringContainsString('checkToken', $controller);
	}

	/**
	 * @param   string  $sql    Full install SQL.
	 * @param   string  $table  Quoted table name including #__.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function createTableBlock(string $sql, string $table): string
	{
		$pattern = '/CREATE TABLE IF NOT EXISTS `' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*ENGINE=/is';

		if (preg_match($pattern, $sql, $match) !== 1)
		{
			return '';
		}

		return $match[1];
	}

	/**
	 * @param   string  $relative  Path from the repository root.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function read(string $relative): string
	{
		$path = dirname(__DIR__) . '/' . $relative;
		$contents = file_get_contents($path);
		$this->assertNotFalse($contents, $relative);

		return (string) $contents;
	}
}

/**
 * Database double for ImportLock GET_LOCK / RELEASE_LOCK.
 *
 * @since  1.0.0
 */
final class ImportLockDatabaseDouble implements DatabaseInterface
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $prefix;

	/**
	 * @var    string|null
	 * @since  1.0.0
	 */
	private ?string $holder = null;

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	private string $lastSql = '';

	/**
	 * @param   string  $prefix  Table prefix.
	 *
	 * @since   1.0.0
	 */
	public function __construct(string $prefix)
	{
		$this->prefix = $prefix;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getPrefix()
	{
		return $this->prefix;
	}

	/**
	 * @param   mixed    $text    Value to quote.
	 * @param   boolean  $escape  Unused.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quote($text, $escape = true)
	{
		return "'" . str_replace("'", "''", (string) $text) . "'";
	}

	/**
	 * @param   mixed    $query   SQL string.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		$this->lastSql = (string) $query;

		return $this;
	}

	/**
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		if (preg_match("/GET_LOCK\('([^']+)'/", $this->lastSql, $match) === 1)
		{
			$name = $match[1];

			if ($this->holder !== null)
			{
				return 0;
			}

			$this->holder = $name;

			return 1;
		}

		return null;
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
		if (preg_match("/RELEASE_LOCK\('([^']+)'/", $this->lastSql, $match) === 1)
		{
			if ($this->holder === $match[1])
			{
				$this->holder = null;
			}
		}

		return true;
	}

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		return new \stdClass;
	}

	/**
	 * Create a new query object.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function createQuery()
	{
		return $this->getQuery(true);
	}

	/**
	 * @param   string  $name  Identifier.
	 * @param   string  $as    Optional alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		return '`' . str_replace('`', '``', (string) $name) . '`';
	}

	/**
	 * @param   string  $key  Unused row key.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null)
	{
		return [];
	}
}
