<?php

/**
 * Wendfold - RSVP, volunteer, and group capacity locks (PR5b).
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
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;

require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/DuplicateKey.php';
require_once dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventRowLock.php';

/**
 * Behavioural locks for PR5b: RSVP set-to-state, volunteer slots, group order.
 *
 * @since  1.0.0
 */
final class RsvpVolunteerCapacityLockTest extends TestCase
{
	/**
	 * Production writers lock the event row and use DuplicateKey.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testProductionWritersCallEventRowLockAndDuplicateKey(): void
	{
		$eventModel = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/EventModel.php'
		);
		$rcModel = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php'
		);
		$controller = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Controller/EventController.php'
		);
		$tmpl = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/tmpl/event/default.php'
		);

		$this->assertNotFalse($eventModel);
		$this->assertNotFalse($rcModel);
		$this->assertNotFalse($controller);
		$this->assertNotFalse($tmpl);

		$this->assertStringContainsString('EventRowLock::lock', $eventModel);
		$this->assertStringContainsString('DuplicateKey::is', $eventModel);
		$this->assertStringContainsString('function toggleRsvp(int $eventId, int $userId, bool $going)', $eventModel);
		$this->assertStringContainsString('lockVolunteerRole', $eventModel);
		$this->assertStringContainsString('FOR UPDATE', $eventModel);

		$this->assertStringContainsString('EventRowLock::lock', $rcModel);
		$this->assertStringContainsString('DuplicateKey::is', $rcModel);
		$this->assertStringContainsString('nextGroupOrder', $rcModel);
		$this->assertStringContainsString('ensureGroupMembership', $rcModel);
		$this->assertStringNotContainsString('insertGroupMemberWithOrderRetry', $rcModel);

		$this->assertStringContainsString("getInt('going'", $controller);
		$this->assertStringContainsString('toggleRsvp($eventId, $user->id, $going === 1)', $controller);
		$this->assertStringContainsString('PostGuard::passes()', $controller);

		$this->assertStringContainsString('name="going"', $tmpl);
		$this->assertStringContainsString("\$isRsvped ? '0' : '1'", $tmpl);
	}

	/**
	 * Lock is taken before the capacity count and the RSVP insert.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpLockTakenBeforeCapacityCountAndInsert(): void
	{
		$writer = new RsvpCapacityDouble;
		$writer->events[4] = (object) [
			'id'       => 4,
			'status'   => 1,
			'capacity' => 2,
		];

		$this->assertSame('added', $writer->toggleRsvp(4, 10, true));
		$this->assertSame(
			[
				'transactionStart',
				'SELECT #__wendfold_events FOR UPDATE',
				'COUNT #__wendfold_rsvps',
				'INSERT #__wendfold_rsvps',
				'transactionCommit',
			],
			$writer->statements
		);
	}

	/**
	 * Capacity-1 events refuse a second going RSVP.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpCapacityLimitHolds(): void
	{
		$writer = new RsvpCapacityDouble;
		$writer->events[4] = (object) [
			'id'       => 4,
			'status'   => 1,
			'capacity' => 1,
		];
		$writer->rsvps[4] = [9];

		$this->assertSame('full', $writer->toggleRsvp(4, 10, true));
		$this->assertSame([9], $writer->rsvps[4]);
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * Set-to-state is idempotent for going and not going.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpSetToStateIsIdempotentBothWays(): void
	{
		$writer = new RsvpCapacityDouble;
		$writer->events[4] = (object) [
			'id'       => 4,
			'status'   => 1,
			'capacity' => 5,
		];
		$writer->rsvps[4] = [10];

		$this->assertSame('added', $writer->toggleRsvp(4, 10, true));
		$this->assertSame([10], $writer->rsvps[4]);

		$writer->statements = [];
		$this->assertSame('removed', $writer->toggleRsvp(4, 11, false));
		$this->assertSame([10], $writer->rsvps[4]);
	}

	/**
	 * Duplicate-key on RSVP insert maps to added.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpDuplicateKeyMapsToAdded(): void
	{
		$writer = new RsvpCapacityDouble;
		$writer->events[4] = (object) [
			'id'       => 4,
			'status'   => 1,
			'capacity' => 5,
		];
		$writer->throwDuplicateOnInsert = true;

		$this->assertSame('added', $writer->toggleRsvp(4, 10, true));
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * Failure rolls the RSVP transaction back.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testRsvpTransactionRolledBackOnFailure(): void
	{
		$writer = new RsvpCapacityDouble;
		$writer->events[4] = (object) [
			'id'       => 4,
			'status'   => 1,
			'capacity' => 5,
		];
		$writer->throwGenericOnInsert = true;

		$this->assertFalse($writer->toggleRsvp(4, 10, true));
		$this->assertSame([], $writer->rsvps[4] ?? []);
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * Volunteer slot over-fill is refused under the lock.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testVolunteerSlotOverFillIsRefused(): void
	{
		$writer = new VolunteerCapacityDouble;
		$writer->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$writer->roles[7] = (object) [
			'id'         => 7,
			'event_id'   => 4,
			'slot_count' => 1,
		];
		$writer->volunteers[] = [
			'id'                 => 1,
			'event_id'           => 4,
			'volunteer_role_id'  => 7,
			'profile_id'         => 9,
			'assignment_status'  => 1,
		];

		$this->assertSame('full', $writer->saveAssignment(4, 7, 10, 2));
		$this->assertCount(1, $writer->volunteers);
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * A second active role for the same member is refused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testSecondActiveVolunteerRoleIsRefused(): void
	{
		$writer = new VolunteerCapacityDouble;
		$writer->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$writer->roles[8] = (object) [
			'id'         => 8,
			'event_id'   => 4,
			'slot_count' => 2,
		];
		$writer->volunteers[] = [
			'id'                 => 1,
			'event_id'           => 4,
			'volunteer_role_id'  => 7,
			'profile_id'         => 10,
			'assignment_status'  => 1,
		];

		$this->assertSame('alreadyassigned', $writer->toggleVolunteer(4, 8, 10));
		$this->assertCount(1, $writer->volunteers);
	}

	/**
	 * Group order is allocated under the event lock and retried once on duplicate.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupOrderAllocatedUnderLockAndRetriedOnceOnDuplicate(): void
	{
		$writer = new GroupOrderDouble;
		$writer->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$writer->groups[4] = [1];
		$writer->failFirstInsert = true;

		$this->assertTrue($writer->addGroup(4, 7));
		$this->assertSame([1, 2, 3], $writer->groups[4]);
		$this->assertSame(
			[
				'transactionStart',
				'SELECT #__wendfold_events FOR UPDATE',
				'MAX group_order',
				'INSERT #__wendfold_event_groups',
				'MAX group_order',
				'INSERT #__wendfold_event_groups',
				'transactionCommit',
			],
			$writer->statements
		);
		$this->assertSame(1, $writer->duplicateRetries);
		$source = (string) file_get_contents(
			dirname(__DIR__) . '/com_wendfold/site/src/Model/RcattendanceModel.php'
		);
		$createBody = $this->methodBody($source, 'createGroups');
		$this->assertStringNotContainsString('DuplicateKey::is($e)', $createBody);
		$this->assertStringContainsString("return 'db_error'", $createBody);
		$addBody = $this->methodBody($source, 'addGroup');
		$outerCatch = $this->outerCatchBlock($addBody);
		$this->assertStringNotContainsString('return true;', $outerCatch);
		$this->assertStringContainsString("return 'db_error'", $outerCatch);
	}

	/**
	 * Outer catch block text (last catch in the method body).
	 *
	 * @param   string  $body  Method body.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function outerCatchBlock(string $body): string
	{
		$pos = strrpos($body, 'catch');

		return $pos === false ? '' : substr($body, $pos);
	}

	/**
	 * Extract one method body for source assertions.
	 *
	 * @param   string  $source  File source.
	 * @param   string  $name    Method name.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function methodBody(string $source, string $name): string
	{
		if (!preg_match(
			'/function\s+' . preg_quote($name, '/') . '\s*\(.*?\{/s',
			$source,
			$match,
			PREG_OFFSET_CAPTURE
		))
		{
			return '';
		}

		$start = (int) $match[0][1] + \strlen($match[0][0]) - 1;
		$depth = 0;
		$length = \strlen($source);

		for ($i = $start; $i < $length; $i++)
		{
			$char = $source[$i];

			if ($char === '{')
			{
				$depth++;
			}
			elseif ($char === '}')
			{
				$depth--;

				if ($depth === 0)
				{
					return substr($source, $start, $i - $start + 1);
				}
			}
		}

		return '';
	}

	/**
	 * Duplicate-key after the single addGroup retry rolls back as db_error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupDuplicateAfterRetryReturnsDbError(): void
	{
		$writer = new GroupOrderDouble;
		$writer->events[4] = (object) [
			'id'     => 4,
			'status' => 1,
		];
		$writer->groups[4] = [1];
		$writer->failEveryInsert = true;

		$this->assertSame('db_error', $writer->addGroup(4, 7));
		$this->assertSame([1], $writer->groups[4]);
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * createGroups maps DuplicateKey after rollback to db_error, not success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreateGroupsDuplicateKeyReturnsDbErrorAndLeavesNoRows(): void
	{
		$writer = new CreateGroupsFailureDouble;
		$writer->failOnSync = true;

		$this->assertSame('db_error', $writer->createGroups(4, 2, 7));
		$this->assertSame([], $writer->groups);
		$this->assertSame([], $writer->members);
		$this->assertContains('transactionRollback', $writer->statements);
		$this->assertNotContains('transactionCommit', $writer->statements);
	}

	/**
	 * createGroups maps a generic DB failure after rollback to db_error.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testCreateGroupsDbFailureReturnsDbErrorAndLeavesNoRows(): void
	{
		$writer = new CreateGroupsFailureDouble;
		$writer->failOnInsert = true;

		$this->assertSame('db_error', $writer->createGroups(4, 2, 7));
		$this->assertSame([], $writer->groups);
		$this->assertSame([], $writer->members);
		$this->assertContains('transactionRollback', $writer->statements);
	}

	/**
	 * Assigning into the same group again is an idempotent success with no write.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupMembershipSameGroupIsIdempotent(): void
	{
		$writer = new GroupMembershipEnsureDouble;
		$writer->members[10] = ['id' => 1, 'event_group_id' => 5, 'assignment_order' => 1];

		$this->assertSame('same', $writer->ensure(4, 5, 10, 7));
		$this->assertSame(5, (int) $writer->members[10]['event_group_id']);
		$this->assertSame(0, $writer->insertCount);
		$this->assertSame(0, $writer->moveCount);
		$this->assertTrue($writer->assignGroup(4, 10, 5, 7));
	}

	/**
	 * Assigning into a different group moves the row (main assign/sync semantics).
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupMembershipDifferentGroupIsMoved(): void
	{
		$writer = new GroupMembershipEnsureDouble;
		$writer->members[10] = ['id' => 1, 'event_group_id' => 5, 'assignment_order' => 2];
		$writer->orders[8] = 3;

		$this->assertSame('moved', $writer->ensure(4, 8, 10, 7));
		$this->assertSame(8, (int) $writer->members[10]['event_group_id']);
		$this->assertSame(4, (int) $writer->members[10]['assignment_order']);
		$this->assertSame(1, $writer->moveCount);
		$this->assertSame(0, $writer->insertCount);
		$this->assertTrue($writer->assignGroup(4, 10, 8, 7));
	}

	/**
	 * A failed membership write surfaces as db_error and does not report success.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupMembershipFailureSurfacesAsError(): void
	{
		$writer = new GroupMembershipEnsureDouble;
		$writer->throwOnInsert = true;
		$writer->leaveMissingAfterRace = true;

		$this->assertSame('db_error', $writer->assignGroup(4, 10, 5, 7));
		$this->assertArrayNotHasKey(10, $writer->members);
		$this->assertFalse($writer->committed);
	}

	/**
	 * Install SQL unique key on group members is (event_id, profile_id) only.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function testGroupMembersUniqueKeyIsEventProfileNotAssignmentOrder(): void
	{
		$sql = file_get_contents(
			dirname(__DIR__) . '/com_wendfold/admin/sql/install.mysql.utf8.sql'
		);
		$this->assertNotFalse($sql);
		$start = strpos($sql, 'CREATE TABLE IF NOT EXISTS `#__wendfold_event_group_members`');
		$this->assertNotFalse($start);
		$chunk = substr($sql, (int) $start, 900);
		$this->assertStringContainsString(
			'UNIQUE KEY `idx_event_profile` (`event_id`, `profile_id`)',
			$chunk
		);
		$this->assertStringContainsString(
			'KEY `idx_group_order` (`event_group_id`, `assignment_order`)',
			$chunk
		);
		$this->assertStringNotContainsString('UNIQUE KEY `idx_group_order`', $chunk);
	}
}

/**
 * RSVP capacity writer double matching EventModel::toggleRsvp lock order.
 *
 * @since  1.0.0
 */
final class RsvpCapacityDouble
{
	/**
	 * Events keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * RSVP profile ids keyed by event id.
	 *
	 * @var    array<int, list<int>>
	 * @since  1.0.0
	 */
	public array $rsvps = [];

