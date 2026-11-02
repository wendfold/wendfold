#!/usr/bin/env php
<?php

/**
 * Wendfold - developer-only demo data seed (not shipped in pkg_wendfold).
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

/**
 * Seeds and resets tagged demo rows for screenshots and demos.
 *
 * Demo rows are identified without writing a marker into user-visible fields.
 * Reset finds them via username prefix, member_number prefix, event alias
 * prefix, created_by on demo users (locations / optional award definitions),
 * and foreign keys from those roots. The MARKER constant is CLI identity only.
 *
 * @since  1.0.0
 */
final class WendfoldDemoSeeder
{
	/**
	 * CLI identity for this seeder. Never written into user-visible DB fields.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const MARKER = 'WENDFOLD_DEMO_SEED';

	/**
	 * Username prefix for demo Joomla users.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const USER_PREFIX = 'wfdemo_';

	/**
	 * Member number prefix for demo profiles.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const MEMBER_PREFIX = 'DEMO-';

	/**
	 * Event alias prefix for demo events.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private const ALIAS_PREFIX = 'demo-seed-';

	/**
	 * Minimum length accepted for --password=.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public const MIN_PASSWORD_LENGTH = 12;

	/**
	 * Database driver.
	 *
	 * @var    \Joomla\Database\DatabaseInterface
	 * @since  1.0.0
	 */
	private \Joomla\Database\DatabaseInterface $db;

	/**
	 * Plaintext password shared by every demo user for this run.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $password;

	/**
	 * Counts printed in the summary.
	 *
	 * @var    array<string, int>
	 * @since  1.0.0
	 */
	private array $counts = [
		'users'                 => 0,
		'profiles'              => 0,
		'officers'              => 0,
		'locations'             => 0,
		'events'                => 0,
		'rsvps'                 => 0,
		'attendance'            => 0,
		'event_metric_credits'  => 0,
		'metric_entries'        => 0,
		'activities'            => 0,
		'dues_payments'         => 0,
		'motorcycles'           => 0,
		'member_awards'         => 0,
		'award_definitions'     => 0,
	];

	/**
	 * @param   \Joomla\Database\DatabaseInterface  $db        Site database.
	 * @param   string                              $password  Plaintext password for demo users.
	 *
	 * @since   1.0.0
	 */
	public function __construct(\Joomla\Database\DatabaseInterface $db, string $password)
	{
		$this->db       = $db;
		$this->password = $password;
	}

	/**
	 * Strong URL-safe random password for one seed run (at least 16 characters).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function generatePassword(): string
	{
		return bin2hex(random_bytes(12));
	}

	/**
	 * Whether a username uses the literal demo prefix (underscore is not a wildcard).
	 *
	 * @param   string  $username  Candidate username.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public static function isDemoUsername(string $username): bool
	{
		return str_starts_with($username, self::USER_PREFIX);
	}

	/**
	 * Next Saturday strictly after a Y-m-d reference date.
	 *
	 * Always advances: when the reference day is already Saturday, the
	 * following Saturday is returned so an "upcoming" seed stays upcoming.
	 *
	 * @param   string  $ymd  Reference calendar day (Y-m-d).
	 *
	 * @return  string  Y-m-d Saturday.
	 *
	 * @since   1.0.0
	 */
	public static function nextSaturdayAfter(string $ymd): string
	{
		$date = new \DateTimeImmutable($ymd . ' 00:00:00');

		return $date->modify('next Saturday')->format('Y-m-d');
	}

	/**
	 * Most recent Saturday strictly before a Y-m-d reference date.
	 *
	 * Always steps back: when the reference day is already Saturday, the
	 * previous Saturday is returned so a "past" seed stays in the past.
	 *
	 * @param   string  $ymd  Reference calendar day (Y-m-d).
	 *
	 * @return  string  Y-m-d Saturday.
	 *
	 * @since   1.0.0
	 */
	public static function previousSaturdayBefore(string $ymd): string
	{
		$date = new \DateTimeImmutable($ymd . ' 00:00:00');

		return $date->modify('previous Saturday')->format('Y-m-d');
	}

	/**
	 * Run reset (optional) then seed.
	 *
	 * @param   boolean  $reset  True to remove previous demo rows first.
	 *
	 * @return  integer  Exit code.
	 *
	 * @since   1.0.0
	 */
	public function run(bool $reset): int
	{
		if (!$this->componentInstalled())
		{
			fwrite(STDERR, "com_wendfold is not installed or not enabled. Aborting.\n");

			return 1;
		}

		if ($reset)
		{
			$this->reset();
			fwrite(STDOUT, "Removed previous demo seed rows (" . self::MARKER . ").\n");
		}
		elseif ($this->alreadySeeded())
		{
			fwrite(STDOUT, "Demo data already present. Use --reset to replace it.\n");

			return 0;
		}

		$this->seed();
		$this->printSummary();

		return 0;
	}

	/**
	 * Whether com_wendfold is installed and enabled.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function componentInstalled(): bool
	{
		$query = $this->db->createQuery()
			->select('COUNT(*)')
			->from($this->db->quoteName('#__extensions'))
			->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
			->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_wendfold'))
			->where($this->db->quoteName('enabled') . ' = 1');

		return (int) $this->db->setQuery($query)->loadResult() > 0
			&& $this->tableExists('#__wendfold_profiles');
	}

	/**
	 * Whether any tagged demo user already exists.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function alreadySeeded(): bool
	{
		$prefix = self::USER_PREFIX;
		$query  = $this->db->createQuery()
			->select('COUNT(*)')
			->from($this->db->quoteName('#__users'))
			->where(
				'LEFT(' . $this->db->quoteName('username') . ', ' . \strlen($prefix) . ') = :demoUsernamePrefix'
			);
		$query->bind(':demoUsernamePrefix', $prefix);

		return (int) $this->db->setQuery($query)->loadResult() > 0;
	}

	/**
	 * Exact event description written by the earlier seeder (marker as a suffix).
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function legacyEventDescription(): string
	{
		return 'Demo chapter event for screenshots. ' . self::MARKER;
	}

	/**
	 * Columns where older seeder versions wrote MARKER as the entire field value.
	 *
	 * Exact equality only — never a substring / LIKE match.
	 *
	 * @return  list<array{0: string, 1: string}>
	 *
	 * @since   1.0.0
	 */
	public static function legacyMarkerColumns(): array
	{
		return [
			['#__wendfold_locations', 'notes'],
			['#__wendfold_profiles', 'notes'],
			['#__wendfold_events', 'notes'],
			['#__wendfold_dues_payments', 'notes'],
			['#__wendfold_officers', 'notes'],
			['#__wendfold_motorcycles', 'notes'],
			['#__wendfold_attendance', 'notes'],
			['#__wendfold_activities', 'description'],
			['#__wendfold_metric_entries', 'note'],
		];
	}

