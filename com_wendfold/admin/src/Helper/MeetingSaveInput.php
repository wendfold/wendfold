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

defined('_JEXEC') or die;

use Wendfold\Component\Wendfold\Site\Helper\AudienceHelper;

/**
 * Audience, workflow, and RSVP choices for an event save.
 *
 * Choosing a meeting type suggests an audience, a workflow, and an RSVP
 * setting. A posted value is kept, including All Visitors and Standard RSVP.
 * The suggestion is used only when that field was not posted. An audience,
 * workflow, or RSVP value that is present in the raw post and is not exactly
 * an allowed choice stops the save. A blank value is judged from that post,
 * because the filtered data may already hold the form default.
 *
 * @since  1.0.0
 */
final class MeetingSaveInput
{
	/**
	 * Scopes the event form can store.
	 *
	 * @var    int[]
	 * @since  1.0.0
	 */
	public const ALLOWED_SCOPES = [
		AudienceHelper::SCOPE_ALL_VISITORS,
		AudienceHelper::SCOPE_MEMBERS,
		AudienceHelper::SCOPE_OFFICERS,
		AudienceHelper::SCOPE_ROAD_CAPTAINS,
	];

	/**
	 * Workflow modes the event form can store.
	 *
	 * @var    int[]
	 * @since  1.0.0
	 */
	public const ALLOWED_WORKFLOWS = [1, 2, 3, 4, 5];

	/**
	 * RSVP values the event form can store.
	 *
	 * @var    int[]
	 * @since  1.0.0
	 */
	public const ALLOWED_RSVP = [0, 1];

	/**
	 * Suggestions for a new meeting.
	 *
	 * Every meeting type suggests members and RSVP on. The workflow and the
	 * riding flag come from the event type row. Audience is chosen on the
	 * event. A posted value is kept.
	 *
	 * @param   integer  $defaultWorkflow  event_types.default_workflow_mode.
	 * @param   integer  $defaultRiding    event_types.default_riding. Meetings ignore it.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public static function meetingDefaults(int $defaultWorkflow, int $defaultRiding = 0): array
	{
		return [
			'workflow_mode'   => $defaultWorkflow > 0 ? $defaultWorkflow : EventTypeBehavior::workflow(EventTypeBehavior::Meeting->value),
			'audience_scope'  => AudienceHelper::SCOPE_MEMBERS,
			'allow_rsvp'      => 1,
			'is_riding_event' => 0,
		];
	}

	/**
	 * Apply meeting suggestions to a new meeting's save data.
	 *
	 * Each suggestion is written only when that field is absent from $posted
	 * or the posted value is not allowed. A posted Standard RSVP is kept.
	 * Other keys in $data are left as they were.
	 *
	 * @param   array  $defaults  workflow_mode, audience_scope, allow_rsvp.
	 * @param   array  $data      Event fields that will be saved.
	 * @param   array  $posted    Posted fields. A missing key was not sent.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public static function applyNewMeetingChoices(array $defaults, array $data, array $posted): array
	{
		$scope = self::postedChoice($posted, 'audience_scope', self::ALLOWED_SCOPES);
		$rsvp = self::postedChoice($posted, 'allow_rsvp', self::ALLOWED_RSVP);
		$workflow = self::postedChoice($posted, 'workflow_mode', self::ALLOWED_WORKFLOWS);

		$data['audience_scope'] = $scope ?? (int) $defaults['audience_scope'];
		$data['allow_rsvp'] = $rsvp ?? (int) $defaults['allow_rsvp'];
		$data['workflow_mode'] = $workflow ?? (int) $defaults['workflow_mode'];

		return $data;
	}

	/**
	 * Apply save rules and return the fields to store.
	 *
	 * Null means audience_scope, workflow_mode, or allow_rsvp was posted and
	 * is not an allowed choice, so the save must stop. A blank, an array, and
	 * any other value count. The decision uses the raw post, not the filtered
	 * data. A new meeting that omits a field uses the type workflow and the
	 * member audience. A new event that is not a meeting uses the type
	 * workflow, or the behavior default, and RSVP on. An edit does not
	 * substitute one.
	 *
	 * @param   array    $data             Event fields that will be saved.
	 * @param   array    $posted           Posted fields. A missing key was not sent.
	 * @param   boolean  $isNew            True when the record has no id yet.
	 * @param   integer  $behavior         event_types.behavior.
	 * @param   integer  $defaultWorkflow  event_types.default_workflow_mode.
	 * @param   integer  $defaultRiding    event_types.default_riding.
	 *
	 * @return  array|null
	 *
	 * @since   1.0.0
	 */
	public static function prepareSaveData(array $data, array $posted, bool $isNew, int $behavior, int $defaultWorkflow, int $defaultRiding): ?array
	{
		if (self::disallowedChoice($posted) !== null)
		{
			return null;
		}

		$isMeeting = EventTypeBehavior::isMeeting($behavior, 0);

		if ($isNew && $isMeeting)
		{
			$data = self::applyNewMeetingChoices(self::meetingDefaults($defaultWorkflow, $defaultRiding), $data, $posted);
		}

		$data = self::keepChoice($data, $posted, 'audience_scope', self::ALLOWED_SCOPES);
		$data = self::keepChoice($data, $posted, 'workflow_mode', self::ALLOWED_WORKFLOWS);
		$data = self::keepChoice($data, $posted, 'allow_rsvp', self::ALLOWED_RSVP);

		if ($isNew && !$isMeeting)
		{
			$workflow = self::postedChoice($data, 'workflow_mode', self::ALLOWED_WORKFLOWS);
			$rsvp = self::postedChoice($data, 'allow_rsvp', self::ALLOWED_RSVP);

			if (!\array_key_exists('workflow_mode', $posted) && $workflow === null)
			{
				$data['workflow_mode'] = $defaultWorkflow > 0
					? $defaultWorkflow
					: EventTypeBehavior::workflow($behavior);
			}

			if (!\array_key_exists('allow_rsvp', $posted) && $rsvp === null)
			{
				$data['allow_rsvp'] = 1;
			}
		}

		return $data;
	}

