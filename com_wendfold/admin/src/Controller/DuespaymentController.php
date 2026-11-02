<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Wendfold\Component\Wendfold\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;

class DuespaymentController extends FormController
{
	protected $view_list = 'dues';

	protected $view_item = 'duespayment';
}