	/**
	 * Delete exactly the rows this script creates (including legacy-tagged rows).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function reset(): void
	{
		$userIds    = $this->demoUserIds();
		$usernames  = $this->demoUsernames();
		$profileIds = array_values(
			array_unique(
				array_merge(
					$this->demoProfileIds($userIds),
					$this->idsByExactValue('#__wendfold_profiles', 'notes', self::MARKER)
				)
			)
		);
		$eventIds = array_values(
			array_unique(
				array_merge(
					$this->demoEventIds(),
					$this->idsByExactValue('#__wendfold_events', 'notes', self::MARKER),
					$this->idsByExactValue(
						'#__wendfold_events',
						'description',
						self::legacyEventDescription()
					)
				)
			)
		);

		$this->deleteDemoAwardDefinitions($userIds);
		$this->deleteByColumnIn('#__wendfold_member_awards', 'profile_id', $profileIds);

		$this->deleteByColumnIn('#__wendfold_metric_entries', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_metric_entries', 'profile_id', $profileIds);
		$this->deleteByExactValue('#__wendfold_metric_entries', 'note', self::MARKER);

		$this->deleteByColumnIn('#__wendfold_activities', 'profile_id', $profileIds);
		$this->deleteByExactValue('#__wendfold_activities', 'description', self::MARKER);

		$this->deleteByColumnIn('#__wendfold_attendance', 'event_id', $eventIds);
		$this->deleteByExactValue('#__wendfold_attendance', 'notes', self::MARKER);

		$this->deleteByColumnIn('#__wendfold_rsvps', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_metric_credits', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_volunteers', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_volunteer_roles', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_group_members', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_groups', 'event_id', $eventIds);
		$this->deleteByColumnIn('#__wendfold_event_trip_participants', 'event_id', $eventIds);

		$this->deleteByColumnIn('#__wendfold_events', 'id', $eventIds);
		$this->deleteByExactValue('#__wendfold_events', 'notes', self::MARKER);
		$this->deleteByExactValue('#__wendfold_events', 'description', self::legacyEventDescription());

		$this->deleteByColumnIn('#__wendfold_dues_payments', 'profile_id', $profileIds);
		$this->deleteByExactValue('#__wendfold_dues_payments', 'notes', self::MARKER);
		$this->deleteByColumnIn('#__wendfold_officers', 'profile_id', $profileIds);
		$this->deleteByExactValue('#__wendfold_officers', 'notes', self::MARKER);
		$this->deleteByColumnIn('#__wendfold_motorcycles', 'profile_id', $profileIds);
		$this->deleteByExactValue('#__wendfold_motorcycles', 'notes', self::MARKER);

		$this->deleteByColumnIn('#__wendfold_profiles', 'id', $profileIds);
		$this->deleteByColumnIn('#__wendfold_profiles', 'user_id', $userIds);
		$this->deleteByExactValue('#__wendfold_profiles', 'notes', self::MARKER);

		// #__user_keys.user_id is varchar(150) and stores the username, not the id.
		$this->deleteByColumnIn(
			'#__user_keys',
			'user_id',
			$usernames,
			\Joomla\Database\ParameterType::STRING
		);

		if ($userIds !== [])
		{
			// Raw #__users deletes skip Joomla user-delete plugins; clear side tables.
			// Integer user id columns (session.userid, action_logs.user_id, etc.).
			$this->deleteByColumnIn('#__session', 'userid', $userIds);
			$this->deleteByColumnIn('#__action_logs', 'user_id', $userIds);
			$this->deleteByColumnIn('#__user_usergroup_map', 'user_id', $userIds);
			$this->deleteByColumnIn('#__user_profiles', 'user_id', $userIds);
			$this->deleteByColumnIn('#__user_mfa', 'user_id', $userIds);
			$this->deleteByColumnIn('#__privacy_consents', 'user_id', $userIds);
			$this->deleteByColumnIn('#__messages', 'user_id_from', $userIds);
			$this->deleteByColumnIn('#__messages', 'user_id_to', $userIds);

			$query = $this->db->createQuery()
				->delete($this->db->quoteName('#__users'))
				->whereIn(
					$this->db->quoteName('id'),
					$userIds,
					\Joomla\Database\ParameterType::INTEGER
				);
			$this->db->setQuery($query)->execute();
		}

		// Current seeder: created_by on a demo user. Earlier seeder: notes = MARKER.
		$this->deleteByColumnIn('#__wendfold_locations', 'created_by', $userIds);
		$this->deleteByExactValue('#__wendfold_locations', 'notes', self::MARKER);
	}

	/**
	 * Insert the demo chapter dataset.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seed(): void
	{
		$now     = \Joomla\CMS\Factory::getDate()->toSql();
		$today   = $this->siteToday();
		$year    = (int) (new \DateTimeImmutable($today))->format('Y');
		$members = $this->memberDefinitions();

		$membershipTypeId = $this->firstId('#__wendfold_membership_types', 'ordering');
		$eventTypes       = $this->eventTypeMap();
		$roles            = $this->officerRoleMap();

		$profiles = [];

		foreach ($members as $index => $member)
		{
			$userId = $this->insertUser($member, $now);
			$profiles[] = $this->insertProfile($userId, $member, $index, $membershipTypeId, $today, $now);
			$this->counts['users']++;
			$this->counts['profiles']++;
		}

		$ownerUserId = $profiles[0]['user_id'];
		$locationIds = $this->seedLocations($now, $ownerUserId);

		$this->seedMotorcycles($profiles, $now);
		$this->seedOfficers($profiles, $roles, $today, $now);
		$this->seedDues($profiles, $year, $today, $now);
		$this->seedAwards($profiles, $year, $today, $now, $ownerUserId);

		$events = $this->seedEvents($profiles, $eventTypes, $locationIds, $today, $now);
		$this->seedRsvpsAndAttendance($events, $profiles, $now);
		$this->seedMetricsAndActivities($events, $profiles, $today, $now);
	}

	/**
	 * Site-local calendar day (Y-m-d) from the Joomla configuration offset.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function siteToday(): string
	{
		$offset = 'UTC';
		$app    = \Joomla\CMS\Factory::getApplication();

		if ($app !== null && \is_callable([$app, 'get']))
		{
			$value = $app->get('offset', 'UTC');

			if (\is_string($value) && trim($value) !== '')
			{
				$offset = trim($value);
			}
		}

		try
		{
			$timezone = new \DateTimeZone($offset);
		}
		catch (\Exception $exception)
		{
			$timezone = new \DateTimeZone('UTC');
		}

		return (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
	}

	/**
	 * Fictional member roster (example.com emails).
	 *
	 * @return  list<array{name: string, preferred: string, emailLocal: string}>
	 *
	 * @since   1.0.0
	 */
	private function memberDefinitions(): array
	{
		return [
			['name' => 'Avery Quinn', 'preferred' => 'Avery', 'emailLocal' => 'avery.quinn'],
			['name' => 'Blake Morrow', 'preferred' => 'Blake', 'emailLocal' => 'blake.morrow'],
			['name' => 'Casey Lang', 'preferred' => 'Casey', 'emailLocal' => 'casey.lang'],
			['name' => 'Drew Pell', 'preferred' => 'Drew', 'emailLocal' => 'drew.pell'],
			['name' => 'Ellis Voss', 'preferred' => 'Ellis', 'emailLocal' => 'ellis.voss'],
			['name' => 'Finley Orth', 'preferred' => 'Finley', 'emailLocal' => 'finley.orth'],
			['name' => 'Gray Nunez', 'preferred' => 'Gray', 'emailLocal' => 'gray.nunez'],
			['name' => 'Harper Sidhu', 'preferred' => 'Harper', 'emailLocal' => 'harper.sidhu'],
			['name' => 'Indy Calder', 'preferred' => 'Indy', 'emailLocal' => 'indy.calder'],
			['name' => 'Jordan Phelps', 'preferred' => 'Jordan', 'emailLocal' => 'jordan.phelps'],
			['name' => 'Kai Remington', 'preferred' => 'Kai', 'emailLocal' => 'kai.remington'],
			['name' => 'Logan Estes', 'preferred' => 'Logan', 'emailLocal' => 'logan.estes'],
			['name' => 'Morgan Yates', 'preferred' => 'Morgan', 'emailLocal' => 'morgan.yates'],
			['name' => 'Noa Bridger', 'preferred' => 'Noa', 'emailLocal' => 'noa.bridger'],
			['name' => 'Parker Solis', 'preferred' => 'Parker', 'emailLocal' => 'parker.solis'],
			['name' => 'Quinn Ashford', 'preferred' => 'Quinn', 'emailLocal' => 'quinn.ashford'],
			['name' => 'Reese Tam', 'preferred' => 'Reese', 'emailLocal' => 'reese.tam'],
			['name' => 'Sage Corbin', 'preferred' => 'Sage', 'emailLocal' => 'sage.corbin'],
		];
	}

