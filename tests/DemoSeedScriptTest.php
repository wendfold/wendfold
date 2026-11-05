<?php

/**
 * Wendfold - developer demo seed script layout locks.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The demo seed script stays outside extension folders and outside package ZIPs.
 *
 * @since  1.0.0
 */
final class DemoSeedScriptTest extends TestCase
{
	/**
	 * Script and README live under repo-root dev/, not under any extension.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSeedScriptLivesOutsideExtensionFolders(): void
	{
		$root   = dirname(__DIR__);
		$script = $root . '/dev/seed-demo.php';
		$readme = $root . '/dev/README.md';

		$this->assertFileExists($script);
		$this->assertFileExists($readme);

		$relative = 'dev/seed-demo.php';
		$blocked  = [
			'com_wendfold/',
			'plg_user_wendfold/',
			'plg_task_wendfold/',
			'plg_privacy_wendfold/',
			'pkg_wendfold/',
		];

		foreach ($blocked as $prefix)
		{
			$this->assertStringStartsNotWith($prefix, $relative);
			$this->assertFileDoesNotExist($root . '/' . $prefix . 'seed-demo.php');
			$this->assertFileDoesNotExist($root . '/' . $prefix . 'dev/seed-demo.php');
		}
	}

	/**
	 * seed-demo.php passes php -l.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSeedScriptPassesPhpLint(): void
	{
		$script = dirname(__DIR__) . '/dev/seed-demo.php';
		$pipes  = [];
		$process = proc_open(
			[PHP_BINARY, '-l', $script],
			[
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			],
			$pipes
		);

		$this->assertIsResource($process);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);

		$this->assertSame(0, $exit, (string) $stdout . (string) $stderr);
		$this->assertStringContainsString('No syntax errors detected', (string) $stdout);
	}

	/**
	 * build_release.py only zips extension PACKAGE_SOURCES plus pkg_wendfold.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testBuildPackageSourcesExcludeDevDirectory(): void
	{
		$root  = dirname(__DIR__);
		$build = (string) file_get_contents($root . '/build/build_release.py');

		$this->assertNotSame('', $build);
		$this->assertStringContainsString('PACKAGE_SOURCES = {', $build);
		$this->assertStringContainsString("'com_wendfold'", $build);
		$this->assertStringContainsString("'plg_user_wendfold'", $build);
		$this->assertStringContainsString("'plg_task_wendfold'", $build);
		$this->assertStringContainsString("'plg_privacy_wendfold'", $build);

		$this->assertDoesNotMatchRegularExpression(
			"/PACKAGE_SOURCES\s*=\s*\{[^}]*'dev'/s",
			$build
		);
		$this->assertDoesNotMatchRegularExpression(
			"/REPO_ROOT\s*\/\s*'dev'/",
			$build
		);
		$this->assertStringContainsString(
			"create_zip_from_source(config['source'], zip_path, config['exclude'], date_time)",
			$build
		);
		$this->assertStringContainsString(
			"copy_tree(REPO_ROOT / 'pkg_wendfold', package_stage)",
			$build
		);
		$this->assertStringNotContainsString("REPO_ROOT / 'dev'", $build);
	}

	/**
	 * Script documents CLI usage, tagging, and refuses a missing component.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSeedScriptDocumentsResetAndSiteOptions(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertStringContainsString("--site=", $source);
		$this->assertStringContainsString('--reset', $source);
		$this->assertStringContainsString('--password', $source);
		$this->assertStringContainsString('WENDFOLD_DEMO_SEED', $source);
		$this->assertStringContainsString('com_wendfold is not installed', $source);
		$this->assertStringContainsString('DatabaseInterface', $source);
		$this->assertStringContainsString('quoteName', $source);
		$this->assertStringContainsString('whereIn', $source);
		$this->assertStringNotContainsString('Factory::getDbo', $source);
		$this->assertStringNotContainsString('JFactory', $source);
	}

	/**
	 * Source must not ship a hardcoded demo password constant or literal.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNoHardcodedDemoPasswordInSource(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$readme = (string) file_get_contents(dirname(__DIR__) . '/dev/README.md');

		$this->assertStringNotContainsString('DEMO_PASSWORD', $source);
		$this->assertStringNotContainsString('Demo-Only-ChangeMe', $source);
		$this->assertStringNotContainsString('Demo-Only-ChangeMe', $readme);
		$this->assertDoesNotMatchRegularExpression(
			"/const\s+[A-Z_]*PASSWORD[A-Z_]*\s*=\s*'[^']+'/",
			$source
		);
		$this->assertStringContainsString('hashPassword($this->password)', $source);
		$this->assertStringContainsString("'password:'", $source);
		$this->assertStringContainsString('generatePassword()', $source);
		$this->assertStringContainsString('MIN_PASSWORD_LENGTH', $source);
	}

	/**
	 * --password is hashed for users; short values are rejected with exit 1.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testPasswordOptionHonoredAndShortRejected(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertStringContainsString('hashPassword($this->password)', $source);
		$this->assertStringContainsString('$this->password = $password', $source);
		$this->assertMatchesRegularExpression(
			'/strlen\(\$passwordOption\)\s*<\s*WendfoldDemoSeeder::MIN_PASSWORD_LENGTH/',
			$source
		);

		$script  = dirname(__DIR__) . '/dev/seed-demo.php';
		$pipes   = [];
		$process = proc_open(
			[PHP_BINARY, $script, '--password=short'],
			[
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w'],
			],
			$pipes
		);

		$this->assertIsResource($process);

		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);

		$this->assertSame(1, $exit, (string) $stdout . (string) $stderr);
		$this->assertStringContainsString('at least 12 characters', (string) $stderr);
	}

	/**
	 * Generated passwords are random and at least 16 URL-safe characters.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGeneratedPasswordIsRandomAndLongEnough(): void
	{
		$this->loadSeederClass();

		$first  = \WendfoldDemoSeeder::generatePassword();
		$second = \WendfoldDemoSeeder::generatePassword();

		$this->assertGreaterThanOrEqual(16, strlen($first));
		$this->assertGreaterThanOrEqual(16, strlen($second));
		$this->assertNotSame($first, $second);
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $first);
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $second);

		$generate = $this->methodBody(
			(string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php'),
			'generatePassword'
		);
		$this->assertStringContainsString('random_bytes(', $generate);
		$this->assertStringContainsString('bin2hex(', $generate);
	}

	/**
	 * Demo username selection treats the underscore as literal, not a LIKE wildcard.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testDemoUsernamePrefixDoesNotMatchNearMisses(): void
	{
		$this->loadSeederClass();

		$this->assertTrue(\WendfoldDemoSeeder::isDemoUsername('wfdemo_avery_quinn'));
		$this->assertTrue(\WendfoldDemoSeeder::isDemoUsername('wfdemo_'));
		$this->assertFalse(\WendfoldDemoSeeder::isDemoUsername('wfdemoX_other'));
		$this->assertFalse(\WendfoldDemoSeeder::isDemoUsername('wfdemo1'));
		$this->assertFalse(\WendfoldDemoSeeder::isDemoUsername('wfdemo'));
		$this->assertFalse(\WendfoldDemoSeeder::isDemoUsername('other_wfdemo_user'));

		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$loader = $this->methodBody($source, 'loadDemoUserColumn');
		$seeded = $this->methodBody($source, 'alreadySeeded');

		$this->assertStringContainsString('LEFT(', $loader);
		$this->assertStringContainsString('LEFT(', $seeded);
		$this->assertStringNotContainsString(' LIKE ', $loader);
		$this->assertStringNotContainsString(' LIKE ', $seeded);
		$this->assertStringNotContainsString("USER_PREFIX . '%'", $source);
		$this->assertStringNotContainsString('getQuery(true)', $source);
		$this->assertStringContainsString('createQuery()', $source);
	}

	/**
	 * Load WendfoldDemoSeeder without running the CLI entry point.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function loadSeederClass(): void
	{
		if (!\defined('WENDFOLD_DEMO_SEED_SKIP_CLI'))
		{
			\define('WENDFOLD_DEMO_SEED_SKIP_CLI', 1);
		}

		require_once dirname(__DIR__) . '/dev/seed-demo.php';
	}

	/**
	 * Reset clears Joomla user side tables that a raw #__users delete would leave.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResetClearsDemoUserSideTables(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$reset  = $this->methodBody($source, 'reset');

		$this->assertStringContainsString("'#__session'", $reset);
		$this->assertStringContainsString("'userid'", $reset);
		$this->assertStringContainsString("'#__action_logs'", $reset);
		$this->assertStringContainsString("'#__user_usergroup_map'", $reset);
		$this->assertStringContainsString("'#__user_profiles'", $reset);
		$this->assertStringContainsString("'#__user_mfa'", $reset);
		$this->assertStringContainsString("'#__privacy_consents'", $reset);
		$this->assertStringContainsString("'#__messages'", $reset);
		$this->assertStringContainsString("'user_id_from'", $reset);
		$this->assertStringContainsString("'user_id_to'", $reset);
		$this->assertStringContainsString("'#__users'", $reset);

		$sessionPos = strpos($reset, "deleteByColumnIn('#__session'");
		$usersPos   = strpos($reset, "quoteName('#__users')");

		$this->assertNotFalse($sessionPos);
		$this->assertNotFalse($usersPos);
		$this->assertLessThan($usersPos, $sessionPos);
	}

	/**
	 * #__user_keys.user_id stores usernames; reset must delete by username strings.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResetDeletesUserKeysByUsernameNotNumericId(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$reset  = $this->methodBody($source, 'reset');

		$this->assertStringContainsString('demoUsernames()', $reset);
		$this->assertMatchesRegularExpression(
			'/deleteByColumnIn\(\s*\'#__user_keys\'\s*,\s*\'user_id\'\s*,\s*\$usernames\s*,\s*\\\\Joomla\\\\Database\\\\ParameterType::STRING\s*\)/s',
			$reset
		);
		$this->assertDoesNotMatchRegularExpression(
			'/deleteByColumnIn\(\s*\'#__user_keys\'\s*,\s*\'user_id\'\s*,\s*\$userIds\b/s',
			$reset
		);

		$loader = $this->methodBody($source, 'loadDemoUserColumn');
		$this->assertStringContainsString("quoteName('username')", $loader);
		$this->assertStringContainsString('USER_PREFIX', $loader);
		$this->assertStringContainsString('LEFT(', $loader);
	}

	/**
	 * Summary counts include event_metric_credits rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSummaryCountsEventMetricCredits(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertMatchesRegularExpression(
			"/'event_metric_credits'\s*=>\s*0/",
			$source
		);
		$this->assertStringContainsString(
			"\$this->counts['event_metric_credits']++",
			$source
		);
		$this->assertStringContainsString(
			"insertObject('#__wendfold_event_metric_credits'",
			$source
		);
	}

	/**
	 * Marker string must not be written into user-visible seeded field values.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMarkerNotWrittenToVisibleFieldValues(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertStringContainsString("public const MARKER = 'WENDFOLD_DEMO_SEED'", $source);
		$this->assertStringContainsString('Never written into user-visible', $source);

		$visibleAssignments = [
			"'description'",
			"'notes'",
			"'note'",
			"'title'",
			"'preferred_name'",
			"'name'",
			"'receipt_number'",
			"'nickname'",
		];

		foreach ($visibleAssignments as $field)
		{
			$this->assertDoesNotMatchRegularExpression(
				'/' . preg_quote($field, '/') . '\s*=>\s*self::MARKER\b/',
				$source,
				$field . ' must not be assigned self::MARKER'
			);
			$this->assertDoesNotMatchRegularExpression(
				'/' . preg_quote($field, '/') . '\s*=>\s*[\'"][^\'"]*WENDFOLD_DEMO_SEED/',
				$source,
				$field . ' must not contain WENDFOLD_DEMO_SEED'
			);
		}

		$this->assertDoesNotMatchRegularExpression(
			"/'description'\s*=>\s*[^,\n]*self::MARKER/",
			$source
		);

		$insertProfile = $this->methodBody($source, 'insertProfile');
		$seedEvents    = $this->methodBody($source, 'seedEvents');
		$insertMetric  = $this->methodBody($source, 'insertMetricEntry');
		$seedLocations = $this->methodBody($source, 'seedLocations');
		$seedActivities = $this->methodBody($source, 'seedMetricsAndActivities');

		$this->assertStringContainsString("'notes'                => ''", $insertProfile);
		$this->assertStringContainsString("'notes'              => ''", $seedEvents);
		$this->assertStringContainsString("'note'        => ''", $insertMetric);
		$this->assertStringContainsString("'notes'         => ''", $seedLocations);
		$this->assertStringNotContainsString('WENDFOLD_DEMO_SEED', $seedEvents);
		$this->assertStringNotContainsString('self::MARKER', $seedEvents);
		$this->assertStringNotContainsString('self::MARKER', $insertProfile);
		$this->assertStringNotContainsString('self::MARKER', $insertMetric);
		$this->assertStringNotContainsString('self::MARKER', $seedLocations);
		$this->assertStringNotContainsString('self::MARKER', $seedActivities);
		$this->assertStringContainsString('Leisurely loop through the river valley', $seedEvents);
		$this->assertStringContainsString('Logged personal ride miles', $seedActivities);
	}

	/**
	 * Reset finds demo rows via prefixes/FKs and exact legacy marker cleanup.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResetRemovesOnlyDemoRowsViaPrefixesAndForeignKeys(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$reset  = $this->methodBody($source, 'reset');

		$this->assertStringContainsString('demoUserIds()', $reset);
		$this->assertStringContainsString('demoUsernames()', $reset);
		$this->assertStringContainsString('demoProfileIds($userIds)', $reset);
		$this->assertStringContainsString('demoEventIds()', $reset);
		$this->assertStringContainsString('deleteDemoAwardDefinitions($userIds)', $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_member_awards', 'profile_id', \$profileIds)", $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_events', 'id', \$eventIds)", $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_locations', 'created_by', \$userIds)", $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_dues_payments', 'profile_id', \$profileIds)", $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_officers', 'profile_id', \$profileIds)", $reset);
		$this->assertStringContainsString("deleteByColumnIn('#__wendfold_motorcycles', 'profile_id', \$profileIds)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_locations', 'notes', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_profiles', 'notes', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_events', 'notes', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_events', 'description', self::legacyEventDescription())", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_dues_payments', 'notes', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_attendance', 'notes', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_activities', 'description', self::MARKER)", $reset);
		$this->assertStringContainsString("deleteByExactValue('#__wendfold_metric_entries', 'note', self::MARKER)", $reset);
		$this->assertStringNotContainsString(' LIKE ', $reset);
		$this->assertStringNotContainsString('deleteByMarker', $reset);
		$this->assertStringNotContainsString('idsByNotes', $reset);

		$profiles = $this->methodBody($source, 'demoProfileIds');
		$events   = $this->methodBody($source, 'demoEventIds');
		$exact    = $this->methodBody($source, 'deleteByExactValue');

		$this->assertStringContainsString('MEMBER_PREFIX', $profiles);
		$this->assertStringContainsString('LEFT(', $profiles);
		$this->assertStringContainsString('ALIAS_PREFIX', $events);
		$this->assertStringContainsString('LEFT(', $events);
		$this->assertStringNotContainsString(' LIKE ', $profiles);
		$this->assertStringNotContainsString(' LIKE ', $events);
		$this->assertStringContainsString('= :exactValue', $exact);
		$this->assertStringNotContainsString(' LIKE ', $exact);
	}

	/**
	 * Reset removes legacy-marker rows and leaves non-demo rows alone.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testResetClearsLegacyMarkerRowsAndSparesNonDemo(): void
	{
		$this->loadSeederClass();

		$marker      = \WendfoldDemoSeeder::MARKER;
		$legacyDesc  = \WendfoldDemoSeeder::legacyEventDescription();
		$db          = new DemoSeedResetDatabase(
			[
				'#__wendfold_locations' => [
					['id' => 1, 'notes' => $marker, 'name' => 'Legacy Hall', 'created_by' => 0],
					['id' => 2, 'notes' => $marker, 'name' => 'Legacy Lot', 'created_by' => 0],
					['id' => 3, 'notes' => $marker, 'name' => 'Legacy Trail', 'created_by' => 0],
					['id' => 4, 'notes' => $marker, 'name' => 'Legacy Cafe', 'created_by' => 0],
					[
						'id'         => 10,
						'notes'      => 'Mentions ' . $marker . ' in prose',
						'name'       => 'Keep substring notes',
						'created_by' => 99,
					],
					[
						'id'         => 11,
						'notes'      => '',
						'name'       => 'Chapter Hall with ' . $marker,
						'created_by' => 99,
					],
				],
				'#__wendfold_profiles' => [
					['id' => 20, 'notes' => $marker, 'user_id' => 0, 'member_number' => 'REAL-1'],
					['id' => 21, 'notes' => 'Officer note', 'user_id' => 50, 'member_number' => 'REAL-2'],
				],
				'#__wendfold_events' => [
					[
						'id'          => 30,
						'notes'       => $marker,
						'description' => $legacyDesc,
						'alias'       => 'chapter-meeting-keep-shape',
					],
					[
						'id'          => 31,
						'notes'       => '',
						'description' => 'Real chapter meeting mentioning ' . $marker,
						'alias'       => 'real-meeting',
					],
				],
				'#__wendfold_dues_payments' => [
					['id' => 40, 'notes' => $marker, 'profile_id' => 21],
					['id' => 41, 'notes' => 'Paid at meeting', 'profile_id' => 21],
				],
				'#__wendfold_officers' => [
					['id' => 50, 'notes' => $marker, 'profile_id' => 21],
					['id' => 51, 'notes' => '', 'profile_id' => 21],
				],
				'#__wendfold_motorcycles' => [
					['id' => 60, 'notes' => $marker, 'profile_id' => 21],
					['id' => 61, 'notes' => 'Primary bike', 'profile_id' => 21],
				],
				'#__wendfold_attendance' => [
					['id' => 70, 'notes' => $marker, 'event_id' => 31],
					['id' => 71, 'notes' => '', 'event_id' => 31],
				],
				'#__wendfold_activities' => [
					['id' => 80, 'description' => $marker, 'profile_id' => 21],
					['id' => 81, 'description' => 'Solo loop', 'profile_id' => 21],
				],
				'#__wendfold_metric_entries' => [
					['id' => 90, 'note' => $marker, 'profile_id' => 21, 'event_id' => null],
					['id' => 91, 'note' => '', 'profile_id' => 21, 'event_id' => null],
				],
				'#__users' => [
					['id' => 50, 'username' => 'real_member'],
				],
			]
		);

		$seeder = new \WendfoldDemoSeeder($db, 'password-password');
		$method = new \ReflectionMethod($seeder, 'reset');
		$method->invoke($seeder);

		$this->assertSame(
			[10, 11],
			array_column($db->rows('#__wendfold_locations'), 'id'),
			'Legacy notes=MARKER locations removed; non-exact notes and name mentions survive'
		);
		$this->assertSame([21], array_column($db->rows('#__wendfold_profiles'), 'id'));
		$this->assertSame([31], array_column($db->rows('#__wendfold_events'), 'id'));
		$this->assertSame([41], array_column($db->rows('#__wendfold_dues_payments'), 'id'));
		$this->assertSame([51], array_column($db->rows('#__wendfold_officers'), 'id'));
		$this->assertSame([61], array_column($db->rows('#__wendfold_motorcycles'), 'id'));
		$this->assertSame([71], array_column($db->rows('#__wendfold_attendance'), 'id'));
		$this->assertSame([81], array_column($db->rows('#__wendfold_activities'), 'id'));
		$this->assertSame([91], array_column($db->rows('#__wendfold_metric_entries'), 'id'));
		$this->assertSame([50], array_column($db->rows('#__users'), 'id'));

		foreach (\WendfoldDemoSeeder::legacyMarkerColumns() as [$table, $column])
		{
			foreach ($db->rows($table) as $row)
			{
				$this->assertNotSame(
					$marker,
					$row[$column] ?? null,
					$table . '.' . $column . ' must not retain an exact legacy marker'
				);
			}
		}
	}

	/**
	 * Demo members mix signed/current waivers with missing and expired ones.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testMixedWaiverStatesAcrossDemoMembers(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$waiver = $this->methodBody($source, 'waiverStateForIndex');

		$this->assertStringContainsString("'signed'      => 1", $waiver);
		$this->assertStringContainsString("'signed'      => 0", $waiver);
		$this->assertStringContainsString('[15, 16]', $waiver);
		$this->assertStringContainsString('$index === 17', $waiver);
		$this->assertStringContainsString("'version'     => '2022'", $waiver);

		$profile = $this->methodBody($source, 'insertProfile');
		$this->assertStringContainsString('waiverStateForIndex', $profile);
		$this->assertStringContainsString("\$waiver['signed']", $profile);
	}

	/**
	 * Seeder grants at least one member award from the product award tables.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSeedsAtLeastOneMemberAward(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertMatchesRegularExpression("/'member_awards'\s*=>\s*0/", $source);
		$this->assertStringContainsString("\$this->counts['member_awards']++", $source);
		$this->assertStringContainsString("insertObject('#__wendfold_member_awards'", $source);
		$this->assertStringContainsString('seedAwards(', $source);
		$this->assertStringContainsString('awardDefinitionMap(', $source);
		$this->assertStringContainsString('#__wendfold_award_definitions', $source);

		$awards = $this->methodBody($source, 'seedAwards');
		$this->assertStringContainsString("'grant_source'   => 2", $awards);
		$this->assertStringContainsString("'is_locked'      => 1", $awards);
		$this->assertStringContainsString("'state'          => 1", $awards);
	}

	/**
	 * One seeded event is dated site-today with check-in-open status.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOneEventDatedTodayWithSelfCheckinOpen(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');

		$this->assertStringContainsString('siteToday()', $source);
		$this->assertStringContainsString("get('offset', 'UTC')", $source);

		$events = $this->methodBody($source, 'seedEvents');
		$this->assertStringContainsString("'key'         => 'today-meeting'", $events);
		$this->assertStringContainsString("'date'        => \$today", $events);
		$this->assertStringContainsString("'status'      => 1", $events);
		$this->assertStringContainsString('Self check-in is open', $events);
		$this->assertStringContainsString('qr_token', $events);

		$rsvp = $this->methodBody($source, 'seedRsvpsAndAttendance');
		$this->assertStringContainsString("'today-meeting'", $rsvp);
	}

	/**
	 * Named Saturday rides always land on Saturdays relative to the reference date.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testNamedSaturdayRidesLandOnSaturdaysForEveryReferenceWeekday(): void
	{
		$this->loadSeederClass();

		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$events = $this->methodBody($source, 'seedEvents');

		$this->assertStringContainsString("'title'       => 'Weekend Group Ride'", $events);
		$this->assertStringContainsString("'date'        => self::nextSaturdayAfter(\$today)", $events);
		$this->assertStringContainsString("'title'       => 'Saturday Morning Ride'", $events);
		$this->assertStringContainsString("'date'        => self::previousSaturdayBefore(\$today)", $events);
		$this->assertDoesNotMatchRegularExpression(
			"/'title'\\s*=>\\s*'Weekend Group Ride'[\\s\\S]*?'date'\\s*=>\\s*\\\$base->modify\\('\\+10 days'\\)/",
			$events
		);
		$this->assertDoesNotMatchRegularExpression(
			"/'title'\\s*=>\\s*'Saturday Morning Ride'[\\s\\S]*?'date'\\s*=>\\s*\\\$base->modify\\('-7 days'\\)/",
			$events
		);

		// Mon..Sun reference dates in one week.
		$references = [
			'2026-11-02',
			'2026-11-03',
			'2026-11-04',
			'2026-11-05',
			'2026-11-06',
			'2026-11-07',
			'2026-11-08',
		];

		$upcomingExpected = [
			'2026-11-02' => '2026-11-07',
			'2026-11-03' => '2026-11-07',
			'2026-11-04' => '2026-11-07',
			'2026-11-05' => '2026-11-07',
			'2026-11-06' => '2026-11-07',
			'2026-11-07' => '2026-11-14',
			'2026-11-08' => '2026-11-14',
		];
		$pastExpected = [
			'2026-11-02' => '2026-10-31',
			'2026-11-03' => '2026-10-31',
			'2026-11-04' => '2026-10-31',
			'2026-11-05' => '2026-10-31',
			'2026-11-06' => '2026-10-31',
			'2026-11-07' => '2026-10-31',
			'2026-11-08' => '2026-11-07',
		];

		foreach ($references as $reference)
		{
			$weekday = (new \DateTimeImmutable($reference))->format('N');
			$upcoming = \WendfoldDemoSeeder::nextSaturdayAfter($reference);
			$past = \WendfoldDemoSeeder::previousSaturdayBefore($reference);

			$this->assertSame($upcomingExpected[$reference], $upcoming, 'upcoming for weekday ' . $weekday);
			$this->assertSame($pastExpected[$reference], $past, 'past for weekday ' . $weekday);
			$this->assertSame('6', (new \DateTimeImmutable($upcoming))->format('N'));
			$this->assertSame('6', (new \DateTimeImmutable($past))->format('N'));
			$this->assertGreaterThan($reference, $upcoming);
			$this->assertLessThan($reference, $past);
		}
	}

	/**
	 * Only ride-type demo events receive KSU/KSD times and locations.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testOnlyRideEventsGetKsuAndKsdFields(): void
	{
		$source = (string) file_get_contents(dirname(__DIR__) . '/dev/seed-demo.php');
		$events = $this->methodBody($source, 'seedEvents');

		$this->assertStringContainsString('$isRiding = (int) $def[\'riding\'] === 1', $events);
		$this->assertStringContainsString("'ksu_time'           => \$isRiding ? (\$def['ksu'] ?? null) : null", $events);
		$this->assertStringContainsString("'ksu_location_id'    => \$isRiding ? \$locId : null", $events);
		$this->assertStringContainsString("'ksd_time'           => \$isRiding ? (\$def['ksd'] ?? null) : null", $events);
		$this->assertStringContainsString("'ksd_location_id'    => \$isRiding ? \$locId : null", $events);
		$this->assertStringNotContainsString("'ksu_time'           => \$def['ksu']", $events);
		$this->assertStringContainsString('KSU/KSD are ride-only', $events);

		preg_match_all(
			"/\\[\s*'key'\s*=>\s*'([^']+)'(.*?)\\],/s",
			$events,
			$matches,
			PREG_SET_ORDER
		);
		$this->assertGreaterThanOrEqual(7, \count($matches));

		$seenRide = false;
		$seenNonRide = false;

		foreach ($matches as $match)
		{
			$key   = $match[1];
			$block = $match[2];

			// Skip the $out[] append shape which also has a 'key' field.
			if (!str_contains($block, "'type'"))
			{
				continue;
			}

			if (str_contains($key, 'ride'))
			{
				$seenRide = true;
				$this->assertStringContainsString("'ksu'", $block, $key . ' should define KSU');
				$this->assertStringContainsString("'ksd'", $block, $key . ' should define KSD');
				continue;
			}

			$seenNonRide = true;
			$this->assertStringNotContainsString("'ksu'", $block, $key . ' must not define KSU');
			$this->assertStringNotContainsString("'ksd'", $block, $key . ' must not define KSD');
		}

		$this->assertTrue($seenRide);
		$this->assertTrue($seenNonRide);
	}

	/**
	 * Extract one private/public method body from the seed script source.
	 *
	 * @param   string  $source  Full PHP source.
	 * @param   string  $name    Method name without visibility.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function methodBody(string $source, string $name): string
	{
		$pattern = '/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*(?::\s*\w+\s*)?\{/';
		$this->assertSame(1, preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE));

		$start  = (int) $match[0][1] + strlen($match[0][0]) - 1;
		$depth  = 0;
		$length = strlen($source);

		for ($i = $start; $i < $length; $i++)
		{
			$char = $source[$i];

			if ($char === '{')
			{
				$depth++;
				continue;
			}

			if ($char === '}')
			{
				$depth--;

				if ($depth === 0)
				{
					return substr($source, $start, $i - $start + 1);
				}
			}
		}

		$this->fail('Could not find closing brace for method ' . $name);

		return '';
	}
}

/**
 * In-memory database double for demo seed --reset legacy cleanup tests.
 *
 * @since  1.0.0
 */
final class DemoSeedResetDatabase implements \Joomla\Database\DatabaseInterface
{
	/**
	 * Table rows keyed by #__ name.
	 *
	 * @var    array<string, list<array<string, mixed>>>
	 * @since  1.0.0
	 */
	private array $tables;