	/**
	 * Recorded statements.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * Throw duplicate-key on insert.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $throwDuplicateOnInsert = false;

	/**
	 * Throw a generic error on insert.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $throwGenericOnInsert = false;

	/**
	 * @param   integer  $eventId    Event id.
	 * @param   integer  $profileId  Profile id.
	 * @param   boolean  $going      Desired state.
	 *
	 * @return  string|false
	 *
	 * @since   1.0.0
	 */
	public function toggleRsvp(int $eventId, int $profileId, bool $going): string|false
	{
		$event = $this->events[$eventId] ?? null;

		if ($event === null)
		{
			return 'closed';
		}

		$this->statements[] = 'transactionStart';
		$db = new Pr5bLockRecordingDatabase;
		$db->eventRows[$eventId] = $event;
		$locked = EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);

		if ($locked === null)
		{
			$this->statements[] = 'transactionRollback';

			return 'closed';
		}

		$existing = \in_array($profileId, $this->rsvps[$eventId] ?? [], true);

		if (!$going)
		{
			if (!$existing)
			{
				$this->statements[] = 'transactionRollback';

				return 'removed';
			}

			$this->rsvps[$eventId] = array_values(
				array_filter(
					$this->rsvps[$eventId],
					static fn(int $id): bool => $id !== $profileId
				)
			);
			$this->statements[] = 'DELETE #__wendfold_rsvps';
			$this->statements[] = 'transactionCommit';

			return 'removed';
		}

