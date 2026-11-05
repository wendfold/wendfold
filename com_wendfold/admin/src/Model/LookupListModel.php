<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Wendfold\Component\Wendfold\Administrator\Helper\StateValue;

/**
 * List model for a chapter lookup.
 *
 * Ordering, publish state, and a title search are supported. The ordering
 * column is whitelisted.
 *
 * @since  1.0.0
 */
abstract class LookupListModel extends ListModel
{
	/**
	 * Physical table, including the #__ prefix.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	abstract protected function lookupTable(): string;

	/**
	 * Extra columns to select, already safe names such as a.import_code.
	 *
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function extraColumns(): array
	{
		return [];
	}

	/**
	 * Columns the list may sort on, including the a. prefix.
	 *
	 * @return  string[]
	 *
	 * @since   1.0.0
	 */
	protected function orderColumns(): array
	{
		return ['a.ordering', 'a.title', 'a.published', 'a.id'];
	}

	/**
	 * @param   array  $config  Configuration.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [])
	{
		$fields = [
			'id', 'a.id',
			'title', 'a.title',
			'published', 'a.published',
			'ordering', 'a.ordering',
		];

		foreach ($this->orderColumns() as $column)
		{
			$fields[] = $column;
		}

		$config['filter_fields'] = array_values(
			array_unique(
				array_merge(
					$fields,
					$config['filter_fields'] ?? []
				)
			)
		);

		parent::__construct($config);
	}

	/**
	 * Bound search text. Kept on the model so the query bind stays valid.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected string $boundSearch = '';

	/**
	 * Bound published filter.
	 *
	 * @var    integer
	 * @since  1.0.0
	 */
	protected int $boundPublished = 0;

	/**
	 * @return  \Joomla\Database\QueryInterface
	 *
	 * @since   1.0.0
	 */
	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$columns = array_merge(
			[
				'a.id',
				'a.title',
				'a.published',
				'a.ordering',
				'a.checked_out',
				'a.checked_out_time',
				$db->quoteName('editor.name', 'editor'),
			],
			$this->extraColumns()
		);

		$query = $db->createQuery()
			->select($columns)
			->from($db->quoteName($this->lookupTable(), 'a'))
			->join(
				'LEFT',
				$db->quoteName('#__users', 'editor')
				. ' ON ' . $db->quoteName('editor.id') . ' = ' . $db->quoteName('a.checked_out')
			);

		$published = StateValue::get($this->getState(), 'filter.published');

		if ($published !== '' && $published !== null)
		{
			$this->boundPublished = (int) $published;
			$query->where($db->quoteName('a.published') . ' = :published')
				->bind(':published', $this->boundPublished, ParameterType::INTEGER);
		}

		$search = trim((string) StateValue::get($this->getState(), 'filter.search'));

		if ($search !== '')
		{
			$this->boundSearch = '%' . $db->escape($search, true) . '%';
			$query->where($db->quoteName('a.title') . ' LIKE :lookupSearch')
				->bind(':lookupSearch', $this->boundSearch);
		}

		$orderCol = (string) StateValue::get($this->getState(), 'list.ordering', 'a.ordering');
		$orderDirn = strtoupper((string) StateValue::get($this->getState(), 'list.direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

		if (!\in_array($orderCol, $this->orderColumns(), true))
		{
			$orderCol = 'a.ordering';
		}

		$query->order($db->quoteName($orderCol) . ' ' . $orderDirn);

		if ($orderCol !== 'a.ordering')
		{
			$query->order($db->quoteName('a.ordering') . ' ASC');
		}

		return $query;
	}

	/**
	 * @param   string  $ordering   Default column.
	 * @param   string  $direction  Default direction.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function populateState($ordering = 'a.ordering', $direction = 'ASC')
	{
		/** @var \Joomla\CMS\Application\CMSApplication $app */
		$app = Factory::getApplication();

		$this->setState(
			'filter.search',
			$app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')
		);

		$this->setState(
			'filter.published',
			$app->getUserStateFromRequest($this->context . '.filter.published', 'filter_published', '', 'string')
		);

		parent::populateState($ordering, $direction);
	}
}
