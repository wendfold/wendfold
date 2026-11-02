<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Event Single Record Model
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\EventCancelTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\EventDeleteCascade;
use Wendfold\Component\Wendfold\Administrator\Helper\EventMetricCredits;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Helper\EventTypeBehavior;
use Wendfold\Component\Wendfold\Administrator\Helper\InputStringFilter;
use Wendfold\Component\Wendfold\Administrator\Helper\MeetingSaveInput;
use Wendfold\Component\Wendfold\Administrator\Helper\PublishRowState;
use Wendfold\Component\Wendfold\Administrator\Helper\VolunteerRoleInput;
use Wendfold\Component\Wendfold\Administrator\Service\EventCancelNoticeService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;
use Wendfold\Component\Wendfold\Site\Helper\GpxFile;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * One event record.
 *
 * @method  void  setError(string $error)  Record a message for a refused save.
 *
 * @since   1.0.0
 */
class EventModel extends AdminModel
{
	/**
	 * Content type alias for the UCM / history stack.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_wendfold.event';

	/**
	 * Load the event edit form.
	 *
	 * @param   array    $data      Form data.
	 * @param   boolean  $loadData  Load the current item when true.
	 *
	 * @return  \Joomla\CMS\Form\Form|boolean
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.event',
			'event',
			['control' => 'jform', 'load_data' => $loadData]
		);

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Data for the event form: session draft, or the current item.
	 *
	 * @return  array|object
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		$app = Factory::getApplication();
		$data = $app->getUserState('com_wendfold.edit.event.data', []);

		if (empty($data))
		{
			$data = $this->getItem();

			if (!$data->id)
			{
				$data->published = 1;
				$data->workflow_mode = 2;
				$data->audience_scope = 1;
				$data->allow_rsvp = 1;
				$data->is_riding_event = 1;
				$data->show_in_event_list = 1;
				$data->volunteer_signup_open = 1;
			}
			else
			{
				foreach (['ksu_time', 'ksd_time'] as $field)
				{
					if (!empty($data->$field))
					{
						$ts = strtotime($data->$field);

						if ($ts !== false)
						{
							$data->$field = date('g:i A', $ts);
						}
					}
				}

				$locationSelects = [
					'ksu_location_id' => ['ksu_location_custom', 'ksu_address', 'ksu_city', 'ksu_state'],
					'ksd_location_id' => ['ksd_location_custom', 'ksd_address', 'ksd_city', 'ksd_state'],
					'event_location_id' => ['event_location_custom', 'event_address', 'event_city', 'event_state'],
				];

				foreach ($locationSelects as $idField => $fields)
				{
					$hasId = !empty($data->$idField);

					if ($hasId)
					{
						continue;
					}

					$hasCustom = false;

					foreach ($fields as $field)
					{
						if (!empty($data->$field))
						{
							$hasCustom = true;
							break;
						}
					}

					if ($hasCustom)
					{
						$data->$idField = -1;
					}
				}

				if (empty($data->workflow_mode))
				{
					$type = $this->lookupEventType((int) ($data->event_type_id ?? 0)); // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
					$data->workflow_mode = $type
						? ((int) $type->default_workflow_mode ?: EventTypeBehavior::workflow((int) $type->behavior)) // phpcs:ignore Generic.Files.LineLength.TooLong,Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
						: 1;
				}

				if (!isset($data->allow_rsvp))
				{
					$data->allow_rsvp = 1;
				}

				if (!isset($data->show_in_event_list))
				{
					$data->show_in_event_list = 1;
				}

				$data->volunteer_roles = $this->loadVolunteerRoles((int) $data->id);
			}
		}

		return $data;
	}

	/**
	 * Load one event type, including unpublished rows so an edit can keep it.
	 *
	 * @param   integer  $eventTypeId  event_types.id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	protected function lookupEventType(int $eventTypeId): ?object
	{
		if ($eventTypeId < 1)
		{
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('behavior'),
				$db->quoteName('default_riding'),
				$db->quoteName('default_workflow_mode'),
				$db->quoteName('published'),
			]) // phpcs:ignore PEAR.Functions.FunctionCallSignature.CloseBracketLine
			->from($db->quoteName('#__wendfold_event_types'))
			->where($db->quoteName('id') . ' = :eventTypeId')
			->bind(':eventTypeId', $eventTypeId, ParameterType::INTEGER);

		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
	}

	/**
	 * Normalise a user-entered time string to MySQL TIME format (HH:MM:SS).
	 *
	 * @param   string  $raw  Posted time text.
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	protected function normaliseTime(string $raw): ?string
	{
		$raw = trim($raw);

		if ($raw === '')
		{
			return null;
		}

		if (preg_match('/^\d{3,4}$/', $raw))
		{
			$padded = str_pad($raw, 4, '0', STR_PAD_LEFT);
			$hours = (int) substr($padded, 0, 2);
			$mins = (int) substr($padded, 2, 2);

			if ($hours >= 0 && $hours <= 23 && $mins >= 0 && $mins <= 59)
			{
				return sprintf('%02d:%02d:00', $hours, $mins);
			}
		}

		if (preg_match('/^(\d{3,4})\s*(am|pm)$/i', $raw, $m))
		{
			$digits = str_pad($m[1], 4, '0', STR_PAD_LEFT);
			$hours = (int) substr($digits, 0, 2);
			$mins = (int) substr($digits, 2, 2);
			$ampm = strtolower($m[2]);

			if ($hours >= 1 && $hours <= 12 && $mins >= 0 && $mins <= 59)
			{
				if ($hours === 12)
				{
					$hours = 0;
				}

				if ($ampm === 'pm')
				{
					$hours += 12;
				}

				return sprintf('%02d:%02d:00', $hours, $mins);
			}
		}

		$ts = strtotime($raw);

		if ($ts !== false)
		{
			return date('H:i:s', $ts);
		}

		return null;
	}

	/**
	 * Save an event, its volunteer roles, metric credits, and inline locations.
	 *
	 * @param   array  $data  Form-filtered event fields.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$post = $app->getInput()->post->get('jform', [], 'array');

		$dateFields = ['event_date', 'start_date', 'end_date', 'trip_booking_deadline'];

		foreach ($dateFields as $field)
		{
			if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === '0000-00-00'))
			{
				$data[$field] = null;
			}
		}

		foreach (['ksu_time', 'ksd_time'] as $field)
		{
			if (isset($data[$field]))
			{
				$data[$field] = $this->normaliseTime((string) $data[$field]);
			}
		}

		$intFields = [
			'road_captain_id', 'sweep_id', 'capacity',
			'ksu_location_id', 'ksd_location_id', 'event_location_id',
			'parent_event_id', 'trip_lead_id', 'trip_sweep_id',
			'volunteer_manager_id', 'volunteer_backup_id',
		];

		foreach ($intFields as $field)
		{
			if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === '0' || $data[$field] === '-1'))
			{
				$data[$field] = null;
			}
		}

		$eventTypeId = (int) ($data['event_type_id'] ?? 0);
		$type = $this->lookupEventType($eventTypeId);
		$isNew = empty($data['id']);

		if ($type === null)
		{
			$this->setError(Text::sprintf(
				'JLIB_FORM_VALIDATE_FIELD_INVALID',
				Text::_('COM_WENDFOLD_FIELD_EVENT_TYPE_LABEL')
			)); // phpcs:ignore PEAR.Functions.FunctionCallSignature.CloseBracketLine

			return false;
		}

		$behavior = (int) $type->behavior;
		$defaultWorkflow = (int) $type->default_workflow_mode; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		$defaultRiding = (int) $type->default_riding; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
		$data['event_type_id'] = $eventTypeId;

		$prepared = MeetingSaveInput::prepareSaveData(
			$data,
			$post,
			$isNew,
			$behavior,
			$defaultWorkflow,
			$defaultRiding
		);

		if ($prepared === null)
		{
			$label = MeetingSaveInput::choiceLabel(MeetingSaveInput::disallowedChoice($post));
			$this->setError(Text::sprintf('JLIB_FORM_VALIDATE_FIELD_INVALID', Text::_($label)));

			return false;
		}

		$data = $prepared;
		$workflowMode = (int) ($data['workflow_mode'] ?? 0);

		if (EventTypeBehavior::isMeeting($behavior, 0))
		{
			$data['is_riding_event'] = 0;
		}

		if ($isNew && $workflowMode <= 0)
		{
			$workflowMode = $defaultWorkflow > 0 ? $defaultWorkflow : EventTypeBehavior::workflow($behavior);
			$data['workflow_mode'] = $workflowMode;
		}

		if (EventTypeBehavior::isOvernight($behavior, $workflowMode))
		{
			if (empty($data['start_date']))
			{
				$data['start_date'] = $data['event_date'] ?? null;
			}

			if (empty($data['end_date']))
			{
				$data['end_date'] = $data['start_date'] ?? ($data['event_date'] ?? null);
			}

			$data['is_riding_event'] = 0;
		}
		else
		{
			$data['start_date'] = null;
			$data['end_date'] = null;
		}

		if (!empty($data['parent_event_id']))
		{
			$data['show_in_event_list'] = isset($data['show_in_event_list']) ? (int) $data['show_in_event_list'] : 0;
		}
		elseif (!isset($data['show_in_event_list']))
		{
			$data['show_in_event_list'] = 1;
		}

		if ($isNew && !isset($data['allow_rsvp']))
		{
			$data['allow_rsvp'] = 1;
		}

		if (!empty($data['volunteer_manager_id']) && !empty($data['volunteer_backup_id'])
			&& (int) $data['volunteer_manager_id'] === (int) $data['volunteer_backup_id'])
		{
			$data['volunteer_backup_id'] = null;
		}

		if (empty($data['id']) && empty($data['qr_token']))
		{
			$data['qr_token'] = bin2hex(random_bytes(16));
		}

		$fileBag = $app->getInput()->files->get('jform', [], 'array');
		$gpx = $fileBag['gpx_file'] ?? null;
		$gpxTmp = is_array($gpx) ? ($gpx['tmp_name'] ?? '') : '';
		$originalName = is_array($gpx) ? (string) ($gpx['name'] ?? '') : '';
		$gpxAccepted = $gpxTmp !== ''
			&& is_uploaded_file($gpxTmp)
			&& GpxFile::hasGpxExtension($originalName)
			&& GpxFile::isGpxDocument($gpxTmp);

		if ($gpxAccepted)
		{
			$uploadPath = JPATH_ROOT . '/media/com_wendfold/gpx/';

			if (!is_dir($uploadPath))
			{
				mkdir($uploadPath, 0755, true);
			}

			$safeName = bin2hex(random_bytes(16)) . '.gpx';
			$destination = $uploadPath . $safeName;

			if (move_uploaded_file($gpxTmp, $destination))
			{
				$data['gpx_file'] = 'media/com_wendfold/gpx/' . $safeName;
			}
		}

		if (empty($data['gpx_file']) && !empty($data['id']))
		{
			$existing = $this->getItem($data['id']);
			$data['gpx_file'] = $existing->gpx_file ?? '';
		}

		if (empty($data['image']))
		{
			$locId = (int) ($data['event_location_id'] ?? 0);

			if ($locId > 0)
			{
				$db = $this->getDatabase();
				$query = $db->createQuery()
					->select($db->quoteName('image'))
					->from($db->quoteName('#__wendfold_locations'))
					->where($db->quoteName('id') . ' = ' . $locId);

				$locImage = (string) $db->setQuery($query)->loadResult();

				if (!empty($locImage))
				{
					$data['image'] = $locImage;
				}
			}
		}

		$postedCredits = [];

		if (isset($data['metric_credits']) && \is_array($data['metric_credits']))
		{
			$postedCredits = $data['metric_credits'];
		}
		elseif (isset($post['metric_credits']) && \is_array($post['metric_credits']))
		{
			$postedCredits = $post['metric_credits'];
		}

		try
		{
			$creditValues = $this->parsePostedMetricCredits($postedCredits);
		}
		catch (\InvalidArgumentException)
		{
			$this->setError(Text::_('COM_WENDFOLD_METRIC_CREDIT_INVALID'));

			return false;
		}

		$db = $this->getDatabase();
		$service = $this->metricService();
		$service->deferAwardRecompute(true);
		$db->transactionStart(true);
		$eventId = 0;
		$cancelTransitioned = false;

		try
		{
			$oldStatus = 1;

			if (!$isNew)
			{
				$locked = EventRowLock::lock($db, (int) $data['id']);

				if ($locked === null)
				{
					$db->transactionRollback(true);
					$service->discardDeferredAwardRecompute();
					$this->setError(Text::_('COM_WENDFOLD_ERROR_SAVE_FAILED'));

					return false;
				}

				$oldStatus = (int) $locked->status;
			}

			$newStatus = (int) ($data['status'] ?? 1);
			$intendsCancel = !$isNew
				&& $newStatus === EventCancelTransition::STATUS_CANCELLED
				&& $oldStatus !== EventCancelTransition::STATUS_CANCELLED;

			if ($intendsCancel)
			{
				$eventId = (int) $data['id'];
				$attendanceCount = (int) $db->setQuery(
					$db->createQuery()
						->select('COUNT(*)')
						->from($db->quoteName('#__wendfold_attendance'))
						->where($db->quoteName('event_id') . ' = :eventId')
						->bind(':eventId', $eventId, ParameterType::INTEGER)
				)->loadResult();

				if ($attendanceCount > 0)
				{
					$db->transactionRollback(true);
					$service->discardDeferredAwardRecompute();
					$this->setError(Text::_('COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS'));

					return false;
				}
			}

			if ($newStatus === 2 && $oldStatus !== 2)
			{
				$data['completed_date'] = Factory::getDate()->toSql();
				$data['completed_by'] = $user->id;
			}

			$this->saveInlineLocations($data);

			$result = parent::save($data);

			if (!$result)
			{
				$db->transactionRollback(true);
				$service->discardDeferredAwardRecompute();

				return false;
			}

			$eventId = (int) $this->getState($this->getName() . '.id');

			// Prefer form-filtered $data; still string-filter rows in case of a raw post fallback.
			$roleRows = $data['volunteer_roles'] ?? ($post['volunteer_roles'] ?? []);
			$this->saveVolunteerRoles(
				$eventId,
				VolunteerRoleInput::filterRows($this->normaliseVolunteerRoleRows($roleRows))
			);
			EventMetricCredits::replaceNormalised($db, $eventId, $creditValues);
			$service->resyncEvent($eventId);

			// Gate notices on the status actually persisted, not the posted form value.
			$persistedStatus = $this->loadPersistedEventStatus($db, $eventId, $oldStatus);
			$cancelTransitioned = EventCancelNoticeService::becameCancelled($oldStatus, $persistedStatus);

			$db->transactionCommit(true);
			$service->flushAwardRecompute();
		}
		catch (\Throwable $error)
		{
			$db->transactionRollback(true);
			$service->discardDeferredAwardRecompute();
			$this->failWithLoggedError($error);

			return false;
		}

		// Notices run only after commit so a rolled-back / refused cancel never emails.
		$this->dispatchEventCancelNotice($db, $eventId, $cancelTransitioned);

		return true;
	}

	/**
	 * Status stored on the event row after parent::save, or $fallback when unread.
	 *
	 * @param   DatabaseInterface  $db        Database.
	 * @param   integer            $eventId   Event id.
	 * @param   integer            $fallback  Value when the row cannot be read.
	 *
	 * @return  integer
	 *
	 * @since   1.0.0
	 */
	protected function loadPersistedEventStatus(DatabaseInterface $db, int $eventId, int $fallback): int
	{
		if ($eventId < 1)
		{
			return $fallback;
		}

		$status = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('status'))
				->from($db->quoteName('#__wendfold_events'))
				->where($db->quoteName('id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER)
		)->loadResult();

		return $status === null || $status === false ? $fallback : (int) $status;
	}