		if ($existing)
		{
			$this->statements[] = 'transactionRollback';

			return 'added';
		}

		$this->statements[] = 'COUNT #__wendfold_rsvps';
		$capacity = (int) ($event->capacity ?? 0);
		$count = \count($this->rsvps[$eventId] ?? []);

		if ($capacity > 0 && $count >= $capacity)
		{
			$this->statements[] = 'transactionRollback';

			return 'full';
		}

		try
		{
			if ($this->throwDuplicateOnInsert)
			{
				throw new \RuntimeException('Duplicate entry \'4-10\' for key \'idx_event_profile\'', 1062);
			}

			if ($this->throwGenericOnInsert)
			{
				throw new \RuntimeException('write failed', 500);
			}

			$this->statements[] = 'INSERT #__wendfold_rsvps';
			$this->rsvps[$eventId][] = $profileId;
			$this->statements[] = 'transactionCommit';

			return 'added';
		}
		catch (\Throwable $error)
		{
			$this->statements[] = 'transactionRollback';

			if ($going && DuplicateKey::is($error))
			{
				return 'added';
			}

			return false;
		}
	}
}

/**
 * Volunteer capacity writer double for slot and single-role rules.
 *
 * @since  1.0.0
 */
final class VolunteerCapacityDouble
{
	/**
	 * Events keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * Roles keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $roles = [];

	/**
	 * Volunteer rows.
	 *
	 * @var    list<array<string, int>>
	 * @since  1.0.0
	 */
	public array $volunteers = [];

