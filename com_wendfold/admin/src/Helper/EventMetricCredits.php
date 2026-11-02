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

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Service\MetricValue;

/**
 * Per-event metric credits shown on the event form.
 *
 * A new event starts from default_event_credit. Saving replaces the credit
 * rows; MetricService then re-upserts attendance entries.
 *
 * @since  1.0.0
 */
final class EventMetricCredits
{
	/**
	 * Credit rows for the event edit form currently being rendered.
	 *
	 * @var    object[]
	 * @since  1.0.0
	 */
	private static array $formRows = [];

	/**
	 * Remember credit rows for the open event edit form.
	 *
	 * @param   object[]  $rows  Editable credit rows.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function setFormRows(array $rows): void
	{
		self::$formRows = $rows;
	}

	/**
	 * Credit rows set for the open event edit form.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public static function formRows(): array
	{
		return self::$formRows;
	}

	/**
	 * Scalar subquery for an event's distance credit.
	 *
	 * @param   DatabaseInterface  $db     Database driver.
	 * @param   string             $alias  Event table alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function distanceExpression(DatabaseInterface $db, string $alias = 'e'): string
	{
		return '(SELECT ' . $db->quoteName('c.value')
			. ' FROM ' . $db->quoteName('#__wendfold_event_metric_credits', 'c')
			. ' INNER JOIN ' . $db->quoteName('#__wendfold_metric_definitions', 'md')
			. ' ON ' . $db->quoteName('md.id') . ' = ' . $db->quoteName('c.metric_id')
			. ' WHERE ' . $db->quoteName('c.event_id') . ' = ' . $db->quoteName($alias . '.id')
			. ' AND ' . $db->quoteName('md.metric_key') . ' = ' . $db->quote('distance')
			. ')';
	}

	/**
	 * Editable credit rows for the event form.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id. 0 is a new event.
	 *
	 * @return  object[]
	 *
	 * @since   1.0.0
	 */
	public static function rows(DatabaseInterface $db, int $eventId): array
	{
		$allow = 1;
		$published = 1;
		$query = $db->createQuery()
			->select([
				$db->quoteName('d.id'),
				$db->quoteName('d.metric_key'),
				$db->quoteName('d.title'),
				$db->quoteName('d.unit_label'),
				$db->quoteName('d.decimals'),
				$db->quoteName('d.default_event_credit'),
				]
			)
			->from($db->quoteName('#__wendfold_metric_definitions', 'd'))
			->where($db->quoteName('d.allow_event_credit') . ' = :allowCredit')
			->where($db->quoteName('d.published') . ' = :published')
			->bind(':allowCredit', $allow, ParameterType::INTEGER)
			->bind(':published', $published, ParameterType::INTEGER)
			->order($db->quoteName('d.ordering') . ' ASC');
		$definitions = $db->setQuery($query)->loadObjectList('id') ?: [];

		if ($definitions === [])
		{
			return [];
		}

		$stored = [];

		if ($eventId > 0)
		{
			$creditQuery = $db->createQuery()
				->select([
					$db->quoteName('metric_id'),
					$db->quoteName('value'),
					]
				)
				->from($db->quoteName('#__wendfold_event_metric_credits'))
				->where($db->quoteName('event_id') . ' = :eventId')
				->bind(':eventId', $eventId, ParameterType::INTEGER);

			foreach ($db->setQuery($creditQuery)->loadObjectList() ?: [] as $credit)
			{
				$stored[(int) $credit->metric_id] = (string) $credit->value;
			}
		}

		$rows = [];

		foreach ($definitions as $definition)
		{
			$id = (int) $definition->id;
			$value = '';
			$decimals = (int) $definition->decimals;

			if ($eventId > 0)
			{
				$value = $stored[$id] ?? '';
			}
			elseif ($definition->default_event_credit !== null && $definition->default_event_credit !== '')
			{
				$value = (string) $definition->default_event_credit;
			}

			if ($value !== '')
			{
				$value = MetricValue::formatForInput($value, $decimals);
			}

			$definition->credit_value = $value;
			$rows[] = $definition;
		}

		return $rows;
	}

	/**
	 * Normalise posted credit amounts for metrics that allow event credit.
	 *
	 * Blank amounts are omitted. Invalid amounts throw InvalidArgumentException.
	 *
	 * @param   DatabaseInterface    $db      Database driver.
	 * @param   array<string, mixed> $posted  metric id => posted amount.
	 *
	 * @return  array<int, string>  metric id => stored decimal.
	 *
	 * @since   1.0.0
	 */
	public static function normalisePosted(DatabaseInterface $db, array $posted): array
	{
		$definitions = [];

		foreach (self::rows($db, 0) as $row)
		{
			$definitions[(int) $row->id] = $row;
		}

		$values = [];

		foreach ($posted as $metricId => $raw)
		{
			$metricId = (int) $metricId;
			$definition = $definitions[$metricId] ?? null;

			if ($definition === null)
			{
				continue;
			}

			$value = MetricValue::normalise((string) $raw, (int) $definition->decimals);

			if ($value === null)
			{
				continue;
			}

			$values[$metricId] = $value;
		}

		return $values;
	}

	/**
	 * Replace an event's credit rows with the posted amounts.
	 *
	 * A blank amount removes that credit. Only metrics that allow event credit
	 * are stored. The caller re-upserts attendance entries after this returns.
	 *
	 * @param   DatabaseInterface    $db       Database driver.
	 * @param   integer              $eventId  Event id.
	 * @param   array<string, mixed> $posted   metric id => posted amount.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function replace(DatabaseInterface $db, int $eventId, array $posted): void
	{
		self::replaceNormalised($db, $eventId, self::normalisePosted($db, $posted));
	}

	/**
	 * Replace an event's credit rows with already-normalised amounts.
	 *
	 * @param   DatabaseInterface  $db       Database driver.
	 * @param   integer            $eventId  Event id.
	 * @param   array<int, string> $values   metric id => stored decimal.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function replaceNormalised(DatabaseInterface $db, int $eventId, array $values): void
	{
		if ($eventId < 1)
		{
			throw new \InvalidArgumentException('Event credits need a saved event.');
		}

		$delete = $db->createQuery()
			->delete($db->quoteName('#__wendfold_event_metric_credits'))
			->where($db->quoteName('event_id') . ' = :eventId')
			->bind(':eventId', $eventId, ParameterType::INTEGER);
		$db->setQuery($delete)->execute();

		foreach ($values as $metricId => $value)
		{
			$metricId = (int) $metricId;
			$insert = $db->createQuery()
				->insert($db->quoteName('#__wendfold_event_metric_credits'))
				->columns([
					$db->quoteName('event_id'),
					$db->quoteName('metric_id'),
					$db->quoteName('value'),
					]
				)
				->values(':eventId, :metricId, :creditValue');
			$insert->bind(':eventId', $eventId, ParameterType::INTEGER);
			$insert->bind(':metricId', $metricId, ParameterType::INTEGER);
			$insert->bind(':creditValue', $value);
			$db->setQuery($insert)->execute();
		}
	}
}
