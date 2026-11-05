<?php

/**
 * Wendfold - Chapter Management System
 * Site Router
 *
 * Provides component-aware routing so Wendfold links can build clean
 * menu-backed SEF URLs while still degrading gracefully when SEF or URL
 * rewriting are disabled in Joomla.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterInterface;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use Wendfold\Component\Wendfold\Site\Helper\AttendanceEventAccess;
use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;

class Router implements RouterInterface
{
	private AbstractMenu $menu;

	private DatabaseInterface $db;

	/**
	 * Application that owns the current visitor.
	 *
	 * @var    CMSApplicationInterface
	 * @since  1.0.0
	 */
	private CMSApplicationInterface $app;

	/** @var array<int, object> */
	private array $componentMenuItems = [];

	/**
	 * Event rows used to build and resolve aliases. Null until the first read.
	 *
	 * @var    array<int, object>|null
	 * @since  1.0.0
	 */
	private ?array $eventRows = null;

	/**
	 * Whether the current visitor has been read.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $viewerResolved = false;

	/**
	 * Audience flags for the current visitor, or null for a guest.
	 *
	 * @var    array<string, boolean>|null
	 * @since  1.0.0
	 */
	private ?array $viewerFlags = null;

	/**
	 * Published profile id of the current visitor, or 0.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	private int $viewerProfileId = 0;

	/**
	 * Whether the current visitor may manage the component.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	private bool $viewerIsAdmin = false;

	/**
	 * Path words the events parser treats as a view, not an event alias.
	 *
	 * The alias generator does not store one of these on its own. Parsing
	 * still checks the word before any alias lookup, so a stored alias
	 * cannot take the view.
	 *
	 * @var    string[]
	 * @since  1.0.0
	 */
	private const RESERVED_EVENT_SEGMENTS = [
		'trip',
		'rcattendance',
		'meetingattendance',
		'volunteerboard',
	];

	public function __construct(
		CMSApplicationInterface $application,
		AbstractMenu $menu,
		?CategoryFactoryInterface $categoryFactory,
		DatabaseInterface $db
	) {
		$this->app = $application;
		$this->menu = $this->resolveMenu($application, $menu);
		$this->db = $db;
	}

	public function preprocess($query)
	{
		if (!is_array($query) || !empty($query['Itemid']))
		{
			return $query;
		}

		$view = isset($query['view']) ? (string) $query['view'] : '';

		if ($view === '')
		{
			return $query;
		}

		$exactMenuItem = $this->findExactMenuItemForView($view);

		if ($exactMenuItem)
		{
			$query['Itemid'] = (int) $exactMenuItem->id;

			return $query;
		}

		$active = $this->menu->getActive();

		if ($active && $this->isWendfoldMenuItem($active) && $this->menuItemSupportsView($active, $view))
		{
			$query['Itemid'] = (int) $active->id;

			return $query;
		}

		$menuItem = $this->findFallbackMenuItemForView($view);

		if ($menuItem)
		{
			$query['Itemid'] = (int) $menuItem->id;
		}

		return $query;
	}

	public function build(&$query): array
	{
		$segments = [];
		$view = isset($query['view']) ? (string) $query['view'] : '';

		// Leave task URLs and non-view routes untouched.
		if ($view === '' || !empty($query['task']))
		{
			return $segments;
		}

		$menuItem = !empty($query['Itemid']) ? $this->menu->getItem((int) $query['Itemid']) : null;
		$menuView = $this->getMenuItemView($menuItem);

		switch ($view)
		{
			case 'events':
			case 'dashboard':
			case 'awards':
			case 'myrsvps':
			case 'myactivities':
			case 'officers':
				if ($menuView !== $view)
				{
					$segments[] = $view;
				}

				unset($query['view']);
				break;

			case 'profileedit':
				if ($menuView === 'profileedit')
				{
					unset($query['view']);
					break;
				}

				if ($menuView !== 'dashboard')
				{
					$segments[] = 'dashboard';
				}

				$segments[] = 'profileedit';
				unset($query['view']);
				break;

			case 'checkin':
				if ($menuView !== 'checkin')
				{
					$segments[] = 'checkin';
				}

				if (!empty($query['token']))
				{
					$segments[] = (string) $query['token'];
					unset($query['token']);
				}

				unset($query['view']);
				break;

			case 'event':
				if ($menuView !== 'events')
				{
					$segments[] = 'events';
				}

				if (!empty($query['id']))
				{
					$segments[] = $this->buildEventSegment((int) $query['id']);
					unset($query['id']);
				}

				unset($query['view']);
				break;

			case 'trip':
			case 'rcattendance':
			case 'meetingattendance':
			case 'volunteerboard':
				if ($menuView !== 'events')
				{
					$segments[] = 'events';
				}

				$segments[] = $view;

				$key = $view === 'trip' ? 'id' : 'event_id';

				if (!empty($query[$key]))
				{
					$segments[] = $this->buildEventSegment((int) $query[$key]);
					unset($query[$key]);
				}

				unset($query['view']);
				break;
		}

		return $segments;
	}

	/**
	 * Read path segments back into query variables.
	 *
	 * The active menu item is the one the site router set before this runs.
	 * Itemid is not on the request input yet. Each segment that is understood
	 * is removed so a leftover segment still becomes a 404.
	 *
	 * @param   array  $segments  Remaining path segments, updated in place.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function parse(&$segments): array
	{
		$segments = array_values(array_filter($segments, static fn ($segment) => $segment !== ''));
		$vars = [];

		$menuItem = $this->menu->getActive();
		$menuItem = $this->isWendfoldMenuItem($menuItem) ? $menuItem : null;
		$menuView = $this->getMenuItemView($menuItem);

		if ($menuView === 'events')
		{
			return $this->parseEventsContext($segments);
		}

		if ($menuView === 'dashboard')
		{
			return $this->parseDashboardContext($segments);
		}

		if ($menuView === 'checkin')
		{
			return $this->parseCheckinContext($segments);
		}

		if ($segments === [])
		{
			return $vars;
		}

		$first = (string) array_shift($segments);
		$reserved = $this->parseReservedView($first, $segments);

		if ($reserved !== null)
		{
			return $reserved;
		}

		switch ($first)
		{
			case 'events':
				return $this->parseEventsContext($segments);

			case 'dashboard':
				return $this->parseDashboardContext($segments);

			case 'awards':
			case 'myrsvps':
			case 'myactivities':
			case 'officers':
				$vars['view'] = $first;

				return $vars;

			case 'profileedit':
				$vars['view'] = 'profileedit';

				return $vars;

			case 'checkin':
				$vars['view'] = 'checkin';

				if ($segments !== [])
				{
					$vars['token'] = (string) array_shift($segments);
				}

				return $vars;
		}

		// A single remaining segment with no explicit prefix is an event.
		$vars['view'] = 'event';
		$vars['id'] = $this->extractIdFromSegment($first);

		return $vars;
	}

	/**
	 * Read segments under the events menu, or after an events prefix.
	 *
	 * @param   array  $segments  Remaining path segments, updated in place.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function parseEventsContext(array &$segments): array
	{
		$vars = ['view' => 'events'];

		if ($segments === [])
		{
			return $vars;
		}

		$first = (string) array_shift($segments);
		$reserved = $this->parseReservedView($first, $segments);

		if ($reserved !== null)
		{
			return $reserved;
		}

		$vars['view'] = 'event';
		$vars['id'] = $this->extractIdFromSegment($first);

		return $vars;
	}

	private function parseDashboardContext(array &$segments): array
	{
		if (empty($segments))
		{
			return ['view' => 'dashboard'];
		}

		$first = (string) $segments[0];

		if ($first === 'profileedit' || in_array($first, ['dashboard', 'myrsvps', 'myactivities', 'awards', 'officers'], true))
		{
			array_shift($segments);

			return ['view' => $first];
		}

		return ['view' => 'dashboard'];
	}

	private function parseCheckinContext(array &$segments): array
	{
		$vars = ['view' => 'checkin'];

		if (!empty($segments))
		{
			$vars['token'] = (string) array_shift($segments);
		}

		return $vars;
	}

	private function resolveMenu(CMSApplicationInterface $application, AbstractMenu $menu): AbstractMenu
	{
		$isAdministrator = method_exists($application, 'isClient')
			? $application->isClient('administrator')
			: (method_exists($application, 'getName') && $application->getName() === 'administrator');

		if ($isAdministrator && method_exists($application, 'getMenu'))
		{
			try
			{
				$siteMenu = $application->getMenu('site');

				if ($siteMenu)
				{
					$siteMenu->load();

					return $siteMenu;
				}
			}
			catch (\Throwable $e)
			{
				// Fall back to the injected menu if the site menu is unavailable.
			}
		}

		return $menu;
	}

	private function getComponentMenuItems(): array
	{
		if ($this->componentMenuItems !== [])
		{
			return $this->componentMenuItems;
		}

		$items = (array) $this->menu->getItems('component', 'com_wendfold');

		foreach ($items as $item)
		{
			if ($item)
			{
				$this->componentMenuItems[(int) $item->id] = $item;
			}
		}

		return $this->componentMenuItems;
	}

	private function findExactMenuItemForView(string $view): ?object
	{
		foreach ($this->getComponentMenuItems() as $item)
		{
			if ($this->getMenuItemView($item) === $view)
			{
				return $item;
			}
		}

		return null;
	}

	private function findFallbackMenuItemForView(string $view): ?object
	{
		foreach ($this->getPreferredMenuViews($view) as $preferredView)
		{
			foreach ($this->getComponentMenuItems() as $item)
			{
				if ($this->getMenuItemView($item) === $preferredView)
				{
					return $item;
				}
			}
		}

		return null;
	}

	private function getPreferredMenuViews(string $view): array
	{
		return match ($view) {
			'event', 'trip', 'rcattendance', 'meetingattendance', 'volunteerboard' => ['events'],
			'profileedit' => ['profileedit', 'dashboard'],
			'myrsvps' => ['myrsvps', 'dashboard'],
			'myactivities' => ['myactivities', 'dashboard'],
			default => [$view],
		};
	}

	private function menuItemSupportsView(object $menuItem, string $view): bool
	{
		$menuView = $this->getMenuItemView($menuItem);

		if ($menuView === $view)
		{
			return true;
		}

		return match ($view) {
			'event', 'trip', 'rcattendance', 'meetingattendance', 'volunteerboard' => $menuView === 'events',
			'profileedit' => in_array($menuView, ['profileedit', 'dashboard'], true),
			'myrsvps' => in_array($menuView, ['myrsvps', 'dashboard'], true),
			'myactivities' => in_array($menuView, ['myactivities', 'dashboard'], true),
			default => false,
		};
	}

	private function isWendfoldMenuItem(?object $menuItem): bool
	{
		return $menuItem
			&& (($menuItem->component ?? '') === 'com_wendfold'
				|| (($menuItem->query['option'] ?? '') === 'com_wendfold'));
	}

	private function getMenuItemView(?object $menuItem): string
	{
		if (!$menuItem || !isset($menuItem->query) || !is_array($menuItem->query))
		{
			return '';
		}

		return (string) ($menuItem->query['view'] ?? '');
	}

	/**
	 * Build the path segment for one event.
	 *
	 * The segment is the alias stored on the event. Generation may already
	 * have shortened that alias on a hyphen so it fits the column. The
	 * segment is that stored value, not the title and not a longer slug.
	 * An empty alias, which check() does not store, falls back to the
	 * numeric id.
	 *
	 * @param   integer  $eventId  Event primary key.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function buildEventSegment(int $eventId): string
	{
		if ($eventId <= 0)
		{
			return '0';
		}

		foreach ($this->loadEventRows() as $row)
		{
			if ((int) ($row->id ?? 0) !== $eventId)
			{
				continue;
			}

			$alias = (string) ($row->alias ?? '');

			if ($alias !== '')
			{
				return $alias;
			}

			break;
		}

		return (string) $eventId;
	}

	/**
	 * Resolve one path segment to an event id.
	 *
	 * The segment is compared exactly with the stored alias first, including
	 * an alias that is only digits, the same match as the utf8mb4_bin
	 * unique key. A row that owns the alias but is unpublished, trashed, or
	 * hidden from this visitor resolves to 0, and it is not given to another
	 * event. Attendance views are the exception: unpublished and archived
	 * resolve so AttendanceEventAccess can choose the page message. When no
	 * row owns the alias, a segment that is only digits is that id under the
	 * same visibility checks. Any other segment is a missing page.
	 *
	 * @param   string|null  $segment        Path segment, or null when it was absent.
	 * @param   boolean      $forAttendance  Resolve unpublished/archived for attendance views.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function extractIdFromSegment(?string $segment, bool $forAttendance = false): int
	{
		$segment = trim((string) $segment);

		if ($segment === '')
		{
			return 0;
		}

		$aliasId = $this->idForAlias($segment, $forAttendance);

		if ($aliasId !== null)
		{
			return $aliasId;
		}

		if (preg_match('/^\d+$/', $segment) === 1)
		{
			return $this->visibleId((int) $segment, $forAttendance);
		}

		return 0;
	}

	/**
	 * Resolve an exact alias.
	 *
	 * Null means no row stores this alias, so the caller may try an id
	 * fallback. Zero means a row stores it and the visitor may not see
	 * that row. The id is returned only for an event this visitor may open.
	 * Attendance views also resolve unpublished and archived events.
	 *
	 * @param   string   $segment        Path segment.
	 * @param   boolean  $forAttendance  Resolve unpublished/archived for attendance views.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private function idForAlias(string $segment, bool $forAttendance = false): ?int
	{
		if ($segment === '')
		{
			return null;
		}

		foreach ($this->loadEventRows() as $row)
		{
			$alias = (string) ($row->alias ?? '');

			if ($alias === '' || $alias !== $segment)
			{
				continue;
			}

			if (!$this->rowIsVisible($row, $forAttendance))
			{
				return 0;
			}

			return (int) ($row->id ?? 0);
		}

		return null;
	}

	/**
	 * Resolve an id when that event is visible to the current visitor.
	 *
	 * A missing or trashed row, and a row outside the visitor's audience,
	 * returns 0. Unpublished and archived return 0 except for attendance
	 * views, which resolve them so the attendance gate can message.
	 *
	 * @param   integer  $eventId        Event primary key.
	 * @param   boolean  $forAttendance  Resolve unpublished/archived for attendance views.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	private function visibleId(int $eventId, bool $forAttendance = false): int
	{
		if ($eventId <= 0)
		{
			return 0;
		}

		foreach ($this->loadEventRows() as $row)
		{
			if ((int) ($row->id ?? 0) !== $eventId)
			{
				continue;
			}

			return $this->rowIsVisible($row, $forAttendance) ? $eventId : 0;
		}

		return 0;
	}

	/**
	 * Load event rows once.
	 *
	 * The alias column is utf8mb4_bin. Callers compare it with a binary
	 * exact match, so the lookup does not fold case.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	private function loadEventRows(): array
	{
		if ($this->eventRows !== null)
		{
			return $this->eventRows;
		}

		$this->eventRows = [];

		try
		{
			$query = $this->db->createQuery()
				->select(
					[
						$this->db->quoteName('id'),
						$this->db->quoteName('alias'),
						$this->db->quoteName('title'),
						$this->db->quoteName('start_date'),
						$this->db->quoteName('event_date'),
						$this->db->quoteName('published'),
						$this->db->quoteName('audience_scope'),
						$this->db->quoteName('volunteer_manager_id'),
						$this->db->quoteName('volunteer_backup_id'),
					]
				)
				->from($this->db->quoteName('#__wendfold_events'));

			$this->db->setQuery($query);
			$rows = (array) $this->db->loadObjectList();
		}
		catch (\Throwable $e)
		{
			return $this->eventRows;
		}

		foreach ($rows as $row)
		{
			if (is_object($row))
			{
				$this->eventRows[] = $row;
			}
		}

		return $this->eventRows;
	}

	/**
	 * Whether this row is published.
	 *
	 * A row with no published column is included. The catalogue query selects
	 * the column. Fixtures that omit it are published events.
	 *
	 * @param   object  $row  Event row from #__wendfold_events.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function rowIsPublished(object $row): bool
	{
		if (!property_exists($row, 'published'))
		{
			return true;
		}

		return (int) $row->published === 1;
	}

	/**
	 * Whether this row's published state may be resolved for the given view.
	 *
	 * Attendance views accept published, archived, and unpublished. Every
	 * other view stays published-only.
	 *
	 * @param   object   $row            Event row from #__wendfold_events.
	 * @param   boolean  $forAttendance  Attendance view resolution.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function rowIsPublishedForContext(object $row, bool $forAttendance): bool
	{
		if (!$forAttendance)
		{
			return $this->rowIsPublished($row);
		}

		if (!property_exists($row, 'published'))
		{
			return true;
		}

		return AttendanceEventAccess::isRouterResolvable((int) $row->published);
	}

	/**
	 * Whether the current visitor may open this event for the given view.
	 *
	 * Published state is required for normal views. Attendance views also
	 * resolve unpublished and archived so AttendanceEventAccess can message.
	 * Audience scope uses the same rule as the event page, including a
	 * volunteer manager for the event and a component administrator.
	 *
	 * @param   object   $row            Event row.
	 * @param   boolean  $forAttendance  Attendance view resolution.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function rowIsVisible(object $row, bool $forAttendance = false): bool
	{
		if (!$this->rowIsPublishedForContext($row, $forAttendance))
		{
			return false;
		}

		$this->resolveViewer();

		$stored = get_object_vars($row);
		$scope = (int) ($stored['audience_scope'] ?? AudienceHelper::SCOPE_ALL_VISITORS);
		$profileId = $this->viewerProfileId;
		$managerId = (int) ($stored['volunteer_manager_id'] ?? 0);
		$backupId = (int) ($stored['volunteer_backup_id'] ?? 0);
		$isManager = $profileId > 0 && ($profileId === $managerId || $profileId === $backupId);

		return AudienceHelper::canSeeEvent($scope, $this->viewerFlags, $isManager, $this->viewerIsAdmin);
	}

	/**
	 * Read the current visitor once.
	 *
	 * A guest, or a signed-in account with no published profile, keeps the
	 * null flags. Those visitors see All Visitors events only, unless they
	 * may manage the component.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function resolveViewer(): void
	{
		if ($this->viewerResolved)
		{
			return;
		}

		$this->viewerResolved = true;
		$user = $this->app->getIdentity();

		if ($user === null)
		{
			return;
		}

		$userId = (int) $user->id;

		if ($userId <= 0)
		{
			return;
		}

		$this->viewerIsAdmin = $user->authorise('core.admin', 'com_wendfold')
			|| $user->authorise('core.manage', 'com_wendfold');

		$profile = $this->loadProfileRow($userId);

		if ($profile === null)
		{
			return;
		}

		$stored = get_object_vars($profile);
		$this->viewerProfileId = (int) ($stored['profile_id'] ?? 0);
		$this->viewerFlags = AudienceHelper::flagsFromProfile($profile);
	}

	/**
	 * Load the published profile for one user id.
	 *
	 * @param   integer  $userId  Joomla user id.
	 *
	 * @return  object|null  Profile flags row, or null when there is none.
	 *
	 * @since   1.0.0
	 */
	private function loadProfileRow(int $userId): ?object
	{
		try
		{
			$query = $this->db->createQuery()
				->select(
					[
						'p.id AS profile_id',
						'p.user_id',
						'p.is_road_captain',
						'CASE WHEN o.id IS NULL THEN 0 ELSE 1 END AS is_officer',
					]
				)
				->from($this->db->quoteName('#__wendfold_profiles', 'p'))
				->join(
					'LEFT',
					$this->db->quoteName('#__wendfold_officers', 'o')
						. ' ON o.profile_id = p.id AND o.is_current = 1 AND o.published = 1'
				)
				->where($this->db->quoteName('p.user_id') . ' = ' . $userId)
				->where($this->db->quoteName('p.published') . ' = 1');

			$this->db->setQuery($query);
			$rows = (array) $this->db->loadObjectList();
		}
		catch (\Throwable $e)
		{
			return null;
		}

		foreach ($rows as $row)
		{
			if (!is_object($row))
			{
				continue;
			}

			$stored = get_object_vars($row);

			if ((int) ($stored['user_id'] ?? 0) !== $userId)
			{
				continue;
			}

			return $row;
		}

		return null;
	}

	/**
	 * Whether this word is a path segment the events parser treats as a view.
	 *
	 * @param   string  $segment  Path segment.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isReservedEventSegment(string $segment): bool
	{
		return \in_array(strtolower($segment), self::RESERVED_EVENT_SEGMENTS, true);
	}

	/**
	 * Read a nested events view, such as trip or the attendance screens.
	 *
	 * The bare word is the view. This check runs before alias lookup, so a
	 * stored alias cannot take the word. The following segment is the event.
	 * The word match ignores case.
	 *
	 * @param   string  $segment   Candidate view word.
	 * @param   array   $segments  Segments after that word, updated in place.
	 *
	 * @return  array|null  View variables, or null when $segment is not reserved.
	 *
	 * @since   1.0.0
	 */
	private function parseReservedView(string $segment, array &$segments): ?array
	{
		if (!$this->isReservedEventSegment($segment))
		{
			return null;
		}

		$view = strtolower($segment);
		$eventSegment = array_shift($segments);
		$forAttendance = $view === 'meetingattendance' || $view === 'rcattendance';
		$id = $this->extractIdFromSegment(
			\is_string($eventSegment) ? $eventSegment : null,
			$forAttendance
		);

		if ($view === 'trip')
		{
			return [
				'view' => 'trip',
				'id' => $id,
			];
		}

		return [
			'view' => $view,
			'event_id' => $id,
		];
	}
}