	/**
	 * Recorded statements.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * @param   integer  $eventId           Event id.
	 * @param   integer  $roleId            Role id.
	 * @param   integer  $profileId         Profile id.
	 * @param   integer  $assignmentStatus  Desired status.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function saveAssignment(int $eventId, int $roleId, int $profileId, int $assignmentStatus): string
	{
		$this->statements[] = 'transactionStart';
		$db = new Pr5bLockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);
		$this->statements[] = 'SELECT #__wendfold_event_volunteer_roles FOR UPDATE';

		$role = $this->roles[$roleId] ?? null;

		if ($role === null)
		{
			$this->statements[] = 'transactionRollback';

			return 'notfound';
		}

		$filled = 0;

		foreach ($this->volunteers as $row)
		{
			if ((int) $row['volunteer_role_id'] === $roleId
				&& (int) $row['profile_id'] !== $profileId
				&& \in_array((int) $row['assignment_status'], [1, 2], true))
			{
				$filled++;
			}
		}

		$alreadyFills = false;

		foreach ($this->volunteers as $row)
		{
			if ((int) $row['event_id'] === $eventId
				&& (int) $row['profile_id'] === $profileId
				&& (int) $row['volunteer_role_id'] === $roleId
				&& \in_array((int) $row['assignment_status'], [1, 2], true))
			{
				$alreadyFills = true;
			}
		}

		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- mirrors slot_count column.
		$slotCount = (int) ($role->slot_count ?? 1);

		if (\in_array($assignmentStatus, [1, 2], true)
			&& !$alreadyFills
			&& $filled >= $slotCount)
		{
			$this->statements[] = 'transactionRollback';

			return 'full';
		}

		$this->volunteers[] = [
			'id'                => \count($this->volunteers) + 1,
			'event_id'          => $eventId,
			'volunteer_role_id' => $roleId,
			'profile_id'        => $profileId,
			'assignment_status' => $assignmentStatus,
		];
		$this->statements[] = 'transactionCommit';

		return 'saved';
	}

	/**
	 * @param   integer  $eventId    Event id.
	 * @param   integer  $roleId     Role id.
	 * @param   integer  $profileId  Profile id.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function toggleVolunteer(int $eventId, int $roleId, int $profileId): string
	{
		$this->statements[] = 'transactionStart';
		$db = new Pr5bLockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);
		$this->statements[] = 'SELECT #__wendfold_event_volunteer_roles FOR UPDATE';

		foreach ($this->volunteers as $row)
		{
			if ((int) $row['event_id'] === $eventId
				&& (int) $row['profile_id'] === $profileId
				&& \in_array((int) $row['assignment_status'], [1, 2, 3], true)
				&& (int) $row['volunteer_role_id'] !== $roleId)
			{
				$this->statements[] = 'transactionRollback';

				return 'alreadyassigned';
			}
		}

		$role = $this->roles[$roleId] ?? null;
		$filled = 0;

		foreach ($this->volunteers as $row)
		{
			if ((int) $row['volunteer_role_id'] === $roleId
				&& \in_array((int) $row['assignment_status'], [1, 2], true))
			{
				$filled++;
			}
		}

		// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- mirrors slot_count column.
		$status = ($role !== null && $filled >= (int) ($role->slot_count ?? 1)) ? 3 : 1;
		$this->volunteers[] = [
			'id'                => \count($this->volunteers) + 1,
			'event_id'          => $eventId,
			'volunteer_role_id' => $roleId,
			'profile_id'        => $profileId,
			'assignment_status' => $status,
		];
		$this->statements[] = 'transactionCommit';

		return $status === 3 ? 'waitlisted' : 'added';
	}
}

/**
 * Group order writer double with one duplicate-key retry.
 *
 * @since  1.0.0
 */
final class GroupOrderDouble
{
	/**
	 * Events keyed by id.
	 *
	 * @var    array<int, object>
	 * @since  1.0.0
	 */
	public array $events = [];

