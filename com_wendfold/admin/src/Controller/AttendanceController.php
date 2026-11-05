<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Attendance Controller
 *
 * Handles officer-facing manual check-in actions on the attendance management view:
 *   - add      : manually check in a member
 *   - remove   : undo a check-in
 *   - complete : mark an event Completed and generate ride logs
 *   - cancel   : mark an event Cancelled
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\DuplicateKey;
use Wendfold\Component\Wendfold\Administrator\Helper\EventRowLock;
use Wendfold\Component\Wendfold\Administrator\Helper\EventStatusTransition;
use Wendfold\Component\Wendfold\Administrator\Helper\PostGuard;
use Wendfold\Component\Wendfold\Administrator\Service\EventCancelNoticeService;
use Wendfold\Component\Wendfold\Administrator\Service\MetricService;

/**
 * Attendance Controller
 *
 * Provides task methods for officer-managed check-in operations.
 * All tasks validate CSRF tokens before executing any write operations.
 *
 * @since  1.0.0
 */
class AttendanceController extends BaseController
{
	/**
	 * Manually add a member to the attendance roster.
	 *
	 * Validates CSRF token, checks for duplicate check-in, then inserts
	 * a new attendance record with check_in_method=2 (officer manual).
	 *
	 * Expects POST: event_id, profile_id, plus a valid Joomla CSRF token.
	 * Requires core.manage and core.create.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function add(): void
	{
		$app       = Factory::getApplication();
		$input     = $app->getInput();
		$eventId   = $input->getInt('event_id', 0);
		$profileId = $input->getInt('profile_id', 0);

		if (!$this->allowStateChange('core.create'))
		{
			return;
		}

		if (!$eventId || !$profileId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT_OR_MEMBER'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . $eventId, false));

			return;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$metrics = MetricService::fromDatabase($db, (int) $app->getIdentity()->id);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			}
			elseif ((int) $locked->published === -2)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_TRASHED'), 'error');
			}
			elseif ((int) $locked->status === 3)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_LOCKED'), 'error');
			}
			else
			{
				$dupQuery = $db->createQuery()
					->select('COUNT(*)')
					->from($db->quoteName('#__wendfold_attendance'))
					->where($db->quoteName('event_id') . ' = :eventId')
					->where($db->quoteName('profile_id') . ' = :profileId')
					->bind(':eventId', $eventId, ParameterType::INTEGER)
					->bind(':profileId', $profileId, ParameterType::INTEGER);

				if ((int) $db->setQuery($dupQuery)->loadResult() > 0)
				{
					$db->transactionRollback(true);
					$metrics->discardDeferredAwardRecompute();
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_ALREADY_CHECKED_IN'), 'notice');
					$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . $eventId, false));

					return;
				}

				$record                  = new \stdClass;
				$record->event_id        = $eventId;
				$record->profile_id      = $profileId;
				$record->check_in_time   = Factory::getDate()->toSql();
				$record->check_in_method = 2;
				$record->checked_in_by   = $app->getIdentity()->id;

				$db->insertObject('#__wendfold_attendance', $record, 'id');
				$metrics->creditAttendance($eventId, (int) $record->id);
				$db->transactionCommit(true);
				$metrics->flushAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_CHECKIN_SUCCESS'), 'success');
			}
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			Log::add('Admin attendance check-in failed: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');

			if ($this->isDuplicateAttendanceError($e))
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_ALREADY_CHECKED_IN'), 'notice');
			}
			else
			{
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
			}
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . $eventId, false));
	}

	/**
	 * Remove a member from the attendance roster (undo check-in).
	 *
	 * Requires POST, a valid CSRF token, core.manage, and core.delete.
	 *
	 * Expects POST: event_id, id (attendance record ID), plus a valid CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function remove(): void
	{
		$app          = Factory::getApplication();
		$input        = $app->getInput();
		$eventId      = $input->getInt('event_id', 0);
		$attendanceId = $input->getInt('id', 0);

		if (!$this->allowStateChange('core.delete'))
		{
			return;
		}

		if (!$eventId || !$attendanceId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_ATTENDANCE_RECORD'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . $eventId, false));

			return;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$metrics = MetricService::fromDatabase($db, (int) $app->getIdentity()->id);
		$metrics->deferAwardRecompute(true);
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			}
			elseif ((int) $locked->status === 3)
			{
				$db->transactionRollback(true);
				$metrics->discardDeferredAwardRecompute();
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_LOCKED'), 'error');
			}
			else
			{
				$owned = $db->setQuery(
					$db->createQuery()
						->select([
							$db->quoteName('id'),
							$db->quoteName('profile_id'),
							]
						)
						->from($db->quoteName('#__wendfold_attendance'))
						->where($db->quoteName('id') . ' = :attendanceId')
						->where($db->quoteName('event_id') . ' = :eventId')
						->bind(':attendanceId', $attendanceId, ParameterType::INTEGER)
						->bind(':eventId', $eventId, ParameterType::INTEGER)
				)->loadObject();

				if (!$owned)
				{
					$db->transactionRollback(true);
					$metrics->discardDeferredAwardRecompute();
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_ATTENDANCE_RECORD'), 'error');
				}
				else
				{
					// phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps -- DB column
					$profileId = (int) $owned->profile_id;
					$db->setQuery(
						$db->createQuery()
							->delete($db->quoteName('#__wendfold_attendance'))
							->where($db->quoteName('id') . ' = :attendanceId')
							->where($db->quoteName('event_id') . ' = :eventId')
							->bind(':attendanceId', $attendanceId, ParameterType::INTEGER)
							->bind(':eventId', $eventId, ParameterType::INTEGER)
					)->execute();

					if ((int) $db->getAffectedRows() !== 1)
					{
						$db->transactionRollback(true);
						$metrics->discardDeferredAwardRecompute();
						$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_ATTENDANCE_RECORD'), 'error');
					}
					else
					{
						$metrics->trashAttendance($eventId, $attendanceId, $profileId);
						$db->transactionCommit(true);
						$metrics->flushAwardRecompute();
						$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_REMOVE_SUCCESS'), 'success');
					}
				}
			}
		}
		catch (\Throwable $e)
		{
			$db->transactionRollback(true);
			$metrics->discardDeferredAwardRecompute();
			Log::add('Admin attendance remove failed: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance&event_id=' . $eventId, false));
	}

	/**
	 * Mark an event as Completed.
	 *
	 * Requires POST, a valid CSRF token, core.manage, and core.edit.state.
	 * Attendance credits are written when the attendance row is created.
	 *
	 * Expects POST: event_id, plus a valid CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function complete(): void
	{
		$app     = Factory::getApplication();
		$eventId = $app->getInput()->getInt('event_id', 0);

		if (!$this->allowStateChange('core.edit.state'))
		{
			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=events', false));

			return;
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$completedBy = (int) $app->getIdentity()->id;
		$completedDate = Factory::getDate()->toSql();
		$allowed = [1, 4];
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null || !\in_array((int) $locked->status, $allowed, true))
			{
				$db->transactionRollback(true);
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED'), 'notice');
			}
			else
			{
				$db->setQuery(
					$db->createQuery()
						->update($db->quoteName('#__wendfold_events'))
						->set($db->quoteName('status') . ' = 2')
						->set($db->quoteName('completed_date') . ' = :completedDate')
						->set($db->quoteName('completed_by') . ' = :completedBy')
						->where($db->quoteName('id') . ' = :eventId')
						->where($db->quoteName('status') . ' IN (1,4)')
						->bind(':completedDate', $completedDate)
						->bind(':completedBy', $completedBy, ParameterType::INTEGER)
						->bind(':eventId', $eventId, ParameterType::INTEGER)
				)->execute();

				$outcome = EventStatusTransition::resolve(
					$db,
					$eventId,
					(int) $db->getAffectedRows(),
					2,
					$allowed
				);

				if ($outcome === EventStatusTransition::OK)
				{
					$db->transactionCommit(true);
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_COMPLETED_SUCCESS'), 'success');
				}
				elseif ($outcome === EventStatusTransition::DB_ERROR)
				{
					$db->transactionRollback(true);
					Log::add(
						'Admin attendance complete affected 0 rows while event stayed open',
						Log::ERROR,
						'com_wendfold'
					);
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
				}
				else
				{
					$db->transactionRollback(true);
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED'), 'notice');
				}
			}
		}
		catch (\Exception $e)
		{
			$db->transactionRollback(true);
			Log::add('Admin attendance complete failed: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=events', false));
	}

	/**
	 * Mark an event as Cancelled.
	 *
	 * Requires POST, a valid CSRF token, core.manage, and core.edit.state.
	 * Updates event status to 3 (Cancelled).
	 * No ride logs are generated for cancelled events.
	 *
	 * Expects POST: event_id, plus a valid CSRF token.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function cancel(): void
	{
		$app     = Factory::getApplication();
		$eventId = $app->getInput()->getInt('event_id', 0);

		if (!$this->allowStateChange('core.edit.state'))
		{
			return;
		}

		if (!$eventId)
		{
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			$app->redirect(Route::_('index.php?option=com_wendfold&view=events', false));

			return;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$allowed = [1, 4, 5];
		$db->transactionStart(true);

		try
		{
			$locked = EventRowLock::lock($db, $eventId);

			if ($locked === null)
			{
				$db->transactionRollback(true);
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_INVALID_EVENT'), 'error');
			}
			elseif (!\in_array((int) $locked->status, $allowed, true))
			{
				$db->transactionRollback(true);
				$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED'), 'notice');
			}
			else
			{
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
					$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_CANCEL_HAS_CHECKINS'), 'error');
				}
				else
				{
					$db->setQuery(
						$db->createQuery()
							->update($db->quoteName('#__wendfold_events'))
							->set($db->quoteName('status') . ' = 3')
							->where($db->quoteName('id') . ' = :eventId')
							->where($db->quoteName('status') . ' IN (1,4,5)')
							->bind(':eventId', $eventId, ParameterType::INTEGER)
					)->execute();

					$affected = (int) $db->getAffectedRows();
					$outcome  = EventStatusTransition::resolve(
						$db,
						$eventId,
						$affected,
						3,
						$allowed
					);

					if ($outcome === EventStatusTransition::OK)
					{
						$db->transactionCommit(true);
						$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_CANCELLED_SUCCESS'), 'warning');
						EventCancelNoticeService::notifyAfterTransition(
							$db,
							$eventId,
							EventCancelNoticeService::shouldNotify($affected, $outcome)
						);
					}
					elseif ($outcome === EventStatusTransition::DB_ERROR)
					{
						$db->transactionRollback(true);
						Log::add(
							'Admin attendance cancel affected 0 rows while event stayed open',
							Log::ERROR,
							'com_wendfold'
						);
						$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
					}
					else
					{
						$db->transactionRollback(true);
						$app->enqueueMessage(Text::_('COM_WENDFOLD_ATTENDANCE_EVENT_ALREADY_CLOSED'), 'notice');
					}
				}
			}
		}
		catch (\Exception $e)
		{
			$db->transactionRollback(true);
			Log::add('Admin attendance cancel failed: ' . $e->getMessage(), Log::ERROR, 'com_wendfold');
			$app->enqueueMessage(Text::_('COM_WENDFOLD_ERROR_OPERATION_FAILED'), 'error');
		}

		$app->redirect(Route::_('index.php?option=com_wendfold&view=events', false));
	}

	/**
	 * Require POST plus a CSRF token, component access, and one core action.
	 *
	 * A failed token check ends the request. A failed ACL check redirects.
	 *
	 * @param   string  $action  core.create, core.delete, or core.edit.state.
	 *
	 * @return  boolean  True when the request may change state.
	 *
	 * @since   1.0.0
	 */
	private function allowStateChange(string $action): bool
	{
		if (!PostGuard::passes())
		{
			jexit(Text::_('JINVALID_TOKEN'));
		}

		$app = Factory::getApplication();
		$user = $app->getIdentity();

		if ($user && $user->authorise('core.manage', 'com_wendfold') && $user->authorise($action, 'com_wendfold'))
		{
			return true;
		}

		$app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
		$app->redirect(Route::_('index.php?option=com_wendfold&view=attendance', false));

		return false;
	}

	/**
	 * Whether the failure is a concurrent duplicate attendance insert.
	 *
	 * @param   \Throwable  $error  Database or driver exception.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	private function isDuplicateAttendanceError(\Throwable $error): bool
	{
		return DuplicateKey::is($error);
	}
}