	/**
	 * Pending query.
	 *
	 * @var    DemoSeedResetQuery|null
	 * @since  1.0.0
	 */
	private ?DemoSeedResetQuery $pending = null;

	/**
	 * @param   array<string, list<array<string, mixed>>>  $tables  Initial rows.
	 *
	 * @since   1.0.0
	 */
	public function __construct(array $tables)
	{
		$this->tables = $tables;
	}

	/**
	 * Current rows for a table.
	 *
	 * @param   string  $table  Table name with #__ prefix.
	 *
	 * @return  list<array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	public function rows(string $table): array
	{
		return array_values($this->tables[$table] ?? []);
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getPrefix()
	{
		return 'jos_';
	}

	/**
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	public function getTableList()
	{
		$tables = [];

		foreach (array_keys($this->tables) as $name)
		{
			$tables[] = str_replace('#__', $this->getPrefix(), $name);
		}

		// Tables reset may probe even when empty in this fixture.
		foreach ([
			'#__wendfold_award_definitions',
			'#__wendfold_award_criteria',
			'#__wendfold_member_awards',
			'#__wendfold_rsvps',
			'#__wendfold_event_metric_credits',
			'#__wendfold_event_volunteers',
			'#__wendfold_event_volunteer_roles',
			'#__wendfold_event_group_members',
			'#__wendfold_event_groups',
			'#__wendfold_event_trip_participants',
			'#__user_keys',
			'#__session',
			'#__action_logs',
			'#__user_usergroup_map',
			'#__user_profiles',
			'#__user_mfa',
			'#__privacy_consents',
			'#__messages',
		] as $extra)
		{
			$physical = str_replace('#__', $this->getPrefix(), $extra);

			if (!\in_array($physical, $tables, true))
			{
				$tables[] = $physical;
				$this->tables[$extra] = $this->tables[$extra] ?? [];
			}
		}

		return $tables;
	}

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  DemoSeedResetQuery
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		return new DemoSeedResetQuery;
	}

	/**
	 * @return  DemoSeedResetQuery
	 *
	 * @since   1.0.0
	 */
	public function createQuery()
	{
		return $this->getQuery(true);
	}

