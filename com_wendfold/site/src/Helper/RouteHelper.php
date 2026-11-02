<?php
/**
 * Wendfold - Chapter Management System
 * Site Route Helper
 *
 * Centralises Wendfold URL generation so templates, controllers, and
 * admin-side integrations all build the same internal and routed URLs.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

abstract class RouteHelper
{
	public static function internal(array $query = []): string
	{
		$query = array_merge(['option' => 'com_wendfold'], $query);

		return 'index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	public static function route(array $query = [], bool $xhtml = false): string
	{
		return Route::_(self::internal($query), $xhtml, Route::TLS_IGNORE, false);
	}

	public static function site(array $query = [], bool $absolute = false, bool $xhtml = false): string
	{
		$app = Factory::getApplication();

		if (method_exists($app, 'isClient') && $app->isClient('administrator'))
		{
			try
			{
				$siteMenu = $app->getMenu('site');

				if ($siteMenu)
				{
					$siteMenu->load();
				}
			}
			catch (\Throwable $e)
			{
				// Fall back below if site menu loading is unavailable.
			}
		}

		try
		{
			return Route::link('site', self::internal($query), $xhtml, Route::TLS_IGNORE, $absolute);
		}
		catch (\Throwable $e)
		{
			$fallback = self::internal($query);

			return $absolute ? Uri::root() . ltrim($fallback, '/') : $fallback;
		}
	}

	public static function view(string $view, array $query = [], bool $xhtml = false): string
	{
		$query['view'] = $view;

		return self::route($query, $xhtml);
	}

	public static function task(string $task, array $query = [], bool $xhtml = false): string
	{
		$query['task'] = $task;

		return self::route($query, $xhtml);
	}

	public static function events(array $query = [], bool $xhtml = false): string
	{
		return self::view('events', $query, $xhtml);
	}

	public static function eventsInternal(array $query = []): string
	{
		$query['view'] = 'events';

		return self::internal($query);
	}

	public static function dashboard(array $query = [], bool $xhtml = false): string
	{
		return self::view('dashboard', $query, $xhtml);
	}

	public static function dashboardInternal(array $query = []): string
	{
		$query['view'] = 'dashboard';

		return self::internal($query);
	}

	/**
	 * Site awards catalog URL.
	 *
	 * @param   array    $query  Extra query vars.
	 * @param   boolean  $xhtml  Escape ampersands when true.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function awards(array $query = [], bool $xhtml = false): string
	{
		return self::view('awards', $query, $xhtml);
	}

	public static function myRsvps(array $query = [], bool $xhtml = false): string
	{
		return self::view('myrsvps', $query, $xhtml);
	}

	public static function myRsvpsInternal(array $query = []): string
	{
		$query['view'] = 'myrsvps';

		return self::internal($query);
	}

	/**
	 * Site my-activities list URL.
	 *
	 * @param   array    $query  Extra query vars.
	 * @param   boolean  $xhtml  Escape ampersands when true.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function myActivities(array $query = [], bool $xhtml = false): string
	{
		return self::view('myactivities', $query, $xhtml);
	}

	/**
	 * Internal my-activities URL for login return.
	 *
	 * @param   array  $query  Extra query vars.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function myActivitiesInternal(array $query = []): string
	{
		$query['view'] = 'myactivities';

		return self::internal($query);
	}

	public static function officers(array $query = [], bool $xhtml = false): string
	{
		return self::view('officers', $query, $xhtml);
	}

	public static function profileEdit(array $query = [], bool $xhtml = false): string
	{
		return self::view('profileedit', $query, $xhtml);
	}

	public static function profileEditInternal(array $query = []): string
	{
		$query['view'] = 'profileedit';

		return self::internal($query);
	}

	public static function checkin(string $token, array $query = [], bool $xhtml = false): string
	{
		$query['token'] = $token;

		return self::view('checkin', $query, $xhtml);
	}

	public static function checkinInternal(string $token, array $query = []): string
	{
		$query['view'] = 'checkin';
		$query['token'] = $token;

		return self::internal($query);
	}

	public static function event(int $id, array $query = [], bool $xhtml = false): string
	{
		$query['id'] = $id;

		return self::view('event', $query, $xhtml);
	}

	public static function eventInternal(int $id, array $query = []): string
	{
		$query['view'] = 'event';
		$query['id'] = $id;

		return self::internal($query);
	}

	public static function trip(int $id, array $query = [], bool $xhtml = false): string
	{
		$query['id'] = $id;

		return self::view('trip', $query, $xhtml);
	}

	public static function tripInternal(int $id, array $query = []): string
	{
		$query['view'] = 'trip';
		$query['id'] = $id;

		return self::internal($query);
	}

	public static function rcAttendance(int $eventId, array $query = [], bool $xhtml = false): string
	{
		$query['event_id'] = $eventId;

		return self::view('rcattendance', $query, $xhtml);
	}

	public static function rcAttendanceInternal(int $eventId, array $query = []): string
	{
		$query['view'] = 'rcattendance';
		$query['event_id'] = $eventId;

		return self::internal($query);
	}

	public static function meetingAttendance(int $eventId, array $query = [], bool $xhtml = false): string
	{
		$query['event_id'] = $eventId;

		return self::view('meetingattendance', $query, $xhtml);
	}

	public static function meetingAttendanceInternal(int $eventId, array $query = []): string
	{
		$query['view'] = 'meetingattendance';
		$query['event_id'] = $eventId;

		return self::internal($query);
	}

	public static function volunteerBoard(int $eventId, array $query = [], bool $xhtml = false): string
	{
		$query['event_id'] = $eventId;

		return self::view('volunteerboard', $query, $xhtml);
	}

	public static function volunteerBoardInternal(int $eventId, array $query = []): string
	{
		$query['view'] = 'volunteerboard';
		$query['event_id'] = $eventId;

		return self::internal($query);
	}

	public static function login(?string $returnInternal = null, bool $xhtml = false): string
	{
		$query = 'index.php?option=com_users&view=login';

		if ($returnInternal !== null && $returnInternal !== '')
		{
			$query .= '&return=' . self::encodeReturn($returnInternal);
		}

		return Route::_($query, $xhtml, Route::TLS_IGNORE, false);
	}

	public static function encodeReturn(string $internalUrl): string
	{
		return base64_encode($internalUrl);
	}

	public static function detail($eventLike, bool $xhtml = false): string
	{
		$id = self::getEventId($eventLike);

		if ($id <= 0)
		{
			return self::events([], $xhtml);
		}

		return self::detailViewName($eventLike) === 'trip'
			? self::trip($id, [], $xhtml)
			: self::event($id, [], $xhtml);
	}

	public static function detailInternal($eventLike): string
	{
		$id = self::getEventId($eventLike);

		if ($id <= 0)
		{
			return self::eventsInternal();
		}

		return self::detailViewName($eventLike) === 'trip'
			? self::tripInternal($id)
			: self::eventInternal($id);
	}

	public static function detailViewName($eventLike): string
	{
		return self::isTripEvent($eventLike) ? 'trip' : 'event';
	}

	public static function isTripEvent($eventLike): bool
	{
		if (!is_object($eventLike) && !is_array($eventLike))
		{
			return false;
		}

		$behavior = (int) self::getValue($eventLike, 'event_behavior');
		$workflowMode = (int) self::getValue($eventLike, 'workflow_mode');
		$parentEventId = (int) self::getValue($eventLike, 'parent_event_id');

		return \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight($behavior, $workflowMode)
			&& $parentEventId === 0;
	}

	private static function getEventId($eventLike): int
	{
		if (!is_object($eventLike) && !is_array($eventLike))
		{
			return 0;
		}

		$id = (int) self::getValue($eventLike, 'id');

		if ($id > 0)
		{
			return $id;
		}

		return (int) self::getValue($eventLike, 'event_id');
	}

	private static function getValue($eventLike, string $key)
	{
		if (is_array($eventLike))
		{
			return $eventLike[$key] ?? null;
		}

		return $eventLike->$key ?? null;
	}
}
