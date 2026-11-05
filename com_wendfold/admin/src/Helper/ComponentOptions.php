<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

\defined('_JEXEC') or die;

/**
 * Defaults stored in the component options.
 *
 * Group ids are filled by the installer when the option is empty. A saved id
 * is kept while that group still exists, and replaced when it does not.
 * Dues, waiver, and reminder values are read with
 * ComponentHelper::getParams('com_wendfold').
 *
 * The group titles in groups() are the install seed. A saved id that still
 * exists is kept, title included. The seed title is used only when that id
 * is empty or no longer exists, and then only for an exact match under the
 * seed parent. created_group_ids lists only groups this install or update
 * inserted, plus earlier ids that still exist. The options form does not
 * edit that list. Uninstall deletes only those ids.
 *
 * @since  1.0.0
 */
final class ComponentOptions
{
	/**
	 * Default suggested dues amount.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const DEFAULT_DUES_AMOUNT = '25.00';

	/**
	 * Default waiver version stored on a new profile.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const DEFAULT_WAIVER_VERSION = '2026';

	/**
	 * Param that lists the usergroup ids Wendfold created.
	 *
	 * Install and update write it. The options form keeps the value but does
	 * not offer it for editing.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public const CREATED_GROUP_IDS = 'created_group_ids';

	/**
	 * Highest Joomla core usergroup id. Ids 1 through 9 are never removed.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public const CORE_GROUP_ID_MAX = 9;

	/**
	 * Install seed for the chapter user groups, parents first.
	 *
	 * parent_param empty means parent_title is a Joomla core group.
	 * Registered is that core parent. It is not one of the stored ids.
	 *
	 * @return  array<int, array{param: string, title: string, parent_param: string, parent_title: string}>
	 *
	 * @since   1.0.0
	 */
	public static function groups(): array
	{
		return [
			[
				'param'        => 'group_member',
				'title'        => 'Chapter Member',
				'parent_param' => '',
				'parent_title' => 'Registered',
			],
			[
				'param'        => 'group_road_captain',
				'title'        => 'Chapter Road Captain',
				'parent_param' => 'group_member',
				'parent_title' => '',
			],
			[
				'param'        => 'group_activities',
				'title'        => 'Chapter Activities',
				'parent_param' => 'group_member',
				'parent_title' => '',
			],
			[
				'param'        => 'group_membership',
				'title'        => 'Chapter Membership',
				'parent_param' => 'group_member',
				'parent_title' => '',
			],
			[
				'param'        => 'group_officer',
				'title'        => 'Chapter Officer',
				'parent_param' => 'group_membership',
				'parent_title' => '',
			],
			[
				'param'        => 'group_webmaster',
				'title'        => 'Chapter Webmaster',
				'parent_param' => 'group_officer',
				'parent_title' => '',
			],
		];
	}

	/**
	 * Group ids that still need to be saved.
	 *
	 * A stored id is kept when that group still exists. An empty option, or
	 * a saved id that is no longer in #__usergroups, is filled from the id
	 * the installer created or found. When $existingIds is omitted, a
	 * positive saved id is treated as still present.
	 *
	 * @param   array       $stored       Current component params.
	 * @param   array       $resolved     Param name => usergroup id.
	 * @param   array|null  $existingIds  Ids present in #__usergroups, or null to keep every positive id.
	 *
	 * @return  array<string, int>
	 *
	 * @since   1.0.0
	 */
	public static function groupIdsToStore(array $stored, array $resolved, ?array $existingIds = null): array
	{
		$present = self::idSet($existingIds);
		$write = [];

		foreach (self::groups() as $group)
		{
			$param = $group['param'];
			$saved = self::positiveInt($stored[$param] ?? 0);

			if ($saved > 0 && ($present === null || isset($present[$saved])))
			{
				continue;
			}

			$id = self::positiveInt($resolved[$param] ?? 0);

			if ($id < 1 || $id === $saved)
			{
				continue;
			}

			$write[$param] = $id;
		}

		return $write;
	}

