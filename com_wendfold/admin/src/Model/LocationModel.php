<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Location Single Record Model
 *
 * Handles loading and saving a single saved chapter location record.
 * Extends Joomla's AdminModel to get getItem(), save(), delete(),
 * and form handling automatically.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;

/**
 * Location Model
 *
 * Single-record model for chapter saved locations. Handles null-sanitization
 * of optional latitude/longitude float fields before saving.
 *
 * @since  1.0.0
 */
class LocationModel extends AdminModel
{
	/**
	 * The type alias used for content type and history tracking.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	public $typeAlias = 'com_wendfold.location';

	/**
	 * Return the form for editing a location record.
	 *
	 * @param   array   $data      Data to pre-populate.
	 * @param   bool    $loadData  Whether to load existing data into the form.
	 *
	 * @return  \Joomla\CMS\Form\Form|false
	 *
	 * @since   1.0.0
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.location',
			'location',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return empty($form) ? false : $form;
	}

	/**
	 * Load the data to pre-populate the edit form.
	 *
	 * Returns session data from a failed save attempt if present,
	 * otherwise loads the database record. Sets sensible defaults
	 * for new (unsaved) records.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_wendfold.edit.location.data', []);

		if (empty($data))
		{
			$data = $this->getItem();

			if (!$data->id)
			{
				$data->published     = 1;
				$data->location_type = 1;
				$data->is_default    = 0;
			}
		}

		return $data;
	}

	/**
	 * Save a location record with null-sanitization for optional float fields.
	 *
	 * Empty-string lat/lng values are converted to null before saving
	 * to prevent database type errors on the DECIMAL columns.
	 *
	 * @param   array  $data  Form data to save.
	 *
	 * @return  bool  True on success, false on failure.
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		// Null-sanitize float fields  -  empty string causes a MySQL type error
		foreach (['lat', 'lng'] as $field)
		{
			if (isset($data[$field]) && $data[$field] === '')
			{
				$data[$field] = null;
			}
		}

		return parent::save($data);
	}

	/**
	 * Return the Location table object.
	 *
	 * @param   string  $name     Table name.
	 * @param   string  $prefix   Table class prefix.
	 * @param   array   $options  Optional configuration.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 *
	 * @since   1.0.0
	 */
	public function getTable($name = 'Location', $prefix = 'Table', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}
}
