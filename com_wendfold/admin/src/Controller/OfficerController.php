<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Officer Controller
 *
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @since       1.0.0
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\Input\Input;

/**
 * Controller for a single officer record (add, edit, save, etc.)
 *
 * @since  1.0.0
 */
class OfficerController extends FormController
{
	/**
	 * The prefix to use with controller messages.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $text_prefix = 'COM_WENDFOLD_OFFICER';

	/**
	 * Constructor.
	 *
	 * @param   array                     $config   An optional associative array of configuration settings.
	 * @param   MVCFactoryInterface|null  $factory  The factory.
	 * @param   CMSApplicationInterface|null $app      The application.
	 * @param   Input|null                $input    The input object.
	 *
	 * @since   1.0.0
	 */
	public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?CMSApplicationInterface $app = null, ?Input $input = null)
	{
		parent::__construct($config, $factory, $app, $input);

		// Register additional tasks (standard for FormController)
		$this->registerTask('apply', 'save');
		$this->registerTask('save2new', 'save');
	}

	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name     The model name. Optional.
	 * @param   string  $prefix   The class prefix. Optional.
	 * @param   array   $config   Configuration array for model. Optional.
	 *
	 * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
	 *
	 * @since   1.0.0
	 */
	public function getModel($name = 'Officer', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}
}