	/**
	 * Existing group_order values keyed by event id.
	 *
	 * @var    array<int, list<int>>
	 * @since  1.0.0
	 */
	public array $groups = [];

	/**
	 * Recorded statements.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * Fail the first insert with duplicate key.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failFirstInsert = false;

	/**
	 * Fail every insert with duplicate key.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failEveryInsert = false;

	/**
	 * Duplicate retries performed.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $duplicateRetries = 0;

	/**
	 * Insert attempts in the current addGroup call.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $insertAttempts = 0;

	/**
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $userId   Actor user id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function addGroup(int $eventId, int $userId): bool|string
	{
		$this->statements = [];
		$this->insertAttempts = 0;
		$this->statements[] = 'transactionStart';
		$snapshot = $this->groups[$eventId] ?? [];
		$db = new Pr5bLockRecordingDatabase;
		$db->eventRows[$eventId] = $this->events[$eventId] ?? null;
		$locked = EventRowLock::lock($db, $eventId);
		$this->statements = array_merge($this->statements, $db->statements);

		if ($locked === null)
		{
			$this->statements[] = 'transactionRollback';

			return 'invalid_event';
		}

		$attempt = 0;
		$pendingRaceOrders = [];

		while (true)
		{
			$this->statements[] = 'MAX group_order';
			$orders = array_merge($this->groups[$eventId] ?? [], $pendingRaceOrders);
			$nextOrder = empty($orders) ? 1 : (max($orders) + 1);

			try
			{
				$this->insertAttempts++;
				$this->statements[] = 'INSERT #__wendfold_event_groups';

				if ($this->failEveryInsert
					|| ($this->failFirstInsert && $this->insertAttempts === 1))
				{
					throw new \RuntimeException(
						'Duplicate entry \'' . $eventId . '-' . $nextOrder . '\' for key \'idx_event_group_order\'',
						1062
					);
				}

				$this->groups[$eventId] = array_merge($snapshot, $pendingRaceOrders, [$nextOrder]);
				$this->statements[] = 'transactionCommit';

				return true;
			}
			catch (\Throwable $error)
			{
				if (DuplicateKey::is($error) && $attempt < 1)
				{
					$attempt++;
					$this->duplicateRetries++;
					$pendingRaceOrders[] = $nextOrder;

					continue;
				}

				$this->groups[$eventId] = $snapshot;
				$this->statements[] = 'transactionRollback';

				return 'db_error';
			}
		}
	}
}

/**
 * createGroups double that rolls back and returns db_error on failure.
 *
 * @since  1.0.0
 */
final class CreateGroupsFailureDouble
{
	/**
	 * Created groups (cleared on rollback).
	 *
	 * @var    list<array{group_order: int, title: string}>
	 * @since  1.0.0
	 */
	public array $groups = [];

