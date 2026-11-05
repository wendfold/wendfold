<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Wendfold\Component\Wendfold\Administrator\Helper\ComponentOptions;
use Wendfold\Component\Wendfold\Administrator\Helper\SaveDuplicateKey;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

class DuespaymentModel extends AdminModel
{
	public $typeAlias = 'com_wendfold.duespayment';

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_wendfold.duespayment',
			'duespayment',
			['control' => 'jform', 'load_data' => $loadData]
		);

		return empty($form) ? false : $form;
	}

	protected function loadFormData()
	{
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_wendfold.edit.duespayment.data', []);

		if (empty($data))
		{
			$data = $this->getItem();

			if (!$data->id)
			{
				$data->year          = SiteTime::fromApplication()->year();
				$data->amount_paid   = ComponentOptions::duesAmount(ComponentHelper::getParams('com_wendfold')->get('dues_amount', ''));
				$data->payment_date  = SiteTime::fromApplication()->today();
				$data->payment_method = 'Cash';
			}
		}

		return $data;
	}

	/**
	 * Save a dues payment, mapping UNIQUE(profile_id, year) to a friendly message.
	 *
	 * @param   array  $data  Form data.
	 *
	 * @return  boolean
	 *
	 * @since   1.0.0
	 */
	public function save($data)
	{
		// Null-sanitize optional fields
		if (isset($data['payment_date']) && $data['payment_date'] === '')
		{
			$data['payment_date'] = null;
		}

		try
		{
			$result = parent::save($data);
		}
		catch (\Throwable $error)
		{
			if (SaveDuplicateKey::fromThrowable($error))
			{
				$this->setError(Text::_('COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS'));

				return false;
			}

			throw $error;
		}

		if ($result === false)
		{
			$mapped = SaveDuplicateKey::remap(
				(string) $this->getError(),
				'COM_WENDFOLD_ERROR_DUES_PAYMENT_EXISTS'
			);

			if ($mapped !== null)
			{
				$this->setError($mapped);
			}
		}

		return $result;
	}

	public function getTable($name = 'Duespayment', $prefix = 'Table', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}
}