	/**
	 * Insert a Joomla user and Registered group map row.
	 *
	 * @param   array{name: string, preferred: string, emailLocal: string}  $member  Member stub.
	 * @param   string                                                      $now     UTC datetime.
	 *
	 * @return  integer  New user id.
	 *
	 * @since   1.0.0
	 */
	private function insertUser(array $member, string $now): int
	{
		$slug = str_replace('.', '_', $member['emailLocal']);
		$row  = (object) [
			'name'         => $member['name'],
			'username'     => self::USER_PREFIX . $slug,
			'email'        => $member['emailLocal'] . '@example.com',
			'password'     => \Joomla\CMS\User\UserHelper::hashPassword($this->password),
			'block'        => 0,
			'sendEmail'    => 0,
			'registerDate' => $now,
			'params'       => '{}',
		];

		$this->db->insertObject('#__users', $row);
		$userId = (int) $this->db->insertid();

		$map = (object) [
			'user_id'  => $userId,
			'group_id' => 2,
		];
		$this->db->insertObject('#__user_usergroup_map', $map);

		return $userId;
	}

	/**
	 * Insert a member profile linked to a Joomla user.
	 *
	 * @param   integer                                                     $userId            User id.
	 * @param   array{name: string, preferred: string, emailLocal: string}  $member            Member stub.
	 * @param   integer                                                     $index             Roster index.
	 * @param   integer                                                     $membershipTypeId  Membership type.
	 * @param   string                                                      $today             Local date.
	 * @param   string                                                      $now               UTC datetime.
	 *
	 * @return  array{id: int, user_id: int, index: int}
	 *
	 * @since   1.0.0
	 */
	private function insertProfile(
		int $userId,
		array $member,
		int $index,
		int $membershipTypeId,
		string $today,
		string $now
	): array {
		$duesPaid = $index < 15 ? 1 : 0;
		$status   = $index === 17 ? 2 : 1;
		$join     = (new \DateTimeImmutable($today))->modify('-' . (400 + ($index * 40)) . ' days')->format('Y-m-d');
		$renewal  = (new \DateTimeImmutable($today))->modify('+' . (60 + ($index * 7)) . ' days')->format('Y-m-d');

		// Most members signed/current; a few missing or expired for badge variety.
		$waiver = $this->waiverStateForIndex($index, $join, $today);

		$row = (object) [
			'user_id'              => $userId,
			'member_number'        => self::MEMBER_PREFIX . (1001 + $index),
			'preferred_name'       => $member['preferred'],
			'membership_type_id'   => $membershipTypeId > 0 ? $membershipTypeId : null,
			'member_status'        => $status,
			'member_since'         => $join,
			'chapter_join_date'    => $join,
			'renewal_date'         => $renewal,
			'waiver_signed_date'   => $waiver['signed_date'],
			'waiver_version'       => $waiver['version'],
			'is_road_captain'      => $index === 4 ? 1 : 0,
			'is_road_crew'         => \in_array($index, [4, 5, 10], true) ? 1 : 0,
			'national_verified_date' => $join,
			'dues_paid'            => $duesPaid,
			'dues_paid_date'       => $duesPaid ? $today : null,
			'waiver_signed'        => $waiver['signed'],
			'national_verified'    => 1,
			'status'               => $status,
			'notes'                => '',
			'published'            => 1,
			'created'              => $now,
			'created_by'           => $userId,
			'modified'             => $now,
			'modified_by'          => $userId,
		];

		$this->db->insertObject('#__wendfold_profiles', $row);

		return [
			'id'      => (int) $this->db->insertid(),
			'user_id' => $userId,
			'index'   => $index,
		];
	}

	/**
	 * Waiver fields for a roster index: signed/current, missing, or expired.
	 *
	 * @param   integer  $index  Roster index.
	 * @param   string   $join   Chapter join date.
	 * @param   string   $today  Site-local today.
	 *
	 * @return  array{signed: int, signed_date: string|null, version: string}
	 *
	 * @since   1.0.0
	 */
	private function waiverStateForIndex(int $index, string $join, string $today): array
	{
		if (\in_array($index, [15, 16], true))
		{
			return [
				'signed'      => 0,
				'signed_date' => null,
				'version'     => '',
			];
		}

		if ($index === 17)
		{
			$expired = (new \DateTimeImmutable($today))->modify('-400 days')->format('Y-m-d');

			return [
				'signed'      => 0,
				'signed_date' => $expired,
				'version'     => '2022',
			];
		}

		return [
			'signed'      => 1,
			'signed_date' => $join,
			'version'     => '1.0',
		];
	}

