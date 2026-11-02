<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin Model: Single Motorcycle (CRUD)
 * admin/src/Model/MotorcycleModel.php
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;

class MotorcycleModel extends AdminModel
{
	public $typeAlias = 'com_wendfold.motorcycle';

	public function getTable($name = 'Motorcycle', $prefix = 'Table', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.motorcycle',
			'motorcycle',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return empty($form) ? false : $form;
	}

	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_wendfold.edit.motorcycle.data', []);

		if (empty($data))
		{
			$data = $this->getItem();

			// Pre-fill profile_id if passed via URL (from "Add Motorcycle" button)
			if (empty($data->id))
			{
				$profileId = $app->getInput()->getInt('profile_id', 0);

				if ($profileId > 0)
				{
					$data->profile_id = $profileId;
				}

				// Sensible defaults for a new bike
				$data->make       = '';
				$data->is_primary = 0;
				$data->published  = 1;
			}
		}

		return $data;
	}

	/**
	 * Null-sanitize the YEAR column.
	 * MySQL YEAR type rejects empty string  -  must be NULL or a 4-digit year.
	 */
	public function save($data)
	{
		if (isset($data['year']) && ($data['year'] === '' || $data['year'] === '0'))
		{
			$data['year'] = null;
		}

		return parent::save($data);
	}
}
