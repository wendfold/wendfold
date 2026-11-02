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

use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $item;

	protected $state;

	public $roster = [];

	public $isRcOrSweep = false;

	public $rsvpCount = 0;

	public $attendanceCount = 0;

	public $userIsRsvped = false;

	public $rideGroups = [];

	public $userRideGroup = null;

	public $parentEvent = null;

	public $childEvents = [];

	public $volunteerRoles = [];

	public $volunteerAssignment = null;

	public $canManageVolunteer = false;

	public $tripParticipantStatus = null;

	public $canManageMeetingAttendance = false;

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

		$user = Factory::getApplication()->getIdentity();
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