	/**
	 * Seed a few motorcycles for early roster members.
	 *
	 * @param   list<array{id: int, user_id: int, index: int}>  $profiles  Profile rows.
	 * @param   string                                          $now       UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedMotorcycles(array $profiles, string $now): void
	{
		$bikes = [
			[0, 2018, 'Touring Standard', 'Blue', 'River'],
			[1, 2020, 'Adventure Twin', 'Gray', 'Scout'],
			[4, 2016, 'Cruiser Classic', 'Black', 'Night'],
			[10, 2022, 'Sport Tour', 'Red', 'Spark'],
		];

		foreach ($bikes as [$index, $year, $model, $color, $nickname])
		{
			$row = (object) [
				'profile_id' => $profiles[$index]['id'],
				'model_year' => $year,
				'year'       => $year,
				'make'       => 'Example Motors',
				'model'      => $model,
				'color'      => $color,
				'nickname'   => $nickname,
				'is_primary' => 1,
				'published'  => 1,
				'notes'      => '',
				'created'    => $now,
				'created_by' => $profiles[$index]['user_id'],
				'modified'   => $now,
				'modified_by' => $profiles[$index]['user_id'],
			];
			$this->db->insertObject('#__wendfold_motorcycles', $row);
			$this->counts['motorcycles']++;
		}
	}

	/**
	 * Assign officers using install-seeded roles when present.
	 *
	 * @param   list<array{id: int, user_id: int, index: int}>  $profiles  Profiles.
	 * @param   array<string, int>                              $roles     system_key/title => id.
	 * @param   string                                          $today     Local date.
	 * @param   string                                          $now       UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedOfficers(array $profiles, array $roles, string $today, string $now): void
	{
		$termStart = (new \DateTimeImmutable($today))->modify('first day of january this year')->format('Y-m-d');
		$assignments = [
			[0, 'director'],
			[1, 'assistant_director'],
			[2, 'secretary'],
			[3, 'treasurer'],
			[4, 'head_road_captain'],
			[5, 'safety'],
		];

		foreach ($assignments as $order => [$index, $key])
		{
			$roleId = $roles[$key] ?? 0;

			if ($roleId <= 0)
			{
				continue;
			}

			$row = (object) [
				'profile_id' => $profiles[$index]['id'],
				'role_id'    => $roleId,
				'term_start' => $termStart,
				'term_end'   => null,
				'is_current' => 1,
				'ordering'   => $order + 1,
				'role_order' => $order + 1,
				'published'  => 1,
				'notes'      => '',
				'created'    => $now,
				'created_by' => $profiles[$index]['user_id'],
				'modified'   => $now,
				'modified_by' => $profiles[$index]['user_id'],
			];
			$this->db->insertObject('#__wendfold_officers', $row);
			$this->counts['officers']++;
		}
	}

	/**
	 * Seed dues payment rows for members marked paid.
	 *
	 * @param   list<array{id: int, user_id: int, index: int}>  $profiles  Profiles.
	 * @param   integer                                         $year      Calendar year.
	 * @param   string                                          $today     Local date.
	 * @param   string                                          $now       UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedDues(array $profiles, int $year, string $today, string $now): void
	{
		$periodStart = sprintf('%d-01-01', $year);
		$periodEnd   = sprintf('%d-12-31', $year);

		foreach ($profiles as $profile)
		{
			if ($profile['index'] >= 15)
			{
				continue;
			}

			$row = (object) [
				'profile_id'     => $profile['id'],
				'period_start'   => $periodStart,
				'period_end'     => $periodEnd,
				'amount'         => 50.00,
				'payment_date'   => $today,
				'payment_method' => 'Cash',
				'receipt_number' => 'CH-' . ($year * 100 + $profile['index']),
				'notes'          => '',
				'published'      => 1,
				'year'           => $year,
				'amount_paid'    => 50.00,
				'created'        => $now,
				'created_by'     => $profile['user_id'],
				'modified'       => $now,
				'modified_by'    => $profile['user_id'],
			];
			$this->db->insertObject('#__wendfold_dues_payments', $row);
			$this->counts['dues_payments']++;
		}
	}

	/**
	 * Grant a few demo members awards so awards screens are not empty.
	 *
	 * Uses install-seeded award definitions when present; otherwise inserts a
	 * minimal manual definition owned by a demo user (removed on --reset).
	 *
	 * @param   list<array{id: int, user_id: int, index: int}>  $profiles     Profiles.
	 * @param   integer                                         $year         Calendar year.
	 * @param   string                                          $today        Local date.
	 * @param   string                                          $now          UTC datetime.
	 * @param   integer                                         $ownerUserId  Demo user for created_by.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedAwards(
		array $profiles,
		int $year,
		string $today,
		string $now,
		int $ownerUserId
	): void {
		if (!$this->tableExists('#__wendfold_award_definitions')
			|| !$this->tableExists('#__wendfold_member_awards'))
		{
			return;
		}

		$definitions = $this->awardDefinitionMap($now, $ownerUserId);

		if ($definitions === [])
		{
			return;
		}

		$grants = [
			[
				'profile'    => 0,
				'key'        => 'annual_events',
				'period_key' => (string) $year,
				'tier'       => 1,
				'value'      => 22.0,
			],
			[
				'profile'    => 1,
				'key'        => 'membership_years',
				'period_key' => '',
				'tier'       => 1,
				'value'      => 5.0,
			],
			[
				'profile'    => 4,
				'key'        => 'lifetime_distance',
				'period_key' => '',
				'tier'       => 1,
				'value'      => 12500.0,
			],
			[
				'profile'    => 10,
				'key'        => 'annual_volunteer',
				'period_key' => (string) $year,
				'tier'       => 1,
				'value'      => 28.0,
			],
		];

		foreach ($grants as $grant)
		{
			$awardId = $definitions[$grant['key']] ?? 0;

			if ($awardId <= 0 || !isset($profiles[$grant['profile']]))
			{
				continue;
			}

			$row = (object) [
				'award_id'       => $awardId,
				'profile_id'     => $profiles[$grant['profile']]['id'],
				'period_key'     => $grant['period_key'],
				'tier'           => $grant['tier'],
				'achieved_value' => $grant['value'],
				'state'          => 1,
				'grant_source'   => 2,
				'is_locked'      => 1,
				'earned_at'      => $now,
				'presented_on'   => $today,
				'notes'          => '',
				'modified'       => $now,
			];
			$this->db->insertObject('#__wendfold_member_awards', $row);
			$this->counts['member_awards']++;
		}
	}

	/**
	 * Map friendly keys to award definition ids, seeding one definition if empty.
	 *
	 * @param   string   $now          UTC datetime.
	 * @param   integer  $ownerUserId  Demo user for created_by on new definitions.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	private function awardDefinitionMap(string $now, int $ownerUserId): array
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName(['id', 'title', 'period_type', 'repeat_mode', 'ordering']))
			->from($this->db->quoteName('#__wendfold_award_definitions'))
			->where($this->db->quoteName('published') . ' = 1')
			->order($this->db->quoteName('ordering') . ' ASC');
		$rows = $this->db->setQuery($query)->loadObjectList() ?: [];
		$map  = [];

		foreach ($rows as $row)
		{
			$title = (string) $row->title;

			if (str_contains($title, 'ANNUAL_DISTANCE') && !isset($map['annual_distance']))
			{
				$map['annual_distance'] = (int) $row->id;
			}

			if (str_contains($title, 'LIFETIME_DISTANCE') && !isset($map['lifetime_distance']))
			{
				$map['lifetime_distance'] = (int) $row->id;
			}

			if (str_contains($title, 'ANNUAL_EVENTS') && !isset($map['annual_events']))
			{
				$map['annual_events'] = (int) $row->id;
			}

			if (str_contains($title, 'ANNUAL_VOLUNTEER') && !isset($map['annual_volunteer']))
			{
				$map['annual_volunteer'] = (int) $row->id;
			}

			if (str_contains($title, 'MEMBERSHIP_YEARS') && !isset($map['membership_years']))
			{
				$map['membership_years'] = (int) $row->id;
			}
		}

		if ($map !== [])
		{
			return $map;
		}

		// Fresh / stripped install: seed a single lifetime manual award definition.
		$definition = (object) [
			'title'         => 'Chapter Appreciation',
			'description'   => 'Recognises outstanding chapter service.',
			'period_type'   => 1,
			'match_mode'    => 1,
			'repeat_mode'   => 0,
			'revoke_policy' => 1,
			'published'     => 1,
			'ordering'      => 1,
			'created'       => $now,
			'created_by'    => $ownerUserId,
			'modified'      => $now,
			'modified_by'   => $ownerUserId,
		];
		$this->db->insertObject('#__wendfold_award_definitions', $definition);
		$awardId = (int) $this->db->insertid();
		$this->counts['award_definitions']++;

		if ($this->tableExists('#__wendfold_award_criteria'))
		{
			$criterion = (object) [
				'award_id'        => $awardId,
				'criterion_type'  => 2,
				'metric_id'       => null,
				'required_award_id' => null,
				'threshold'       => 1.0,
				'tier_step'       => null,
				'ordering'        => 1,
			];
			$this->db->insertObject('#__wendfold_award_criteria', $criterion);
		}

		return [
			'annual_events'     => $awardId,
			'membership_years'  => $awardId,
			'lifetime_distance' => $awardId,
			'annual_volunteer'  => $awardId,
			'annual_distance'   => $awardId,
		];
	}

	/**
	 * Seed meeting and ride locations.
	 *
	 * @param   string   $now          UTC datetime.
	 * @param   integer  $ownerUserId  Demo user id stored in created_by for reset.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	private function seedLocations(string $now, int $ownerUserId): array
	{
		$places = [
			['Cedar Community Hall', '100 Main Street', 'Cedar Ridge', 'OR', '97001', 'US'],
			['Riverside Parking Lot', '45 River Road', 'Cedar Ridge', 'OR', '97001', 'US'],
			['North Trailhead', '800 Ridge Way', 'Pine Grove', 'OR', '97005', 'US'],
			['Lakeside Cafe', '12 Lake View', 'Cedar Ridge', 'OR', '97001', 'US'],
		];

		$ids = [];

		foreach ($places as $i => $place)
		{
			$row = (object) [
				'name'          => $place[0],
				'address'       => $place[1],
				'city'          => $place[2],
				'region'        => $place[3],
				'state'         => $place[3],
				'postcode'      => $place[4],
				'zip'           => $place[4],
				'country'       => $place[5],
				'is_default'    => $i === 0 ? 1 : 0,
				'is_adhoc'      => 0,
				'published'     => 1,
				'location_type' => 1,
				'notes'         => '',
				'created'       => $now,
				'created_by'    => $ownerUserId,
				'modified'      => $now,
				'modified_by'   => $ownerUserId,
			];
			$this->db->insertObject('#__wendfold_locations', $row);
			$ids[] = (int) $this->db->insertid();
			$this->counts['locations']++;
		}

		return $ids;
	}

	/**
	 * Seed past, today, and upcoming events (rides and meetings).
	 *
	 * @param   list<array{id: int, user_id: int, index: int}>  $profiles    Profiles.
	 * @param   array<string, int>                              $eventTypes  Behavior key => id.
	 * @param   list<int>                                       $locations   Location ids.
	 * @param   string                                          $today       Local date.
	 * @param   string                                          $now         UTC datetime.
	 *
	 * @return  list<array{id: int, key: string, date: string, status: int}>
	 *
	 * @since   1.0.0
	 */
	private function seedEvents(
		array $profiles,
		array $eventTypes,
		array $locations,
		string $today,
		string $now
	): array {
		$base = new \DateTimeImmutable($today);
		$defs = [
			[
				'key'         => 'past-meeting',
				'title'       => 'Monthly Chapter Meeting',
				'alias'       => self::ALIAS_PREFIX . 'past-chapter-meeting',
				'type'        => 'meeting',
				'date'        => $base->modify('-14 days')->format('Y-m-d'),
				'status'      => 2,
				'riding'      => 0,
				'workflow'    => 3,
				'loc'         => 0,
				'description' => 'Monthly business meeting covering rides, safety briefs, and chapter news.',
			],
			[
				'key'         => 'past-officers',
				'title'       => 'Officers Meeting',
				'alias'       => self::ALIAS_PREFIX . 'past-officers-meeting',
				'type'        => 'officers',
				'date'        => $base->modify('-21 days')->format('Y-m-d'),
				'status'      => 2,
				'riding'      => 0,
				'workflow'    => 3,
				'loc'         => 0,
				'description' => 'Officers planning session for the upcoming ride calendar and budget.',
			],
			[
				'key'         => 'past-ride',
				'title'       => 'Saturday Morning Ride',
				'alias'       => self::ALIAS_PREFIX . 'past-saturday-ride',
				'type'        => 'ride',
				'date'        => self::previousSaturdayBefore($today),
				'status'      => 2,
				'riding'      => 1,
				'workflow'    => 2,
				'loc'         => 1,
				'ksu'         => '09:00:00',
				'ksd'         => '14:00:00',
				'description' => 'Leisurely loop through the river valley with a coffee stop at the trailhead.',
			],
			[
				'key'         => 'today-meeting',
				'title'       => 'Chapter Meeting',
				'alias'       => self::ALIAS_PREFIX . 'today-chapter-meeting',
				'type'        => 'meeting',
				'date'        => $today,
				'status'      => 1,
				'riding'      => 0,
				'workflow'    => 3,
				'loc'         => 0,
				'description' => 'Tonight\'s chapter meeting. Self check-in is open at the door.',
			],
			[
				'key'         => 'upcoming-meeting',
				'title'       => 'Chapter Meeting',
				'alias'       => self::ALIAS_PREFIX . 'upcoming-chapter-meeting',
				'type'        => 'meeting',
				'date'        => $base->modify('+5 days')->format('Y-m-d'),
				'status'      => 1,
				'riding'      => 0,
				'workflow'    => 3,
				'loc'         => 0,
				'description' => 'Regular chapter meeting with guest safety talk and open floor.',
			],
			[
				'key'         => 'upcoming-ride',
				'title'       => 'Weekend Group Ride',
				'alias'       => self::ALIAS_PREFIX . 'upcoming-weekend-ride',
				'type'        => 'ride',
				'date'        => self::nextSaturdayAfter($today),
				'status'      => 1,
				'riding'      => 1,
				'workflow'    => 2,
				'loc'         => 2,
				'ksu'         => '08:30:00',
				'ksd'         => '15:00:00',
				'description' => 'Group ride to the north ridge overlook. Full tank and rain gear recommended.',
			],
			[
				'key'         => 'upcoming-social',
				'title'       => 'Lakeside Potluck Social',
				'alias'       => self::ALIAS_PREFIX . 'upcoming-potluck-social',
				'type'        => 'social',
				'date'        => $base->modify('+20 days')->format('Y-m-d'),
				'status'      => 1,
				'riding'      => 0,
				'workflow'    => 1,
				'loc'         => 3,
				'description' => 'Bring a dish to share. Families welcome; no ride required.',
			],
		];

		$rcId = $profiles[4]['id'];
		$out  = [];

		foreach ($defs as $def)
		{
			$typeId = $eventTypes[$def['type']] ?? ($eventTypes['general'] ?? 0);

			if ($typeId <= 0)
			{
				continue;
			}

			$isRiding = (int) $def['riding'] === 1;
			$locId    = $locations[$def['loc']] ?? null;
			$row      = (object) [
				'event_type_id'      => $typeId,
				'title'              => $def['title'],
				'alias'              => $def['alias'],
				'description'        => $def['description'],
				'event_date'         => $def['date'],
				'start_date'         => $def['date'],
				// KSU/KSD are ride-only; meetings and socials leave them empty.
				'ksu_time'           => $isRiding ? ($def['ksu'] ?? null) : null,
				'ksu_location_id'    => $isRiding ? $locId : null,
				'ksd_time'           => $isRiding ? ($def['ksd'] ?? null) : null,
				'ksd_location_id'    => $isRiding ? $locId : null,
				'venue_location_id'  => $locId,
				'event_location_id'  => $locId,
				'status'             => $def['status'],
				'workflow_mode'      => $def['workflow'],
				'audience_scope'     => 1,
				'is_riding_event'    => $def['riding'],
				'allow_rsvp'         => 1,
				'show_in_event_list' => 1,
				'capacity'           => 0,
				'road_captain_id'    => $isRiding ? $rcId : null,
				'qr_token'           => bin2hex(random_bytes(16)),
				'published'          => 1,
				'completed_at'       => $def['status'] === 2 ? $now : null,
				'notes'              => '',
				'created'            => $now,
				'created_by'         => $profiles[0]['user_id'],
				'modified'           => $now,
				'modified_by'        => $profiles[0]['user_id'],
			];
			$this->db->insertObject('#__wendfold_events', $row);
			$eventId = (int) $this->db->insertid();
			$this->counts['events']++;

			$out[] = [
				'id'     => $eventId,
				'key'    => $def['key'],
				'date'   => $def['date'],
				'status' => $def['status'],
			];
		}

		return $out;
	}