	/**
	 * @param   string  $name  Identifier.
	 * @param   mixed   $as    Optional alias.
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
	 * @param   mixed    $text    Value.
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
	 * @param   mixed    $query   Query object.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		$this->pending = $query instanceof DemoSeedResetQuery ? $query : null;

		return $this;
	}

	/**
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function execute()
	{
		if ($this->pending === null || $this->pending->type !== 'delete')
		{
			return true;
		}

		$table = $this->pending->table;
		$rows  = $this->tables[$table] ?? [];
		$kept  = [];

		foreach ($rows as $row)
		{
			if ($this->pending->matches($row))
			{
				continue;
			}

			$kept[] = $row;
		}

		$this->tables[$table] = $kept;
		$this->pending        = null;

		return true;
	}

	/**
	 * @return  list<mixed>
	 *
	 * @since   1.0.0
	 */
	public function loadColumn()
	{
		if ($this->pending === null || $this->pending->type !== 'select')
		{
			$this->pending = null;

			return [];
		}

		$column = $this->pending->selectColumn ?? 'id';
		$values = [];

		foreach ($this->tables[$this->pending->table] ?? [] as $row)
		{
			if ($this->pending->matches($row))
			{
				$values[] = $row[$column] ?? null;
			}
		}

		$this->pending = null;

		return $values;
	}