	/**
	 * Params JSON after filling empty or missing group ids, or null when nothing changes.
	 *
	 * Other saved options are kept. A saved id that still exists is not replaced.
	 *
	 * @param   string      $paramsJson   Current extensions.params value.
	 * @param   array       $resolved     Param name => usergroup id.
	 * @param   array|null  $existingIds  Ids present in #__usergroups, or null to keep every positive id.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function mergeGroupParams(string $paramsJson, array $resolved, ?array $existingIds = null): ?string
	{
		$stored = json_decode($paramsJson, true);

		if (!\is_array($stored))
		{
			$stored = [];
		}

		$write = self::groupIdsToStore($stored, $resolved, $existingIds);

		if ($write === [])
		{
			return null;
		}

		foreach ($write as $param => $id)
		{
			$stored[$param] = $id;
		}

		$encoded = json_encode($stored);

		return \is_string($encoded) ? $encoded : null;
	}

	/**
	 * Saved usergroup id for one option, or zero when it is empty.
	 *
	 * @param   array   $stored  Current component params.
	 * @param   string  $param   Option name, such as group_member.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function storedGroupId(array $stored, string $param): int
	{
		return self::positiveInt($stored[$param] ?? 0);
	}

	/**
	 * Id to keep for one group on install or update.
	 *
	 * A saved id that still exists wins, and its current title is left alone.
	 * Otherwise the exact seed title under the expected parent is used. The
	 * caller creates a group only when both are missing.
	 *
	 * @param   integer  $savedId       Option value, or 0 when the option is empty.
	 * @param   boolean  $savedExists   True when that id is present in #__usergroups.
	 * @param   integer  $titleMatchId  Id with the seed title under the expected parent, or 0.
	 *
	 * @return  array{id: int, create: bool}
	 *
	 * @since   1.0.0
	 */
	public static function resolveGroupChoice(int $savedId, bool $savedExists, int $titleMatchId): array
	{
		if ($savedId > 0 && $savedExists)
		{
			return [
				'id'     => $savedId,
				'create' => false,
			];
		}

		if ($titleMatchId > 0)
		{
			return [
				'id'     => $titleMatchId,
				'create' => false,
			];
		}

		return [
			'id'     => 0,
			'create' => true,
		];
	}