	/**
	 * Seed RSVPs for open events and attendance for past events.
	 *
	 * @param   list<array{id: int, key: string, date: string, status: int}>  $events    Events.
	 * @param   list<array{id: int, user_id: int, index: int}>                $profiles  Profiles.
	 * @param   string                                                        $now       UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedRsvpsAndAttendance(array $events, array $profiles, string $now): void
	{
		foreach ($events as $event)
		{
			$going = match ($event['key'])
			{
				'past-ride', 'upcoming-ride' => range(0, 11),
				'past-meeting', 'upcoming-meeting', 'today-meeting' => range(0, 13),
				'past-officers' => range(0, 5),
				'upcoming-social' => range(0, 9),
				default => range(0, 7),
			};

			foreach ($going as $i => $profileIndex)
			{
				$response = ($i === \count($going) - 1) ? 2 : 1;
				$rsvp     = (object) [
					'event_id'   => $event['id'],
					'profile_id' => $profiles[$profileIndex]['id'],
					'response'   => $response,
					'guests'     => 0,
					'created'    => $now,
					'modified'   => $now,
				];
				$this->db->insertObject('#__wendfold_rsvps', $rsvp);
				$this->counts['rsvps']++;
			}

			if ((int) $event['status'] !== 2)
			{
				continue;
			}

			$attendees = \array_slice($going, 0, max(4, \count($going) - 2));

			foreach ($attendees as $profileIndex)
			{
				$row = (object) [
					'event_id'        => $event['id'],
					'profile_id'      => $profiles[$profileIndex]['id'],
					'check_in_time'   => $now,
					'check_in_method' => 3,
					'checked_in_by'   => $profiles[0]['user_id'],
					'notes'           => '',
				];
				$this->db->insertObject('#__wendfold_attendance', $row);
				$this->counts['attendance']++;
			}
		}
	}

	/**
	 * Seed metric definitions usage via entries plus member activities.
	 *
	 * @param   list<array{id: int, key: string, date: string, status: int}>  $events    Events.
	 * @param   list<array{id: int, user_id: int, index: int}>                $profiles  Profiles.
	 * @param   string                                                        $today     Local date.
	 * @param   string                                                        $now       UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function seedMetricsAndActivities(array $events, array $profiles, string $today, string $now): void
	{
		$metrics = $this->metricIdMap();
		$pastRide = null;

		foreach ($events as $event)
		{
			if ($event['key'] === 'past-ride')
			{
				$pastRide = $event;
				break;
			}
		}

		if ($pastRide !== null && isset($metrics['distance'], $metrics['events_attended'], $metrics['points']))
		{
			foreach (['distance' => 85.5, 'events_attended' => 1.0, 'points' => 10.0] as $key => $value)
			{
				$credit = (object) [
					'event_id'  => $pastRide['id'],
					'metric_id' => $metrics[$key],
					'value'     => $value,
				];
				$this->db->insertObject('#__wendfold_event_metric_credits', $credit);
				$this->counts['event_metric_credits']++;
			}

			$eventId = (int) $pastRide['id'];
			$query   = $this->db->createQuery()
				->select($this->db->quoteName(['id', 'profile_id']))
				->from($this->db->quoteName('#__wendfold_attendance'))
				->where($this->db->quoteName('event_id') . ' = :eventId');
			$query->bind(':eventId', $eventId, \Joomla\Database\ParameterType::INTEGER);
			$attendanceRows = $this->db->setQuery($query)->loadObjectList() ?: [];

			$attendanceCredits = [
				['distance', 85.5, 1],
				['events_attended', 1.0, 1],
				['points', 10.0, 1],
			];

			foreach ($attendanceRows as $attendance)
			{
				foreach ($attendanceCredits as [$key, $value, $published])
				{
					$this->insertMetricEntry(
						(int) $attendance->{'profile_id'},
						$metrics[$key],
						$pastRide['date'],
						(float) $value,
						$pastRide['id'],
						1,
						(int) $attendance->id,
						$published,
						$now
					);
				}
			}
		}

		if (isset($metrics['volunteer_hours']))
		{
			foreach ([0, 2, 4, 7] as $i => $profileIndex)
			{
				$this->insertMetricEntry(
					$profiles[$profileIndex]['id'],
					$metrics['volunteer_hours'],
					$today,
					(float) (2 + $i),
					null,
					3,
					null,
					$i % 2 === 0 ? 1 : 0,
					$now
				);
			}
		}

		if (!isset($metrics['distance']))
		{
			return;
		}

		$activityTitles = [
			1  => 'Solo practice loop',
			6  => 'Evening canyon ride',
			11 => 'Sunday foothills outing',
		];

		foreach ([1, 6, 11] as $offset => $profileIndex)
		{
			$activityDate = (new \DateTimeImmutable($today))->modify('-' . (3 + $offset) . ' days')->format('Y-m-d');
			$activity     = (object) [
				'profile_id'     => $profiles[$profileIndex]['id'],
				'activity_date'  => $activityDate,
				'title'          => $activityTitles[$profileIndex],
				'description'    => 'Logged personal ride miles for the chapter year.',
				'start_location' => 'Cedar Ridge',
				'end_location'   => 'Pine Grove',
				'published'      => 1,
				'created'        => $now,
				'created_by'     => $profiles[$profileIndex]['user_id'],
				'modified'       => $now,
				'modified_by'    => $profiles[$profileIndex]['user_id'],
			];
			$this->db->insertObject('#__wendfold_activities', $activity);
			$activityId = (int) $this->db->insertid();
			$this->counts['activities']++;

			$this->insertMetricEntry(
				$profiles[$profileIndex]['id'],
				$metrics['distance'],
				$activityDate,
				42.0 + ($offset * 5),
				null,
				2,
				$activityId,
				$offset === 2 ? 0 : 1,
				$now
			);
		}
	}

	/**
	 * Insert one metric_entries row (cleared on reset via profile/event ids).
	 *
	 * @param   integer       $profileId  Profile id.
	 * @param   integer       $metricId   Metric definition id.
	 * @param   string        $entryDate  Local date.
	 * @param   float         $value      Entry value.
	 * @param   integer|null  $eventId    Optional event id.
	 * @param   integer       $sourceType MetricSource value.
	 * @param   integer|null  $sourceId   Source row id.
	 * @param   integer       $published  1 published, 0 pending.
	 * @param   string        $now        UTC datetime.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function insertMetricEntry(
		int $profileId,
		int $metricId,
		string $entryDate,
		float $value,
		?int $eventId,
		int $sourceType,
		?int $sourceId,
		int $published,
		string $now
	): void {
		$row = (object) [
			'profile_id'  => $profileId,
			'metric_id'   => $metricId,
			'entry_date'  => $entryDate,
			'value'       => $value,
			'event_id'    => $eventId,
			'source_type' => $sourceType,
			'source_id'   => $sourceId,
			'published'   => $published,
			'note'        => '',
			'created'     => $now,
			'modified'    => $now,
		];
		$this->db->insertObject('#__wendfold_metric_entries', $row);
		$this->counts['metric_entries']++;
	}

	/**
	 * Print a human-readable summary of inserted counts.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function printSummary(): void
	{
		fwrite(STDOUT, "Demo seed complete (" . self::MARKER . ").\n");

		foreach ($this->counts as $label => $count)
		{
			fwrite(STDOUT, sprintf("  %-16s %d\n", $label . ':', $count));
		}

		fwrite(STDOUT, "Demo password for all users: " . $this->password . "\n");
	}

	/**
	 * First id from a lookup table ordered by the given column.
	 *
	 * @param   string  $table    Table name with #__ prefix.
	 * @param   string  $orderBy  Order column.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function firstId(string $table, string $orderBy): int
	{
		if (!$this->tableExists($table))
		{
			return 0;
		}

		$query = $this->db->createQuery()
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName($table))
			->where($this->db->quoteName('published') . ' = 1')
			->order($this->db->quoteName($orderBy) . ' ASC');

		return (int) $this->db->setQuery($query, 0, 1)->loadResult();
	}

	/**
	 * Map event type buckets to ids from the install defaults.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	private function eventTypeMap(): array
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName(['id', 'behavior', 'title', 'ordering']))
			->from($this->db->quoteName('#__wendfold_event_types'))
			->where($this->db->quoteName('published') . ' = 1')
			->order($this->db->quoteName('ordering') . ' ASC');
		$rows = $this->db->setQuery($query)->loadObjectList() ?: [];
		$map  = [];

		foreach ($rows as $row)
		{
			$behavior = (int) $row->behavior;
			$title    = (string) $row->title;

			if ($behavior === 1 && !isset($map['ride']))
			{
				$map['ride'] = (int) $row->id;
			}

			if ($behavior === 2 && str_contains($title, 'CHAPTER') && !isset($map['meeting']))
			{
				$map['meeting'] = (int) $row->id;
			}

			if ($behavior === 2 && str_contains($title, 'OFFICERS') && !isset($map['officers']))
			{
				$map['officers'] = (int) $row->id;
			}

			if ($behavior === 4 && str_contains($title, 'SOCIAL') && !isset($map['social']))
			{
				$map['social'] = (int) $row->id;
			}

			if ($behavior === 4 && !isset($map['general']))
			{
				$map['general'] = (int) $row->id;
			}
		}

		return $map;
	}

	/**
	 * Map officer role keys to ids.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	private function officerRoleMap(): array
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName(['id', 'system_key', 'title', 'ordering']))
			->from($this->db->quoteName('#__wendfold_officer_roles'))
			->where($this->db->quoteName('published') . ' = 1')
			->order($this->db->quoteName('ordering') . ' ASC');
		$rows = $this->db->setQuery($query)->loadObjectList() ?: [];
		$map  = [];

		foreach ($rows as $row)
		{
			$systemKey = (string) ($row->{'system_key'} ?? '');
			$title     = (string) $row->title;

			if ($systemKey === 'head_road_captain')
			{
				$map['head_road_captain'] = (int) $row->id;
			}

			if (str_contains($title, 'DIRECTOR') && !str_contains($title, 'ASSISTANT') && !isset($map['director']))
			{
				$map['director'] = (int) $row->id;
			}

			if (str_contains($title, 'ASSISTANT_DIRECTOR'))
			{
				$map['assistant_director'] = (int) $row->id;
			}

			if (str_contains($title, 'SECRETARY'))
			{
				$map['secretary'] = (int) $row->id;
			}

			if (str_contains($title, 'TREASURER'))
			{
				$map['treasurer'] = (int) $row->id;
			}

			if (str_contains($title, 'SAFETY'))
			{
				$map['safety'] = (int) $row->id;
			}
		}

		return $map;
	}

	/**
	 * Map metric_key => id for install-seeded definitions.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	private function metricIdMap(): array
	{
		$query = $this->db->createQuery()
			->select($this->db->quoteName(['id', 'metric_key']))
			->from($this->db->quoteName('#__wendfold_metric_definitions'))
			->where($this->db->quoteName('published') . ' = 1');
		$rows = $this->db->setQuery($query)->loadObjectList() ?: [];
		$map  = [];

		foreach ($rows as $row)
		{
			$map[(string) $row->{'metric_key'}] = (int) $row->id;
		}

		return $map;
	}

	/**
	 * Demo Joomla user ids by username prefix.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	private function demoUserIds(): array
	{
		return array_map('intval', $this->loadDemoUserColumn('id'));
	}

	/**
	 * Demo Joomla usernames by username prefix.
	 *
	 * Needed for #__user_keys, whose user_id column stores the username.
	 *
	 * @return  list<string>
	 *
	 * @since   1.0.0
	 */
	private function demoUsernames(): array
	{
		return array_values(array_map('strval', $this->loadDemoUserColumn('username')));
	}