	/**
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function loadResult()
	{
		$column = $this->loadColumn();

		return $column[0] ?? null;
	}

	/**
	 * @param   string  $key  Unused.
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

/**
 * Query builder double for DemoSeedResetDatabase.
 *
 * @since  1.0.0
 */
final class DemoSeedResetQuery
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	public string $type = '';

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	public string $table = '';

	/**
	 * @var    string|null
	 * @since  1.0.0
	 */
	public ?string $selectColumn = null;

	/**
	 * Exact equality filters (column => value).
	 *
	 * @var    array<string, mixed>
	 * @since  1.0.0
	 */
	public array $equals = [];

	/**
	 * whereIn filters.
	 *
	 * @var    array<string, list<mixed>>
	 * @since  1.0.0
	 */
	public array $ins = [];

	/**
	 * LEFT(column, len) = value filters.
	 *
	 * @var    list<array{column: string, length: int, value: string}>
	 * @since  1.0.0
	 */
	public array $leftEquals = [];

	/**
	 * Bound values by placeholder name.
	 *
	 * @var    array<string, mixed>
	 * @since  1.0.0
	 */
	private array $binds = [];

	/**
	 * Pending equality placeholder before bind resolves.
	 *
	 * @var    array<string, string>
	 * @since  1.0.0
	 */
	private array $pendingEquals = [];