	/**
	 * Id of one title, optionally limited to a parent group.
	 *
	 * Null parent matches the title anywhere and keeps the lowest id. A
	 * parent id matches only a direct child of that group.
	 *
	 * @param   array        $groupsById  id => id, parent_id, title.
	 * @param   string       $title       Exact group title.
	 * @param   integer|null $parentId    Expected parent id, or null for any parent.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function matchingGroupId(array $groupsById, string $title, ?int $parentId): int
	{
		$match = 0;

		foreach ($groupsById as $group)
		{
			if (!\is_array($group))
			{
				continue;
			}

			$id = self::positiveInt($group['id'] ?? 0);

			if ($id < 1 || (string) ($group['title'] ?? '') !== $title)
			{
				continue;
			}

			if ($parentId !== null && (int) ($group['parent_id'] ?? 0) !== $parentId)
			{
				continue;
			}

			if ($match === 0 || $id < $match)
			{
				$match = $id;
			}
		}

		return $match;
	}

	/**
	 * Usergroup ids recorded in created_group_ids.
	 *
	 * Core ids 1-9 are dropped. Commas and a list of ids are both accepted.
	 *
	 * @param   mixed  $value  Stored param value.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public static function parseCreatedGroupIds(mixed $value): array
	{
		if (\is_array($value))
		{
			$parts = $value;
		}
		else
		{
			$text = trim((string) ($value ?? ''));

			if ($text === '')
			{
				return [];
			}

			$split = preg_split('/\s*,\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);
			$parts = \is_array($split) ? $split : [];
		}

		$ids = [];

		foreach ($parts as $part)
		{
			$id = self::positiveInt($part);

			if ($id > self::CORE_GROUP_ID_MAX)
			{
				$ids[$id] = $id;
			}
		}

		$list = array_values($ids);
		sort($list);

		return $list;
	}

	/**
	 * Parent used when a missing seed group is matched or created.
	 *
	 * The resolved parent is used only when Wendfold created that group.
	 * Otherwise the seed title is matched under the seed's own parent.
	 * An option that points at some other group is not used as the parent.
	 *
	 * @param   array  $group        One groups() row.
	 * @param   array  $resolved     Param name => chosen id.
	 * @param   array  $groupsById   id => id, parent_id, title.
	 * @param   array  $createdIds   Ids Wendfold created.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	public static function seedParentId(array $group, array $resolved, array $groupsById, array $createdIds): int
	{
		if (($group['parent_param'] ?? '') === '')
		{
			return self::matchingGroupId($groupsById, (string) ($group['parent_title'] ?? ''), null);
		}

		$created = self::idSet($createdIds);
		$parentParam = (string) $group['parent_param'];
		$parentResolved = self::positiveInt($resolved[$parentParam] ?? 0);

		if ($created !== null && $parentResolved > self::CORE_GROUP_ID_MAX && isset($created[$parentResolved]))
		{
			return $parentResolved;
		}

		$parentGroup = null;

		foreach (self::groups() as $candidate)
		{
			if ($candidate['param'] === $parentParam)
			{
				$parentGroup = $candidate;

				break;
			}
		}

		if ($parentGroup === null)
		{
			return 0;
		}

		$ancestor = self::seedParentId($parentGroup, $resolved, $groupsById, $createdIds);

		if ($ancestor < 1)
		{
			return 0;
		}

		return self::matchingGroupId($groupsById, $parentGroup['title'], $ancestor);
	}

	/**
	 * Ids to keep in created_group_ids after install or update.
	 *
	 * A previously recorded id is kept when that group still exists. An id
	 * inserted on this pass is added. Dead ids are dropped. Title, parent,
	 * and option value never mark a group as created. Core ids are dropped.
	 *
	 * @param   array  $storedCreated  Ids already recorded.
	 * @param   array  $groupsById     id => id, parent_id, title.
	 * @param   array  $insertedIds    Ids inserted on this pass.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public static function createdIdsToRemember(
		array $storedCreated,
		array $groupsById,
		array $insertedIds
	): array
	{
		$groups = [];

		foreach ($groupsById as $group)
		{
			if (!\is_array($group))
			{
				continue;
			}

			$id = self::positiveInt($group['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			$groups[$id] = true;
		}

		$keep = [];

		foreach ([$storedCreated, $insertedIds] as $set)
		{
			foreach ($set as $candidate)
			{
				$id = self::positiveInt($candidate);

				if ($id > self::CORE_GROUP_ID_MAX && isset($groups[$id]))
				{
					$keep[$id] = $id;
				}
			}
		}

		$list = array_values($keep);
		sort($list);

		return $list;
	}

	/**
	 * Nested-set positions for a user-group tree.
	 *
	 * Joomla stores lft and rgt from parent_id, with siblings ordered by
	 * title. Rows whose lft and rgt are zero are replaced by that placement.
	 * Running it again yields the same positions.
	 *
	 * @param   array  $groups  Rows with id, parent_id, and title.
	 *
	 * @return  array<int, array{id: int, parent_id: int, title: string, lft: int, rgt: int}>
	 *
	 * @since   1.0.0
	 */
	public static function nestedPositions(array $groups): array
	{
		$nodes = [];

		foreach ($groups as $group)
		{
			if (!\is_array($group))
			{
				continue;
			}

			$id = self::positiveInt($group['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			$nodes[$id] = [
				'id'        => $id,
				'parent_id' => self::positiveInt($group['parent_id'] ?? 0),
				'title'     => (string) ($group['title'] ?? ''),
				'lft'       => 0,
				'rgt'       => 0,
			];
		}

		$children = [];

		foreach ($nodes as $id => $node)
		{
			$children[$node['parent_id']][] = $id;
		}

		foreach ($children as $parentId => $childIds)
		{
			usort(
				$childIds,
				static function (int $left, int $right) use ($nodes): int {
					return strcmp($nodes[$left]['title'], $nodes[$right]['title']);
				}
			);
			$children[$parentId] = $childIds;
		}

		self::assignNestedPositions($nodes, $children, 0, 0);

		return $nodes;
	}

	/**
	 * Groups a user belongs to, including ancestors.
	 *
	 * This is the nested-set rule Joomla uses: a group contains the user
	 * when its lft and rgt span one of the user's direct groups. Zero
	 * bounds make every group match.
	 *
	 * @param   array  $positions  Result of nestedPositions().
	 * @param   array  $directIds  Groups the user is mapped to.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public static function groupsForUser(array $positions, array $directIds): array
	{
		$found = [];

		foreach ($directIds as $directId)
		{
			$directId = self::positiveInt($directId);

			if ($directId < 1 || !isset($positions[$directId]) || !\is_array($positions[$directId]))
			{
				continue;
			}

			$left = (int) ($positions[$directId]['lft'] ?? 0);
			$right = (int) ($positions[$directId]['rgt'] ?? 0);

			foreach ($positions as $id => $node)
			{
				if (!\is_array($node))
				{
					continue;
				}

				$nodeLeft = (int) ($node['lft'] ?? 0);
				$nodeRight = (int) ($node['rgt'] ?? 0);

				if ($nodeLeft <= $left && $nodeRight >= $right)
				{
					$found[(int) $id] = (int) $id;
				}
			}
		}

		$list = array_values($found);
		sort($list);

		return $list;
	}

	/**
	 * Params JSON after recording created group ids, or null when nothing changes.
	 *
	 * Other saved options, including the group fields, are kept.
	 *
	 * @param   string  $paramsJson  Current extensions.params value.
	 * @param   array   $createdIds  Ids Wendfold created.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	public static function mergeCreatedGroupIds(string $paramsJson, array $createdIds): ?string
	{
		$stored = json_decode($paramsJson, true);

		if (!\is_array($stored))
		{
			$stored = [];
		}

		$normalized = self::parseCreatedGroupIds($createdIds);
		$current = self::parseCreatedGroupIds($stored[self::CREATED_GROUP_IDS] ?? null);

		if ($current === $normalized)
		{
			return null;
		}

		if ($normalized === [])
		{
			unset($stored[self::CREATED_GROUP_IDS]);
		}
		else
		{
			$stored[self::CREATED_GROUP_IDS] = implode(',', $normalized);
		}

		$encoded = json_encode($stored);

		return \is_string($encoded) ? $encoded : null;
	}

	/**
	 * Ids to delete on uninstall, children before parents.
	 *
	 * Pass every user group so a child Wendfold did not create is visible.
	 * A recorded id is deleted only when it is not a core, root, or Public
	 * group, it has no members, and every group nested under it is also
	 * deleted. Foreign ids and ids 1-9 are left out even if they were recorded.
	 *
	 * @param   array  $createdIds    Ids Wendfold created.
	 * @param   array  $groups        Every user group: id, parent_id, title.
	 * @param   array  $memberCounts  Group id => member count. A missing id has none.
	 *
	 * @return  array<int, int>
	 *
	 * @since   1.0.0
	 */
	public static function groupIdsToDelete(array $createdIds, array $groups, array $memberCounts): array
	{
		$byId = self::indexGroups($groups);
		$created = [];

		foreach (self::parseCreatedGroupIds($createdIds) as $id)
		{
			if (isset($byId[$id]) && !self::groupIsProtected($byId[$id]))
			{
				$created[$id] = true;
			}
		}

		$children = [];

		foreach ($byId as $id => $group)
		{
			$parentId = $group['parent_id'];

			if ($parentId > 0 && isset($byId[$parentId]))
			{
				$children[$parentId][] = $id;
			}
		}

		$deletable = [];

		foreach (array_keys($created) as $id)
		{
			if (self::groupCanBeDeleted($id, $created, $children, $memberCounts, $byId, []))
			{
				$deletable[$id] = true;
			}
		}

		$ordered = array_keys($deletable);
		$depth = [];

		foreach ($ordered as $id)
		{
			$depth[$id] = self::groupDepth($id, $byId);
		}

		usort(
			$ordered,
			static function (int $left, int $right) use ($depth): int {
				if ($depth[$left] !== $depth[$right])
				{
					return $depth[$right] <=> $depth[$left];
				}

				return $right <=> $left;
			}
		);

		return $ordered;
	}

	/**
	 * The two renewal reminder windows.
	 *
	 * The day counts come from the options. The profile columns stay
	 * reminder_60_sent and reminder_30_sent. A missing or out-of-range day
	 * count uses 60 and 30. The first count must be more than 10 days before
	 * the second, so the two windows do not overlap. A pair that fails that
	 * check uses 60 and 30. The upper bound matches the options form.
	 *
	 * @param   mixed  $first   reminder_days_60.
	 * @param   mixed  $second  reminder_days_30.
	 *
	 * @return  array<int, array{days: int, column: string, result: string}>
	 *
	 * @since   1.0.0
	 */
	public static function reminderWindows(mixed $first, mixed $second): array
	{
		[$firstDays, $secondDays] = self::reminderDays($first, $second);

		return [
			[
				'days'   => $firstDays,
				'column' => 'reminder_60_sent',
				'result' => 'sent_60',
			],
			[
				'days'   => $secondDays,
				'column' => 'reminder_30_sent',
				'result' => 'sent_30',
			],
		];
	}

	/**
	 * One reminder per member when a profile matches one or both windows.
	 *
	 * The nearer window is the second one. A member who matches both is sent
	 * that reminder only, and both sent columns are listed so the earlier
	 * reminder is not sent on a later run.
	 *
	 * @param   array  $windows    Result of reminderWindows().
	 * @param   array  $qualified  Profile id => window indexes the member matched.
	 *
	 * @return  array<int, array{index: int, days: int, result: string, columns: array<int, string>}>
	 *
	 * @since   1.0.0
	 */
	public static function reminderSelection(array $windows, array $qualified): array
	{
		$choices = [];

		foreach ($qualified as $profileId => $indexes)
		{
			if (!\is_array($indexes))
			{
				continue;
			}

			$matched = [];

			foreach ($indexes as $index)
			{
				$index = (int) $index;

				if (!isset($windows[$index]) || isset($matched[$index]))
				{
					continue;
				}

				$matched[$index] = true;
			}

			if ($matched === [])
			{
				continue;
			}

			$sendIndex = max(array_keys($matched));
			$columns = [];

			foreach (array_keys($matched) as $index)
			{
				$columns[] = (string) $windows[$index]['column'];
			}

			$choices[(int) $profileId] = [
				'index'   => $sendIndex,
				'days'    => (int) $windows[$sendIndex]['days'],
				'result'  => (string) $windows[$sendIndex]['result'],
				'columns' => $columns,
			];
		}

		return $choices;
	}

	/**
	 * Suggested amount for a new dues payment.
	 *
	 * @param   mixed  $configured  dues_amount option.
	 *
	 * @return  string  Two decimal places.
	 *
	 * @since   1.0.0
	 */
	public static function duesAmount(mixed $configured): string
	{
		if (!is_numeric($configured) || (float) $configured < 0)
		{
			return self::DEFAULT_DUES_AMOUNT;
		}

		return number_format((float) $configured, 2, '.', '');
	}

	/**
	 * Waiver version to store for one profile.
	 *
	 * The option fills a blank value only when the record is new. An existing
	 * profile keeps its current value, including a blank one.
	 *
	 * @param   integer  $id          Profile id. Zero is a new record.
	 * @param   mixed    $current     Value already on the record or form.
	 * @param   mixed    $configured  waiver_version option.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function waiverVersionForRecord(int $id, mixed $current, mixed $configured): string
	{
		$version = trim((string) $current);

		if ($id !== 0 || $version !== '')
		{
			return $version;
		}

		return self::waiverVersion($configured);
	}

	/**
	 * Waiver version for a new profile when the field was left blank.
	 *
	 * @param   mixed  $configured  waiver_version option.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function waiverVersion(mixed $configured): string
	{
		$version = trim((string) $configured);

		if ($version === '')
		{
			return self::DEFAULT_WAIVER_VERSION;
		}

		if (\strlen($version) > 20)
		{
			return substr($version, 0, 20);
		}

		return $version;
	}

	/**
	 * Write lft and rgt for one node and its children. Returns the next free edge.
	 *
	 * @param   array    $nodes     Group rows, updated in place.
	 * @param   array    $children  Parent id => child ids, title order.
	 * @param   integer  $parentId  Node being placed. Zero is the missing root.
	 * @param   integer  $left      Left edge for this node.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function assignNestedPositions(array &$nodes, array $children, int $parentId, int $left): int
	{
		$right = $left + 1;

		foreach ($children[$parentId] ?? [] as $childId)
		{
			$right = self::assignNestedPositions($nodes, $children, (int) $childId, $right);
		}

		if (isset($nodes[$parentId]))
		{
			$nodes[$parentId]['lft'] = $left;
			$nodes[$parentId]['rgt'] = $right;
		}

		return $right + 1;
	}

	/**
	 * Groups keyed by id.
	 *
	 * @param   array  $groups  Rows with id, parent_id, and title.
	 *
	 * @return  array<int, array{id: int, parent_id: int, title: string}>
	 *
	 * @since   1.0.0
	 */
	private static function indexGroups(array $groups): array
	{
		$byId = [];

		foreach ($groups as $group)
		{
			if (!\is_array($group))
			{
				continue;
			}

			$id = self::positiveInt($group['id'] ?? 0);

			if ($id < 1)
			{
				continue;
			}

			$byId[$id] = [
				'id'        => $id,
				'parent_id' => self::positiveInt($group['parent_id'] ?? 0),
				'title'     => (string) ($group['title'] ?? ''),
			];
		}

		return $byId;
	}

	/**
	 * Whether uninstall must leave this group in place.
	 *
	 * Core ids 1-9, the root group, and Public are protected.
	 *
	 * @param   array{id: int, parent_id: int, title: string}  $group  One user group.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function groupIsProtected(array $group): bool
	{
		if ($group['id'] <= self::CORE_GROUP_ID_MAX || $group['parent_id'] === 0)
		{
			return true;
		}

		return strcasecmp($group['title'], 'Public') === 0;
	}

	/**
	 * Whether this created group and every group under it can be removed.
	 *
	 * @param   integer              $id             Group id.
	 * @param   array<int, true>     $created        Created ids that are not protected.
	 * @param   array<int, int[]>    $children       Parent id => child ids.
	 * @param   array                $memberCounts   Group id => member count.
	 * @param   array                $byId           Groups keyed by id.
	 * @param   array<int, true>     $stack          Ids already entered, to stop a cycle.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private static function groupCanBeDeleted(
		int $id,
		array $created,
		array $children,
		array $memberCounts,
		array $byId,
		array $stack
	): bool
	{
		if (!isset($created[$id]) || isset($stack[$id]) || !isset($byId[$id]))
		{
			return false;
		}

		if (self::groupIsProtected($byId[$id]) || (int) ($memberCounts[$id] ?? 0) > 0)
		{
			return false;
		}

		$stack[$id] = true;

		foreach ($children[$id] ?? [] as $childId)
		{
			if (!self::groupCanBeDeleted((int) $childId, $created, $children, $memberCounts, $byId, $stack))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Nesting depth, starting at 1 for a root group.
	 *
	 * @param   integer  $id    Group id.
	 * @param   array    $byId  Groups keyed by id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function groupDepth(int $id, array $byId): int
	{
		$depth = 1;
		$seen = [];

		while (isset($byId[$id]) && !isset($seen[$id]))
		{
			$seen[$id] = true;
			$parentId = $byId[$id]['parent_id'];

			if ($parentId < 1 || !isset($byId[$parentId]))
			{
				break;
			}

			$depth++;
			$id = $parentId;
		}

		return $depth;
	}

	/**
	 * Positive ids keyed by id, or null when the caller did not pass a list.
	 *
	 * @param   array|null  $ids  Ids, or null when presence is unknown.
	 *
	 * @return  array<int, true>|null
	 *
	 * @since   1.0.0
	 */
	private static function idSet(?array $ids): ?array
	{
		if ($ids === null)
		{
			return null;
		}

		$set = [];

		foreach ($ids as $id)
		{
			$id = self::positiveInt($id);

			if ($id > 0)
			{
				$set[$id] = true;
			}
		}

		return $set;
	}

	/**
	 * A positive integer, or zero.
	 *
	 * @param   mixed  $value  Stored or resolved id.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function positiveInt(mixed $value): int
	{
		if (\is_int($value))
		{
			return max(0, $value);
		}

		if (\is_string($value) && preg_match('/^\d+$/', $value) === 1)
		{
			return (int) $value;
		}

		return 0;
	}

	/**
	 * A valid reminder pair, or the defaults 60 and 30.
	 *
	 * The first day count must be greater than the second by more than 10
	 * so the two windows, each widened by five days on either side, do not
	 * overlap. Each count must also sit inside 1 to 365.
	 *
	 * @param   mixed  $first   reminder_days_60.
	 * @param   mixed  $second  reminder_days_30.
	 *
	 * @return  array{0: int, 1: int}
	 *
	 * @since   1.0.0
	 */
	private static function reminderDays(mixed $first, mixed $second): array
	{
		$firstDays = self::reminderDayCount($first, 60);
		$secondDays = self::reminderDayCount($second, 30);

		if ($firstDays > $secondDays && ($firstDays - $secondDays) > 10)
		{
			return [$firstDays, $secondDays];
		}

		return [60, 30];
	}

	/**
	 * Day count inside the options form range.
	 *
	 * @param   mixed    $value    Posted or saved day count.
	 * @param   integer  $default  60 or 30.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private static function reminderDayCount(mixed $value, int $default): int
	{
		if (!is_numeric($value))
		{
			return $default;
		}

		$days = (int) $value;

		if ($days < 1 || $days > 365)
		{
			return $default;
		}

		return $days;
	}
}
