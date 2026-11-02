<?php

/**
 * Wendfold - Chapter Management System
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

/**
 * Add, edit, and save one metric definition.
 *
 * @since  1.0.0
 */
class MetricdefinitionController extends FormController
{
	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $view_list = 'metricdefinitions'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

	/**
	 * @var    string
	 * @since  1.0.0
	 */
	protected $view_item = 'metricdefinition'; // phpcs:ignore Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps
}
