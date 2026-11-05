<?php
/**
 * Wendfold - Site Overnight Trip View
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Site\View\Trip;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	protected $item;

	protected $state;

	public $childEvents = [];

	public $volunteerRoles = [];

	public $volunteerAssignment = null;

	public $canManageVolunteer = false;

	public $tripParticipantStatus = null;

	public $participants = [];

	public $rsvpCount = 0;

	public $userIsRsvped = false;

	public $canManageCheckin = false;

	public function display($tpl = null): void
	{
		/** @var \Wendfold\Component\Wendfold\Site\Model\TripModel $model */
		$model = $this->getModel();

		$this->item = $model->getItem();
		$this->state = $model->getState();

		if (!$this->item)
		{
			throw new \RuntimeException('Trip not found', 404);
		}

		$workflowMode = (int) ($this->item->workflow_mode ?? 0);
		$isOvernight = \Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior::isOvernight((int) ($this->item->event_behavior ?? 0), $workflowMode); // phpcs:ignore Generic.Files.LineLength.TooLong

		if (!$isOvernight || !empty($this->item->parent_event_id))
		{
			throw new \RuntimeException('Trip not found', 404);
		}

		if (count($errors = $model->getErrors()))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->childEvents = $model->getChildEvents((int) $this->item->id);
		$this->rsvpCount = $model->getRsvpCount((int) $this->item->id);
		$this->participants = $model->getTripParticipants((int) $this->item->id);

		$user = Factory::getApplication()->getIdentity();
		$this->volunteerRoles = $model->getVolunteerRoles((int) $this->item->id, (int) $user->id);

		if ($user->id)
		{
			$this->volunteerAssignment = $model->getVolunteerAssignment((int) $this->item->id, (int) $user->id);
			$this->canManageVolunteer = $model->canManageVolunteerAssignments((int) $this->item->id, (int) $user->id);

			$this->userIsRsvped = $model->isRsvped((int) $this->item->id, (int) $user->id);
			$this->tripParticipantStatus = $model->getTripParticipantStatus((int) $this->item->id, (int) $user->id);
			$this->canManageCheckin = $model->isRcOrSweep((int) $this->item->id, (int) $user->id);
		}

		$this->document->setTitle($this->item->title);

		$wa = $this->document->getWebAssetManager();
		$wa->useStyle('com_wendfold.site')
			->useScript('com_wendfold.event');

		parent::display($tpl);
	}
}