	/**
	 * Pending LEFT() placeholder before bind resolves.
	 *
	 * @var    list<array{column: string, length: int, key: string}>
	 * @since  1.0.0
	 */
	private array $pendingLeft = [];

	/**
	 * @param   mixed  $columns  Select list.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		$this->type = 'select';

		if (\is_string($columns))
		{
			$this->selectColumn = trim($columns, ' `');
		}
		elseif (\is_array($columns) && isset($columns[0]) && \is_string($columns[0]))
		{
			$this->selectColumn = trim($columns[0], ' `');
		}

		return $this;
	}

	/**
	 * @param   mixed  $table  Table name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table)
	{
		$this->table = $this->unquote((string) $table);

		return $this;
	}

	/**
	 * @param   mixed  $table  Table name.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function delete($table)
	{
		$this->type  = 'delete';
		$this->table = $this->unquote((string) $table);

		return $this;
	}

	/**
	 * @param   mixed   $conditions  WHERE SQL.
	 * @param   string  $glue        Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($conditions, $glue = 'AND')
	{
		$sql = (string) $conditions;

		if (preg_match('/^LEFT\(`([^`]+)`,\s*(\d+)\)\s*=\s*:(\w+)$/', $sql, $match) === 1)
		{
			$this->pendingLeft[] = [
				'column' => $match[1],
				'length' => (int) $match[2],
				'key'    => $match[3],
			];

			return $this;
		}

		if (preg_match('/^`([^`]+)`\s*=\s*:(\w+)$/', $sql, $match) === 1)
		{
			$this->pendingEquals[$match[1]] = $match[2];

			return $this;
		}

		return $this;
	}

	/**
	 * @param   string             $column         Column SQL.
	 * @param   list<int|string>   $values         Values.
	 * @param   string             $parameterType  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function whereIn($column, array $values, $parameterType = 'int')
	{
		$this->ins[$this->unquote((string) $column)] = array_values($values);

		return $this;
	}

	/**
	 * @param   string  $key            Bind key.
	 * @param   mixed   $value          Value.
	 * @param   mixed   $dataType       Unused.
	 * @param   integer $length         Unused.
	 * @param   array   $driverOptions  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function bind($key, &$value, $dataType = 2, $length = 0, $driverOptions = [])
	{
		$name              = ltrim((string) $key, ':');
		$this->binds[$name] = $value;

		foreach ($this->pendingEquals as $column => $bindKey)
		{
			if ($bindKey === $name)
			{
				$this->equals[$column] = $value;
			}
		}

		foreach ($this->pendingLeft as $left)
		{
			if ($left['key'] === $name)
			{
				$this->leftEquals[] = [
					'column' => $left['column'],
					'length' => $left['length'],
					'value'  => (string) $value,
				];
			}
		}

		return $this;
	}

	/**
	 * @param   mixed  $columns  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function order($columns)
	{
		return $this;
	}

	/**
	 * Whether a row matches the accumulated filters.
	 *
	 * @param   array<string, mixed>  $row  Row.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function matches(array $row): bool
	{
		foreach ($this->equals as $column => $value)
		{
			if (($row[$column] ?? null) !== $value)
			{
				return false;
			}
		}

		foreach ($this->ins as $column => $values)
		{
			if (!\in_array($row[$column] ?? null, $values, false))
			{
				return false;
			}
		}

		foreach ($this->leftEquals as $left)
		{
			$haystack = (string) ($row[$left['column']] ?? '');

			if (substr($haystack, 0, $left['length']) !== $left['value'])
			{
				return false;
			}
		}

		return $this->equals !== [] || $this->ins !== [] || $this->leftEquals !== [];
	}

	/**
	 * @param   string  $name  Possibly quoted identifier.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function unquote(string $name): string
	{
		return trim($name, ' `');
	}
}