	/**
	 * Membership rows (cleared on rollback).
	 *
	 * @var    list<array{profile_id: int, event_group_id: int}>
	 * @since  1.0.0
	 */
	public array $members = [];

	/**
	 * Recorded statements.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * Fail during group row insert.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failOnInsert = false;

	/**
	 * Fail during sync membership ensure (DuplicateKey that cannot reconcile).
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $failOnSync = false;

	/**
	 * Snapshot for rollback.
	 *
	 * @var    array{groups: list<array{group_order: int, title: string}>, members: list<array{profile_id: int, event_group_id: int}>}|null
	 * @since  1.0.0
	 */
	private ?array $snapshot = null;

	/**
	 * @param   integer  $eventId  Event id.
	 * @param   integer  $count    Group count.
	 * @param   integer  $userId   Actor user id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function createGroups(int $eventId, int $count, int $userId): bool|string
	{
		$this->statements = [];
		$this->groups = [];
		$this->members = [];
		$this->statements[] = 'transactionStart';
		$this->snapshot = ['groups' => [], 'members' => []];

		try
		{
			$this->statements[] = 'SELECT #__wendfold_events FOR UPDATE';

			for ($i = 1; $i <= $count; $i++)
			{
				if ($this->failOnInsert)
				{
					throw new \RuntimeException('insert failed', 500);
				}

				$this->groups[] = [
					'group_order' => $i,
					'title'       => 'Group ' . $i,
				];
			}

			if ($this->failOnSync)
			{
				throw new \RuntimeException(
					'Duplicate entry \'4-9\' for key \'idx_event_profile\'',
					1062
				);
			}

			$this->members[] = [
				'profile_id'     => 9,
				'event_group_id' => 1,
			];
			$this->statements[] = 'transactionCommit';

			return true;
		}
		catch (\Throwable $error)
		{
			$this->statements[] = 'transactionRollback';
			$this->groups = $this->snapshot['groups'];
			$this->members = $this->snapshot['members'];

			return 'db_error';
		}
	}
}

/**
 * Group membership ensure double matching main move semantics.
 *
 * Unique key is (event_id, profile_id). Same group is idempotent; a different
 * group moves the row; insert failures that cannot reconcile return db_error.
 *
 * @since  1.0.0
 */
final class GroupMembershipEnsureDouble
{
	/**
	 * Membership rows keyed by profile id.
	 *
	 * @var    array<int, array{id: int, event_group_id: int, assignment_order: int}>
	 * @since  1.0.0
	 */
	public array $members = [];

