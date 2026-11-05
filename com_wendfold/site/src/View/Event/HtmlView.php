<?php

/**
 * Wendfold - Site Single Event View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Event;

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\EventCheckinQr;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\Application\WebApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * Renders one published event for the site visitor.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Event row for the current page.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	protected $item;

	/**
	 * Model state.
	 *
	 * @var    mixed
	 * @since  1.0.0
	 */
	protected $state;

	/**
	 * RSVP roster rows for signed-in viewers.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $roster = [];

	/**
	 * Whether the current user is Road Captain or Sweep for this event.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $isRcOrSweep = false;

	/**
	 * RSVP count for the event.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public $rsvpCount = 0;

	/**
	 * Attendance count for the event.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	public $attendanceCount = 0;

	/**
	 * Whether the current user has an RSVP.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $userIsRsvped = false;

	/**
	 * Ride groups for the event.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $rideGroups = [];

	/**
	 * The current user's ride group, when assigned.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $userRideGroup = null;

	/**
	 * Parent overnight event, when this event is a child day.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $parentEvent = null;

	/**
	 * Child day events for an overnight parent.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $childEvents = [];

	/**
	 * Volunteer roles configured for the event.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	public $volunteerRoles = [];

	/**
	 * The current user's volunteer assignment, when present.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	public $volunteerAssignment = null;

	/**
	 * Whether the current user may manage volunteer assignments.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $canManageVolunteer = false;

	/**
	 * Trip lodging status for the current user.
	 *
	 * @var    mixed
	 * @since  1.0.0
	 */
	public $tripParticipantStatus = null;

	/**
	 * Whether the current user may manage meeting attendance.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	public $canManageMeetingAttendance = false;

	/**
	 * Local check-in QR image for signed-in viewers, or null when hidden.
	 *
	 * @var    array{dataUri: string, extension: string, eventId: int}|null
	 * @since  1.0.0
	 */
	public $checkinQr = null;

	/**
	 * Display the event page.
	 *
	 * @param   string|null  $tpl  Template name.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Site\Model\EventModel $model */
		$model = $this->getModel();

		$this->item = $model->getItem();
		$this->state = $model->getState();

		if (!$this->item)
		{
			throw new \RuntimeException('Event not found', 404);
		}

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->rsvpCount = $model->getRsvpCount((int) $this->item->id);
		$this->attendanceCount = $model->getAttendanceCount((int) $this->item->id);
		$this->parentEvent = $model->getParentEvent((int) $this->item->id);
		$this->childEvents = $model->getChildEvents((int) $this->item->id);

		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$this->volunteerRoles = $model->getVolunteerRoles((int) $this->item->id, (int) $user->id);

		if ($user->id)
		{
			$this->volunteerAssignment = $model->getVolunteerAssignment((int) $this->item->id, (int) $user->id);
			$this->canManageVolunteer = $model->canManageVolunteerAssignments((int) $this->item->id, (int) $user->id);
			$this->canManageMeetingAttendance = $model->canManageMeetingAttendance((int) $this->item->id, (int) $user->id);

			$this->roster = $model->getRoster((int) $this->item->id);
			$this->isRcOrSweep = $model->isRcOrSweep((int) $this->item->id, (int) $user->id);
			$this->userIsRsvped = $model->isRsvped((int) $this->item->id, (int) $user->id);
			$this->rideGroups = $model->getRideGroups((int) $this->item->id);
			$this->userRideGroup = $model->getUserRideGroup((int) $this->item->id, (int) $user->id);
			$this->tripParticipantStatus = $model->getTripParticipantStatus((int) $this->item->id, (int) $user->id);
		}

		$this->checkinQr = EventCheckinQr::forViewer(
			$this->item,
			(int) $user->id,
			SiteTime::fromApplication($app)
		);

		if ($this->checkinQr !== null && $app instanceof WebApplication)
		{
			$app->allowCache(false);
			$app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
			$app->setHeader('Pragma', 'no-cache', true);
		}

		// Keep the check-in token out of the template scope for every viewer.
		$itemFields = get_object_vars($this->item);

		if (array_key_exists('qr_token', $itemFields))
		{
			// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column.
			unset($this->item->qr_token);
		}

		$this->document->setTitle($this->item->title);

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site')
			->useScript('com_wendfold.event');

		$showRide = !empty($this->item->is_riding_event);
		$hasGpx = trim((string) ($this->item->gpx_file ?? '')) !== '';
		$this->item->gpxUrl = '';

		if ($hasGpx && $model->currentUserCanAccessEventDetail($this->item))
		{
			$this->item->gpxUrl = RouteHelper::task('event.gpx', ['id' => (int) $this->item->id]);
		}

		$osmTiles = (int) ComponentHelper::getParams('com_wendfold')->get('osm_tiles', 1) === 1;
		$hasKsuCoords = is_numeric($this->item->ksu_loc_lat ?? null) && is_numeric($this->item->ksu_loc_lng ?? null);

		if ($showRide && $this->item->gpxUrl !== '')
		{
			$wa->useStyle('com_wendfold.leaflet')
				->useScript('com_wendfold.routemap');
		}

		if ($osmTiles && $hasKsuCoords)
		{
			$wa->useStyle('com_wendfold.leaflet')
				->useScript('com_wendfold.ksumap');
		}

		parent::display($tpl);
	}
}