	/**
	 * Field name when the raw post sent a choice that is not allowed.
	 *
	 * A missing key was not sent and is allowed through. A blank string, an
	 * array, and any other value that is not exactly an allowed choice are
	 * refused here, before filtered data can substitute the form default.
	 * Audience is checked first, then workflow mode, then Allow RSVP.
	 *
	 * @param   array  $posted  Posted fields.
	 *
	 * @return  string|null  audience_scope, workflow_mode, allow_rsvp, or null.
	 *
	 * @since   1.0.0
	 */
	public static function disallowedChoice(array $posted): ?string
	{
		$fields = [
			'audience_scope' => self::ALLOWED_SCOPES,
			'workflow_mode'  => self::ALLOWED_WORKFLOWS,
			'allow_rsvp'     => self::ALLOWED_RSVP,
		];

		foreach ($fields as $field => $allowed)
		{
			if (!\array_key_exists($field, $posted))
			{
				continue;
			}

			if (self::postedChoice($posted, $field, $allowed) === null)
			{
				return $field;
			}
		}

		return null;
	}

	/**
	 * Language key for the label of a refused choice.
	 *
	 * @param   string|null  $field  Field name from disallowedChoice(), or null.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function choiceLabel(?string $field): string
	{
		return match ($field) {
			'workflow_mode' => 'COM_WENDFOLD_FIELD_WORKFLOW_MODE_LABEL',
			'allow_rsvp'    => 'COM_WENDFOLD_FIELD_ALLOW_RSVP_LABEL',
			default         => 'COM_WENDFOLD_FIELD_AUDIENCE_SCOPE_LABEL',
		};
	}

	/**
	 * Store an allowed choice from the post, or the one already on the data.
	 *
	 * An omitted field leaves a missing key missing. An allowed value already
	 * on $data is normalised to an integer and kept, which is how an edit
	 * retains the stored value when the field was not posted.
	 *
	 * @param   array   $data     Event fields that will be saved.
	 * @param   array   $posted   Posted fields.
	 * @param   string  $field    Field name.
	 * @param   int[]   $allowed  Values that may be stored.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private static function keepChoice(array $data, array $posted, string $field, array $allowed): array
	{
		$choice = self::postedChoice($posted, $field, $allowed);

		if ($choice === null)
		{
			$choice = self::postedChoice($data, $field, $allowed);
		}

		if ($choice !== null)
		{
			$data[$field] = $choice;
		}

		return $data;
	}

	/**
	 * Posted choice, or null when the field was omitted or is not allowed.
	 *
	 * Integers and their decimal strings are accepted. Any other posted
	 * value is rejected so it cannot be stored.
	 *
	 * @param   array   $posted   Posted fields.
	 * @param   string  $field    Field name.
	 * @param   int[]   $allowed  Values that may be stored.
	 *
	 * @return  integer|null
	 *
	 * @since   1.0.0
	 */
	private static function postedChoice(array $posted, string $field, array $allowed): ?int
	{
		if (!\array_key_exists($field, $posted))
		{
			return null;
		}

		$value = $posted[$field];

		foreach ($allowed as $choice)
		{
			if ($value === $choice || $value === (string) $choice)
			{
				return $choice;
			}
		}

		return null;
	}
}