	/**
	 * Current MAX assignment_order per group id.
	 *
	 * @var    array<int, int>
	 * @since  1.0.0
	 */
	public array $orders = [];

	/**
	 * Insert attempts.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $insertCount = 0;

	/**
	 * Move attempts.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public int $moveCount = 0;

	/**
	 * Whether the outer assign committed.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $committed = false;

	/**
	 * Throw duplicate-key on insert.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $throwOnInsert = false;

	/**
	 * After a duplicate, leave the membership missing so reconcile fails.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public bool $leaveMissingAfterRace = false;

	/**
	 * Next membership id.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $nextId = 100;

	/**
	 * @param   integer  $eventId    Event id.
	 * @param   integer  $groupId    Target group id.
	 * @param   integer  $profileId  Profile id.
	 * @param   integer  $userId     Actor user id.
	 *
	 * @return  string  same, moved, or inserted.
	 *
	 * @since   1.0.0
	 */
	public function ensure(int $eventId, int $groupId, int $profileId, int $userId): string
	{
		$existing = $this->members[$profileId] ?? null;

		if ($existing !== null && (int) $existing['event_group_id'] === $groupId)
		{
			return 'same';
		}

		if ($existing !== null)
		{
			$this->move($profileId, $groupId);

			return 'moved';
		}

		try
		{
			if ($this->throwOnInsert)
			{
				throw new \RuntimeException(
					'Duplicate entry \'' . $eventId . '-' . $profileId . '\' for key \'idx_event_profile\'',
					1062
				);
			}

			$this->insertCount++;
			$order = ($this->orders[$groupId] ?? 0) + 1;
			$this->orders[$groupId] = $order;
			$this->members[$profileId] = [
				'id'               => $this->nextId++,
				'event_group_id'   => $groupId,
				'assignment_order' => $order,
			];

			return 'inserted';
		}
		catch (\Throwable $error)
		{
			if (!DuplicateKey::is($error))
			{
				throw $error;
			}

			if ($this->leaveMissingAfterRace)
			{
				throw $error;
			}

			$existing = $this->members[$profileId] ?? null;

			if ($existing === null)
			{
				throw $error;
			}

			if ((int) $existing['event_group_id'] === $groupId)
			{
				return 'same';
			}

			$this->move($profileId, $groupId);

			return 'moved';
		}
	}

	/**
	 * @param   integer  $eventId    Event id.
	 * @param   integer  $profileId  Profile id.
	 * @param   integer  $groupId    Target group id.
	 * @param   integer  $userId     Actor user id.
	 *
	 * @return  true|string
	 *
	 * @since   1.0.0
	 */
	public function assignGroup(int $eventId, int $profileId, int $groupId, int $userId): bool|string
	{
		$this->committed = false;

		try
		{
			$this->ensure($eventId, $groupId, $profileId, $userId);
			$this->committed = true;

			return true;
		}
		catch (\Throwable $error)
		{
			return 'db_error';
		}
	}