	/**
	 * Demo profile ids via demo user_ids and member_number prefix.
	 *
	 * @param   list<int>  $userIds  Demo user ids.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	private function demoProfileIds(array $userIds): array
	{
		if (!$this->tableExists('#__wendfold_profiles'))
		{
			return [];
		}

		$ids = [];

		if ($userIds !== [])
		{
			$query = $this->db->createQuery()
				->select($this->db->quoteName('id'))
				->from($this->db->quoteName('#__wendfold_profiles'))
				->whereIn(
					$this->db->quoteName('user_id'),
					$userIds,
					\Joomla\Database\ParameterType::INTEGER
				);
			$ids = array_merge($ids, array_map('intval', $this->db->setQuery($query)->loadColumn() ?: []));
		}

		$prefix = self::MEMBER_PREFIX;
		$query  = $this->db->createQuery()
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName('#__wendfold_profiles'))
			->where(
				'LEFT(' . $this->db->quoteName('member_number') . ', ' . \strlen($prefix) . ') = :demoMemberPrefix'
			);
		$query->bind(':demoMemberPrefix', $prefix);
		$ids = array_merge($ids, array_map('intval', $this->db->setQuery($query)->loadColumn() ?: []));

		return array_values(array_unique($ids));
	}

	/**
	 * Demo event ids by alias prefix.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	private function demoEventIds(): array
	{
		if (!$this->tableExists('#__wendfold_events'))
		{
			return [];
		}

		$prefix = self::ALIAS_PREFIX;
		$query  = $this->db->createQuery()
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName('#__wendfold_events'))
			->where(
				'LEFT(' . $this->db->quoteName('alias') . ', ' . \strlen($prefix) . ') = :demoAliasPrefix'
			);
		$query->bind(':demoAliasPrefix', $prefix);

		return array_map('intval', $this->db->setQuery($query)->loadColumn() ?: []);
	}

	/**
	 * Delete award definitions (and criteria) created by demo users.
	 *
	 * @param   list<int>  $userIds  Demo user ids.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function deleteDemoAwardDefinitions(array $userIds): void
	{
		if ($userIds === [] || !$this->tableExists('#__wendfold_award_definitions'))
		{
			return;
		}

		$query = $this->db->createQuery()
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName('#__wendfold_award_definitions'))
			->whereIn(
				$this->db->quoteName('created_by'),
				$userIds,
				\Joomla\Database\ParameterType::INTEGER
			);
		$awardIds = array_map('intval', $this->db->setQuery($query)->loadColumn() ?: []);

		if ($awardIds === [])
		{
			return;
		}

		$this->deleteByColumnIn('#__wendfold_award_criteria', 'award_id', $awardIds);
		$this->deleteByColumnIn('#__wendfold_member_awards', 'award_id', $awardIds);
		$this->deleteByColumnIn('#__wendfold_award_definitions', 'id', $awardIds);
	}

	/**
	 * Load a #__users column for rows whose username starts with USER_PREFIX.
	 *
	 * Uses LEFT(...)= so the underscore in wfdemo_ is literal, not a LIKE wildcard.
	 *
	 * @param   string  $column  Column to select (id or username).
	 *
	 * @return  list<mixed>
	 *
	 * @since   1.0.0
	 */
	private function loadDemoUserColumn(string $column): array
	{
		$prefix = self::USER_PREFIX;
		$query  = $this->db->createQuery()
			->select($this->db->quoteName($column))
			->from($this->db->quoteName('#__users'))
			->where(
				'LEFT(' . $this->db->quoteName('username') . ', ' . \strlen($prefix) . ') = :demoUsernamePrefix'
			);
		$query->bind(':demoUsernamePrefix', $prefix);

		return $this->db->setQuery($query)->loadColumn() ?: [];
	}