	/**
	 * Send cancel notices after a committed real cancel. Tests may override.
	 *
	 * @param   DatabaseInterface  $db             Database.
	 * @param   integer            $eventId        Event id.
	 * @param   boolean            $transitioned   True when the event became Cancelled.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function dispatchEventCancelNotice(
		DatabaseInterface $db,
		int $eventId,
		bool $transitioned
	): void
	{
		EventCancelNoticeService::notifyAfterTransition($db, $eventId, $transitioned);
	}

	/**
	 * Normalise posted event credits, or throw when a value is invalid.
	 *
	 * Runs before the event row is saved so a bad credit cannot leave a
	 * half-saved event.
	 *
	 * @param   array<string, mixed>  $posted  metric id => posted amount.
	 *
	 * @return  array<int, string>
	 *
	 * @since   1.0.0
	 */
	protected function parsePostedMetricCredits(array $posted): array
	{
		return EventMetricCredits::normalisePosted($this->getDatabase(), $posted);
	}

	/**
	 * MetricService for this model's database and signed-in user.
	 *
	 * Tests may override to inject an in-memory store.
	 *
	 * @return  MetricService
	 *
	 * @since   1.0.0
	 */
	protected function metricService(): MetricService
	{
		$user = Factory::getApplication()->getIdentity();

		return MetricService::fromDatabase($this->getDatabase(), (int) $user->id);
	}

