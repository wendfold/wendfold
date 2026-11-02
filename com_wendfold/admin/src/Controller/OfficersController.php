<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @since       1.0.0
 *
 * Officers display controller
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

/**
 * Officers list controller class.
 *
 * @since  1.0.0
 */
class OfficersController extends ListController
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD_OFFICERS';

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Officer', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