	/**
	 * @param   integer  $profileId  Profile id.
	 * @param   integer  $groupId    Target group id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function move(int $profileId, int $groupId): void
	{
		$this->moveCount++;
		$order = ($this->orders[$groupId] ?? 0) + 1;
		$this->orders[$groupId] = $order;
		$this->members[$profileId]['event_group_id'] = $groupId;
		$this->members[$profileId]['assignment_order'] = $order;
	}
}

/**
 * Records SELECT … FOR UPDATE statements for EventRowLock in PR5b tests.
 *
 * @since  1.0.0
 */
final class Pr5bLockRecordingDatabase implements DatabaseInterface
{
	/**
	 * Event rows keyed by id.
	 *
	 * @var    array<int, object|null>
	 * @since  1.0.0
	 */
	public array $eventRows = [];

	/**
	 * Recorded statement labels.
	 *
	 * @var    list<string>
	 * @since  1.0.0
	 */
	public array $statements = [];

	/**
	 * Bound event id.
	 *
	 * @var    integer|null
	 * @since  1.0.0
	 */
	private ?int $boundId = null;

	/**
	 * Whether the current query is for update.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $forUpdate = false;

	/**
	 * @param   boolean  $new  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false)
	{
		$this->boundId = null;
		$this->forUpdate = false;

		return $this;
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
	 * @param   mixed  $columns  Columns.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function select($columns)
	{
		return $this;
	}

	/**
	 * @param   mixed  $table  Table.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function from($table)
	{
		return $this;
	}

	/**
	 * @param   mixed  $condition  Condition.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function where($condition)
	{
		return $this;
	}

	/**
	 * @param   mixed  $key            Placeholder.
	 * @param   mixed  $value          Value.
	 * @param   mixed  $dataType       Unused.
	 * @param   mixed  $length         Unused.
	 * @param   mixed  $driverOptions  Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function bind($key, &$value = null, $dataType = 'string', $length = 0, $driverOptions = [])
	{
		if ((string) $key === ':id' || (string) $key === ':eventId')
		{
			$this->boundId = (int) $value;
		}

		return $this;
	}

	/**
	 * @param   mixed    $query   Query object or raw SQL with FOR UPDATE.
	 * @param   integer  $offset  Unused.
	 * @param   integer  $limit   Unused.
	 *
	 * @return  self
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query = null, $offset = 0, $limit = 0)
	{
		if (\is_string($query) && str_contains($query, 'FOR UPDATE'))
		{
			$this->forUpdate = true;
			$this->statements[] = 'SELECT #__wendfold_events FOR UPDATE';
		}

		return $this;
	}

	/**
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function __toString(): string
	{
		return 'SELECT id,published,status,event_date FROM #__wendfold_events WHERE id = :id';
	}

	/**
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function loadObject()
	{
		if ($this->boundId === null)
		{
			return null;
		}

		return $this->eventRows[$this->boundId] ?? null;
	}

	/**
	 * @param   mixed  $key  Unused.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null)
	{
		return [];
	}

	/**
	 * @param   boolean  $asSavepoint  Unused.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function transactionStart($asSavepoint = false): void
	{
		$this->statements[] = 'transactionStart';
	}

	/**
	 * @param   mixed  $name  Name.
	 * @param   mixed  $as    Alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null)
	{
		if (\is_array($name))
		{
			return array_map(static fn($part): string => (string) $part, $name);
		}

		return (string) $name;
	}

	/**
	 * @param   mixed    $text    Text.
	 * @param   boolean  $escape  Unused.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quote($text, $escape = true)
	{
		return "'" . (string) $text . "'";
	}

	/**
	 * @param   string  $name       Method.
	 * @param   array   $arguments  Arguments.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public function __call(string $name, array $arguments)
	{
		return $this;
	}
}