	/**
	 * Delete events and every child row that points at them in one transaction.
	 *
	 * EventDeleteCascade::purge removes metric_entries (via MetricService) and
	 * every other install-SQL child table for the event_id; then the event row
	 * is deleted. Each event is its own transaction. The batch continues after
	 * a failure so later pks are still attempted. ACL is decided with canDelete
	 * before parent::delete; denied pks contribute one Delete not permitted
	 * line while other pks still run. Returns false when any pk failed so the
	 * controller shows the partial summary.
	 *
	 * @param   array  $pks  Primary keys.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function delete(&$pks)
	{
		$pks = array_map('intval', (array) $pks);
		$db = $this->getDatabase();
		$service = $this->metricService();
		$deletedIds = [];
		$failures = [];
		$aclDenied = false;
		$generic = Text::_('COM_WENDFOLD_ERROR_EVENT_COULD_NOT_BE_DELETED');
		$service->deferAwardRecompute(true);

		try
		{
			foreach ($pks as $pk)
			{
				if ($pk < 1)
				{
					continue;
				}

				$event = $this->loadEventForDelete($pk);
				$title = $this->titleFromEvent($pk, $event);

				if ($event === null)
				{
					$failures[] = Text::sprintf('COM_WENDFOLD_ERROR_DELETE_EVENT_FAILED', $title, $generic);

					continue;
				}

				if (!$this->canDelete($event))
				{
					$aclDenied = true;

					continue;
				}

				// Purge queues award profiles before parent::delete; restore
				// that queue if the event row is vetoed or the txn rolls back.
				$awardCheckpoint = $service->deferredAwardProfileIds();
				$db->transactionStart(true);

				try
				{
					if (EventRowLock::lock($db, $pk) === null)
					{
						$db->transactionRollback(true);
						$service->replaceDeferredAwardProfiles($awardCheckpoint);
						$failures[] = Text::sprintf('COM_WENDFOLD_ERROR_DELETE_EVENT_FAILED', $title, $generic);

						continue;
					}

					$this->purgeEventChildren($db, $service, $pk);
					$one = [$pk];

					if (!parent::delete($one))
					{
						$db->transactionRollback(true);
						$service->replaceDeferredAwardProfiles($awardCheckpoint);
						$reason = trim((string) $this->getError());
						Log::add(
							$reason !== '' ? $reason : $generic,
							Log::ERROR,
							'com_wendfold'
						);
						$failures[] = Text::sprintf('COM_WENDFOLD_ERROR_DELETE_EVENT_FAILED', $title, $generic);

						continue;
					}

					$db->transactionCommit(true);
					$deletedIds[] = $pk;
				}
				catch (\Throwable $error)
				{
					$db->transactionRollback(true);
					$service->replaceDeferredAwardProfiles($awardCheckpoint);
					Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
					$failures[] = Text::sprintf(
						'COM_WENDFOLD_ERROR_DELETE_EVENT_FAILED',
						$title,
						$generic
					);
				}
			}
		}
		finally
		{
			$service->flushAwardRecompute();
		}

		// Controllers count successes from the by-reference primary-key list.
		$pks = $deletedIds;

		if ($failures === [] && !$aclDenied)
		{
			return true;
		}

		$this->setError($this->formatDeleteSummary(\count($deletedIds), $failures, $aclDenied));

		return false;
	}

	/**
	 * Hard-delete every child row for one event inside the open transaction.
	 *
	 * Tests may override to inject a mid-cascade failure.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   MetricService      $service  Metric writer.
	 * @param   integer            $eventId  Event id.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function purgeEventChildren(DatabaseInterface $db, MetricService $service, int $eventId): void
	{
		EventDeleteCascade::purge($db, $service, $eventId);
	}

	/**
	 * Load the event row used for canDelete and error titles.
	 *
	 * Tests may override to supply fixtures without a SELECT.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  object|null  Object with at least id (and title when present).
	 *
	 * @since   1.0.0
	 */
	protected function loadEventForDelete(int $eventId): ?object
	{
		$db = $this->getDatabase();
		$query = $db->createQuery()
			->select($db->quoteName(['id', 'title']))
			->from($db->quoteName('#__wendfold_events'))
			->where($db->quoteName('id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$row = $db->setQuery($query)->loadObject();

		return $row ?: null;
	}

	/**
	 * Event title for delete errors, or #id when the row has no title.
	 *
	 * @param   integer      $eventId  Event id.
	 * @param   object|null  $event    Row from loadEventForDelete, if any.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function titleFromEvent(int $eventId, ?object $event): string
	{
		if ($event !== null && trim((string) ($event->title ?? '')) !== '')
		{
			return (string) $event->title;
		}

		return '#' . $eventId;
	}

	/**
	 * Build the controller-visible summary after a partial batch delete.
	 *
	 * @param   integer       $deleted    Events successfully deleted.
	 * @param   list<string>  $failures   Per-event failure lines.
	 * @param   boolean       $aclDenied  True when any pk failed canDelete.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	private function formatDeleteSummary(int $deleted, array $failures, bool $aclDenied): string
	{
		$parts = [];

		foreach ($failures as $failure)
		{
			$parts[] = $failure;
		}

		if ($aclDenied)
		{
			$parts[] = Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED');
		}

		if ($parts === [] && $deleted > 0)
		{
			$parts[] = Text::plural('COM_WENDFOLD_N_ITEMS_FAILED_DELETING', 1);
		}

		return implode("\n", $parts);
	}

	/**
	 * Publish, unpublish, trash, archive, or restore events.
	 *
	 * Trashing an event trashes its attendance metric entries. Every other
	 * state change re-syncs those entries so published follows
	 * attendanceEntryPublished() for the new event state.
	 *
	 * @param   array    $pks    Primary keys.
	 * @param   integer  $value  New published value.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function publish(&$pks, $value = 1)
	{
		$pks = array_map('intval', (array) $pks);
		$value = (int) $value;
		$db = $this->getDatabase();
		$service = $this->metricService();
		$successes = [];
		$hadFailure = false;
		$service->deferAwardRecompute(true);

		try
		{
			foreach ($pks as $pk)
			{
				if ($pk < 1)
				{
					continue;
				}

				// Metric writes queue award profiles before commit; restore that
				// queue if the event state change is vetoed or the txn rolls back.
				$awardCheckpoint = $service->deferredAwardProfileIds();
				$db->transactionStart(true);

				try
				{
					$locked = EventRowLock::lock($db, $pk);

					if ($locked === null)
					{
						$db->transactionRollback(true);
						$service->replaceDeferredAwardProfiles($awardCheckpoint);
						$this->setError(Text::_('COM_WENDFOLD_ERROR_SAVE_FAILED'));
						$hadFailure = true;

						continue;
					}

					$userId = 0;
					$identity = Factory::getApplication()->getIdentity();

					if ($identity !== null)
					{
						$userId = (int) $identity->id;
					}

					$checkedOutField = 'checked_out';
					$checkedOut = (int) ($locked->$checkedOutField ?? 0);
					$verdict = PublishRowState::classify(
						(int) ($locked->published ?? 0),
						$checkedOut,
						true,
						$value,
						$userId
					);

					if ($verdict === PublishRowState::UNCHANGED)
					{
						$db->transactionCommit(true);

						continue;
					}

					if ($verdict === PublishRowState::CHECKED_OUT)
					{
						$db->transactionRollback(true);
						$service->replaceDeferredAwardProfiles($awardCheckpoint);
						$hadFailure = true;

						continue;
					}

					if ($value === -2)
					{
						$service->trashEventAttendance($pk);
					}

					$one = [$pk];

					if (!parent::publish($one, $value))
					{
						$db->transactionRollback(true);
						$service->replaceDeferredAwardProfiles($awardCheckpoint);
						$hadFailure = true;

						continue;
					}

					if ($one === [])
					{
						/*
						 * Parent emptied the list while returning true (ACL prune or
						 * similar). Re-read under the lock: only already-at-target is
						 * a silent no-op; anything else is a failure.
						 */
						$lockedAfter = EventRowLock::lock($db, $pk);

						if ($lockedAfter === null)
						{
							$db->transactionRollback(true);
							$service->replaceDeferredAwardProfiles($awardCheckpoint);
							$hadFailure = true;

							continue;
						}

						$afterCheckedOut = (int) ($lockedAfter->$checkedOutField ?? 0);
						$after = PublishRowState::classify(
							(int) ($lockedAfter->published ?? 0),
							$afterCheckedOut,
							true,
							$value,
							$userId
						);

						if ($after !== PublishRowState::UNCHANGED)
						{
							$db->transactionRollback(true);
							$service->replaceDeferredAwardProfiles($awardCheckpoint);
							$hadFailure = true;

							continue;
						}

						$db->transactionCommit(true);

						continue;
					}

					if ($value !== -2)
					{
						$service->resyncEvent($pk);
					}

					$db->transactionCommit(true);
					$successes[] = $pk;
				}
				catch (\Throwable $error)
				{
					$db->transactionRollback(true);
					$service->replaceDeferredAwardProfiles($awardCheckpoint);
					$this->failWithLoggedError($error);
					$hadFailure = true;
				}
			}
		}
		finally
		{
			$service->flushAwardRecompute();
		}

		// Controllers count successes from the by-reference primary-key list.
		$pks = $successes;

		return !$hadFailure;
	}

	/**
	 * Log the exception detail and show a generic translated error.
	 *
	 * @param   \Throwable  $error  Failure from the database or metric write.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function failWithLoggedError(\Throwable $error): void
	{
		Log::add($error->getMessage(), Log::ERROR, 'com_wendfold');
		$this->setError(Text::_('COM_WENDFOLD_ERROR_SAVE_FAILED'));
	}

	/**
	 * Coerce posted volunteer role subform rows into a list of arrays.
	 *
	 * @param   mixed  $roleRows  Posted roles (array or JSON string).
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	protected function normaliseVolunteerRoleRows($roleRows): array
	{
		if (is_array($roleRows))
		{
			return array_values(array_filter($roleRows, static fn ($row) => is_array($row)));
		}

		if (is_string($roleRows))
		{
			$roleRows = trim($roleRows);

			if ($roleRows == '')
			{
				return [];
			}

			$decoded = json_decode($roleRows, true);

			if (is_array($decoded))
			{
				return array_values(array_filter($decoded, static fn ($row) => is_array($row)));
			}
		}

		return [];
	}

	/**
	 * Volunteer role rows for the event form subform.
	 *
	 * @param   integer  $eventId  Event id.
	 *
	 * @return  array<int, array<string, mixed>>
	 *
	 * @since   1.0.0
	 */
	protected function loadVolunteerRoles(int $eventId): array
	{
		if ($eventId <= 0)
		{
			return [];
		}

		$db = $this->getDatabase();
		$rows = $db->setQuery(
			$db->createQuery()
				->select('*')
				->from($db->quoteName('#__wendfold_event_volunteer_roles'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
				->order($db->quoteName('sort_order') . ' ASC, ' . $db->quoteName('id') . ' ASC')
		)->loadAssocList() ?: [];

		foreach ($rows as &$row)
		{
			foreach (['start_time', 'end_time'] as $field)
			{
				if (!empty($row[$field]))
				{
					$ts = strtotime((string) $row[$field]);
					$row[$field] = $ts ? date('g:i A', $ts) : $row[$field];
				}
			}
		}

		return $rows;
	}

	/**
	 * Replace volunteer roles for an event with the filtered posted rows.
	 *
	 * @param   integer  $eventId   Event id.
	 * @param   array    $roleRows  Filtered role rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveVolunteerRoles(int $eventId, array $roleRows): void
	{
		if ($eventId <= 0)
		{
			return;
		}

		$db = $this->getDatabase();
		$user = Factory::getApplication()->getIdentity();
		$date = Factory::getDate()->toSql();

		$existingIds = $db->setQuery(
			$db->createQuery()
				->select($db->quoteName('id'))
				->from($db->quoteName('#__wendfold_event_volunteer_roles'))
				->where($db->quoteName('event_id') . ' = ' . $eventId)
		)->loadColumn() ?: [];

		$keepIds = [];
		$sortOrder = 1;

		foreach ($roleRows as $row)
		{
			$title = trim((string) ($row['title'] ?? ''));

			if ($title === '')
			{
				continue;
			}

			$record = new \stdClass;
			$record->id = (int) ($row['id'] ?? 0);
			$record->event_id = $eventId;
			$record->title = $title;
			$record->description = trim((string) ($row['description'] ?? ''));
			$record->slot_count = max(1, (int) ($row['slot_count'] ?? 1));
			$record->shift_label = trim((string) ($row['shift_label'] ?? ''));
			$record->start_time = $this->normaliseTime((string) ($row['start_time'] ?? ''));
			$record->end_time = $this->normaliseTime((string) ($row['end_time'] ?? ''));
			$record->requires_road_captain = !empty($row['requires_road_captain']) ? 1 : 0;
			$record->requires_road_crew = !empty($row['requires_road_crew']) ? 1 : 0;
			$record->members_only = !empty($row['members_only']) ? 1 : 0;
			$record->published = array_key_exists('published', $row) ? (int) $row['published'] : 1;
			$record->sort_order = $sortOrder;
			$record->ordering = $sortOrder;
			$sortOrder++;
			$record->modified = $date;
			$record->modified_by = $user->id;

			if ($record->id > 0 && in_array($record->id, $existingIds, true))
			{
				$db->updateObject('#__wendfold_event_volunteer_roles', $record, 'id', true);
				$keepIds[] = $record->id;
				continue;
			}

			unset($record->id);
			$record->created = $date;
			$record->created_by = $user->id;
			$db->insertObject('#__wendfold_event_volunteer_roles', $record, 'id');
			$keepIds[] = (int) $record->id;
		}

		$deleteIds = array_diff(array_map('intval', $existingIds), array_map('intval', $keepIds));

		if (!empty($deleteIds))
		{
			$quotedIds = implode(',', array_map('intval', $deleteIds));
			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_event_volunteers'))
					->where($db->quoteName('volunteer_role_id') . ' IN (' . $quotedIds . ')')
			)->execute();

			$db->setQuery(
				$db->createQuery()
					->delete($db->quoteName('#__wendfold_event_volunteer_roles'))
					->where($db->quoteName('id') . ' IN (' . $quotedIds . ')')
			)->execute();
		}
	}

	/**
	 * Get a table object for this model.
	 *
	 * @param   string  $name     Table name.
	 * @param   string  $prefix   Class prefix.
	 * @param   array   $options  Configuration array.
	 *
	 * @return  \Joomla\CMS\Table\Table|boolean
	 *
	 * @since   1.0.0
	 */
	public function getTable($name = 'Event', $prefix = 'Table', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	/**
	 * Saves inline custom locations when "Save as..." checkboxes are checked.
	 *
	 * @param   array  $data  Form-filtered event fields (text); save flags stay on post.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function saveInlineLocations(array $data): void
	{
		$db = $this->getDatabase();
		$app = Factory::getApplication();
		$user = $app->getIdentity();
		$date = Factory::getDate()->toSql();
		$post = $app->getInput()->post->get('jform', [], 'array');

		$locationSets = [
			'ksu' => [
				'save_flag' => 'save_ksu_location',
				'custom_name' => 'ksu_location_custom',
				'address' => 'ksu_address',
				'city' => 'ksu_city',
				'state' => 'ksu_state',
				'zip' => 'ksu_zip',
				'type' => 1,
			],
			'ksd' => [
				'save_flag' => 'save_ksd_location',
				'custom_name' => 'ksd_location_custom',
				'address' => 'ksd_address',
				'city' => 'ksd_city',
				'state' => 'ksd_state',
				'zip' => 'ksd_zip',
				'type' => 1,
			],
			'event' => [
				'save_flag' => 'save_event_location',
				'custom_name' => 'event_location_custom',
				'address' => 'event_address',
				'city' => 'event_city',
				'state' => 'event_state',
				'zip' => 'event_zip',
				'type' => 2,
			],
		];

		foreach ($locationSets as $set)
		{
			// Save-as checkboxes are template-only (not form fields); text comes from filtered $data.
			$saveFlag = !empty($post[$set['save_flag']]);
			$customName = InputStringFilter::clean((string) ($data[$set['custom_name']] ?? ''));

			if (!$saveFlag || $customName === '')
			{
				continue;
			}

			$query = $db->createQuery()
				->select('COUNT(*)')
				->from($db->quoteName('#__wendfold_locations'))
				->where($db->quoteName('name') . ' = ' . $db->quote($customName));

			if ((int) $db->setQuery($query)->loadResult() > 0)
			{
				continue;
			}

			$loc = new \stdClass;
			$loc->name = $customName;
			$loc->location_type = $set['type'];
			$loc->address = InputStringFilter::clean((string) ($data[$set['address']] ?? ''));
			$loc->city = InputStringFilter::clean((string) ($data[$set['city']] ?? ''));
			$loc->state = InputStringFilter::clean((string) ($data[$set['state']] ?? ''));
			$loc->zip = InputStringFilter::clean((string) ($data[$set['zip']] ?? ''));
			$loc->region = $loc->state;
			$loc->postcode = $loc->zip;
			$loc->country = '';
			$adhoc = 'is_adhoc';
			$loc->$adhoc = 1;
			$loc->is_default = 0;
			$loc->published = 1;
			$loc->created = $date;
			$loc->created_by = $user->id;
			$loc->modified = $date;
			$loc->modified_by = $user->id;

			$db->insertObject('#__wendfold_locations', $loc);
		}
	}

	/**
	 * Credit rows for the event form. A new event uses default credits.
	 *
	 * @param   integer  $eventId  Event id. 0 is a new event.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public function getMetricCreditRows(int $eventId): array
	{
		return EventMetricCredits::rows($this->getDatabase(), $eventId);
	}
}