	/**
	 * Ids whose column equals an exact value (legacy marker cleanup).
	 *
	 * @param   string  $table   Table name.
	 * @param   string  $column  Column name.
	 * @param   string  $value   Exact value to match.
	 *
	 * @return  list<int>
	 *
	 * @since   1.0.0
	 */
	private function idsByExactValue(string $table, string $column, string $value): array
	{
		if (!$this->tableExists($table))
		{
			return [];
		}

		$query = $this->db->createQuery()
			->select($this->db->quoteName('id'))
			->from($this->db->quoteName($table))
			->where($this->db->quoteName($column) . ' = :exactValue');
		$query->bind(':exactValue', $value);
		$ids = $this->db->setQuery($query)->loadColumn() ?: [];

		return array_map('intval', $ids);
	}

	/**
	 * Delete rows where a text column equals an exact value (no LIKE / substring).
	 *
	 * Used for legacy marker cleanup after older seeders wrote MARKER into notes.
	 *
	 * @param   string  $table   Table name.
	 * @param   string  $column  Column name.
	 * @param   string  $value   Exact value to match.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function deleteByExactValue(string $table, string $column, string $value): void
	{
		if (!$this->tableExists($table))
		{
			return;
		}

		$query = $this->db->createQuery()
			->delete($this->db->quoteName($table))
			->where($this->db->quoteName($column) . ' = :exactValue');
		$query->bind(':exactValue', $value);
		$this->db->setQuery($query)->execute();
	}

	/**
	 * Delete rows whose column is in the given value list.
	 *
	 * @param   string            $table          Table name.
	 * @param   string            $column         Column name.
	 * @param   list<int|string>  $values         Bound whereIn values.
	 * @param   string            $parameterType  ParameterType::INTEGER or STRING.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function deleteByColumnIn(
		string $table,
		string $column,
		array $values,
		string $parameterType = \Joomla\Database\ParameterType::INTEGER
	): void {
		if ($values === [] || !$this->tableExists($table))
		{
			return;
		}

		$query = $this->db->createQuery()
			->delete($this->db->quoteName($table))
			->whereIn(
				$this->db->quoteName($column),
				$values,
				$parameterType
			);
		$this->db->setQuery($query)->execute();
	}

	/**
	 * Whether a database table exists.
	 *
	 * @param   string  $table  Table name with #__ prefix.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function tableExists(string $table): bool
	{
		$name = str_replace('#__', $this->db->getPrefix(), $table);
		$tables = $this->db->getTableList();

		return \in_array(strtolower($name), array_map('strtolower', $tables), true);
	}
}

if (!\defined('WENDFOLD_DEMO_SEED_SKIP_CLI'))
{
	if (PHP_SAPI !== 'cli')
	{
		fwrite(STDERR, "This script must be run from the command line.\n");
		exit(1);
	}

	$options         = getopt('', ['site:', 'reset', 'password:']);
	$site            = isset($options['site']) ? (string) $options['site'] : '';
	$reset           = array_key_exists('reset', $options);
	$passwordProvided = array_key_exists('password', $options);

	if ($passwordProvided)
	{
		$passwordOption = is_string($options['password']) ? $options['password'] : '';

		if (strlen($passwordOption) < WendfoldDemoSeeder::MIN_PASSWORD_LENGTH)
		{
			fwrite(
				STDERR,
				'--password must be at least ' . WendfoldDemoSeeder::MIN_PASSWORD_LENGTH . " characters.\n"
			);
			exit(1);
		}
	}

	if ($site === '')
	{
		fwrite(
			STDERR,
			"Usage: php dev/seed-demo.php --site=/path/to/joomla [--reset] [--password=...]\n"
		);
		exit(1);
	}

	$siteRoot = realpath($site);

	if ($siteRoot === false || !is_dir($siteRoot) || !is_file($siteRoot . '/includes/defines.php'))
	{
		fwrite(STDERR, "Not a Joomla site root: {$site}\n");
		exit(1);
	}

	if (!defined('_JEXEC'))
	{
		define('_JEXEC', 1);
	}

	define('JPATH_BASE', $siteRoot);

	require_once JPATH_BASE . '/includes/defines.php';
	require_once JPATH_BASE . '/includes/framework.php';

	$container = \Joomla\CMS\Factory::getContainer();
	$container->alias('session', 'session.cli')
		->alias('JSession', 'session.cli')
		->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\Session::class, 'session.cli')
		->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

	/** @var \Joomla\Console\Application $app */
	$app = $container->get(\Joomla\Console\Application::class);
	\Joomla\CMS\Factory::$application = $app;

	/** @var \Joomla\Database\DatabaseInterface $db */
	$db = $container->get(\Joomla\Database\DatabaseInterface::class);

	$password = $passwordProvided
		? $passwordOption
		: WendfoldDemoSeeder::generatePassword();

	$seeder = new WendfoldDemoSeeder($db, $password);
	exit($seeder->run($reset));
}
